/**
 * Exam engine: attempt loading/resume, live countdown, debounced answer
 * autosave, question palette state and submission.
 *
 * All timers and debounce handles live in refs and are cleared on unmount, so
 * navigating away from the exam never leaves a ticking interval behind.
 */
import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../lib/api'

const SAVE_DEBOUNCE_MS = 300
const SAVED_FLASH_MS = 1500

/** In-flight attempt requests, shared so a remount never starts two attempts. */
const pendingStarts = new Map()

async function requestStart(testId) {
  try {
    return await api.startAttempt(testId)
  } catch (error) {
    if (error.status !== 409) throw error
    /* Another attempt is already running: resume it instead. */
    const resumeId = error.data?.attempt?.id || error.data?.attempt_id
    if (resumeId) return api.resumeAttempt(resumeId)
    const history = await api.attemptHistory({ test_id: testId, per_page: 20 })
    const inProgress = (history.data || []).find((item) => item.status === 'in_progress')
    if (inProgress) return api.resumeAttempt(inProgress.attempt_id ?? inProgress.id)
    throw error
  }
}

function startAttemptRequest(testId) {
  if (!pendingStarts.has(testId)) {
    const promise = requestStart(testId).finally(() => pendingStarts.delete(testId))
    pendingStarts.set(testId, promise)
  }
  return pendingStarts.get(testId)
}

function normalise(payload) {
  const attempt = payload.attempt || payload
  const questions = (attempt.questions || payload.questions || []).slice()
  questions.sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
  const answers = {}
  questions.forEach((question) => {
    answers[question.id] = typeof question.selected_option === 'number' ? question.selected_option : null
  })
  return { attempt, questions, answers }
}

function secondsRemaining(expiresAt) {
  if (!expiresAt) return null
  const diff = new Date(expiresAt).getTime() - Date.now()
  return Number.isFinite(diff) ? Math.max(0, Math.ceil(diff / 1000)) : null
}

export function useExam(testId, options = {}) {
  const [phase, setPhase] = useState('loading') // loading | ready | error | finished
  const [error, setError] = useState(null)
  const [attempt, setAttempt] = useState(null)
  const [questions, setQuestions] = useState([])
  const [answers, setAnswers] = useState({})
  const [flags, setFlags] = useState({})
  const [index, setIndex] = useState(0)
  const [remaining, setRemaining] = useState(null)
  const [saveState, setSaveState] = useState('idle') // idle | saving | saved | error
  const [saveError, setSaveError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  const attemptRef = useRef(null)
  const timerRef = useRef(null)
  const saveTimerRef = useRef(null)
  const savedFlashRef = useRef(null)
  const pendingRef = useRef(null)
  const submittingRef = useRef(false)
  const loadStartedRef = useRef(false)
  const onFinishRef = useRef(null)
  const submitRef = useRef(async () => {})
  const phaseRef = useRef('loading')

  useEffect(() => {
    onFinishRef.current = options.onFinish || null
  }, [options.onFinish])

  /* Mirrors `phase` so async callbacks can check it without stale closures. */
  useEffect(() => {
    phaseRef.current = phase
  }, [phase])

  const stopTimer = useCallback(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
  }, [])

  const startTimer = useCallback(() => {
    stopTimer()
    timerRef.current = setInterval(() => {
      setRemaining((previous) => (previous === null ? previous : Math.max(0, previous - 1)))
    }, 1000)
  }, [stopTimer])

  const finish = useCallback((info) => {
    stopTimer()
    setPhase('finished')
    if (typeof onFinishRef.current === 'function') onFinishRef.current(info)
  }, [stopTimer])

  /** The server finalised the attempt (410 deadline or 409 already finished). */
  const handleServerFinish = useCallback(
    (attemptId, reason) => {
      finish({ attemptId, reason })
    },
    [finish],
  )

  const flushSave = useCallback(async () => {
    if (saveTimerRef.current) {
      clearTimeout(saveTimerRef.current)
      saveTimerRef.current = null
    }
    const pending = pendingRef.current
    const attemptId = attemptRef.current
    if (!pending || !attemptId) return

    pendingRef.current = null
    setSaveState('saving')
    try {
      await api.saveAnswer(attemptId, pending.questionId, pending.value)
      if (pendingRef.current) return
      setSaveState('saved')
      setSaveError('')
      if (savedFlashRef.current) clearTimeout(savedFlashRef.current)
      savedFlashRef.current = setTimeout(() => setSaveState('idle'), SAVED_FLASH_MS)
    } catch (requestError) {
      if (requestError.status === 410 || requestError.status === 409) {
        handleServerFinish(attemptId, requestError.status === 410 ? 'expired' : 'finished')
        return
      }
      pendingRef.current = pending
      setSaveState('error')
      setSaveError(requestError.message)
    }
  }, [handleServerFinish])

  const scheduleSave = useCallback(
    (questionId, value) => {
      pendingRef.current = { questionId, value }
      setSaveState('saving')
      setSaveError('')
      if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
      saveTimerRef.current = setTimeout(() => {
        saveTimerRef.current = null
        flushSave()
      }, SAVE_DEBOUNCE_MS)
    },
    [flushSave],
  )

  const selectOption = useCallback(
    (questionId, optionIndex) => {
      setAnswers((previous) => ({ ...previous, [questionId]: optionIndex }))
      scheduleSave(questionId, optionIndex)
    },
    [scheduleSave],
  )

  const clearAnswer = useCallback(
    (questionId) => {
      setAnswers((previous) => ({ ...previous, [questionId]: null }))
      scheduleSave(questionId, null)
    },
    [scheduleSave],
  )

  const toggleFlag = useCallback((questionId) => {
    setFlags((previous) => ({ ...previous, [questionId]: !previous[questionId] }))
  }, [])

  const goTo = useCallback((nextIndex) => {
    setIndex(nextIndex)
  }, [])

  const submit = useCallback(async () => {
    const attemptId = attemptRef.current
    if (!attemptId || submittingRef.current) return
    submittingRef.current = true
    setSubmitting(true)
    setSubmitError('')
    stopTimer()

    try {
      await flushSave()
    } catch {
      /* a failed save must not block submission */
    }

    try {
      const payload = await api.submitAttempt(attemptId)
      submittingRef.current = false
      setSubmitting(false)
      finish({
        attemptId,
        reason: 'submitted',
        result: payload?.result || null,
        attempt: payload?.attempt || null,
      })
    } catch (requestError) {
      submittingRef.current = false
      setSubmitting(false)
      if (requestError.status === 409) {
        finish({ attemptId, reason: 'already-finished', result: null })
        return
      }
      setSubmitError(requestError.message)
      if (phaseRef.current === 'ready') startTimer()
    }
  }, [finish, flushSave, startTimer, stopTimer])

  /* Keeps the auto-submit effect calling the latest submit implementation. */
  useEffect(() => {
    submitRef.current = submit
  }, [submit])

  const retrySave = useCallback(() => {
    if (!pendingRef.current) {
      setSaveState('idle')
      setSaveError('')
      return
    }
    flushSave()
  }, [flushSave])

  /* ------------------------------------------------------------ lifecycle */
  useEffect(() => {
    if (loadStartedRef.current) return undefined
    loadStartedRef.current = true
    let cancelled = false

    startAttemptRequest(testId)
      .then((payload) => {
        if (cancelled) return
        const { attempt: nextAttempt, questions: nextQuestions, answers: nextAnswers } = normalise(payload)

        if (!nextQuestions.length) {
          setError(new Error('This test has no questions available right now.'))
          setPhase('error')
          return
        }

        attemptRef.current = nextAttempt.id
        setAttempt(nextAttempt)
        setQuestions(nextQuestions)
        setAnswers(nextAnswers)

        const firstUnanswered = nextQuestions.findIndex(
          (question) => nextAnswers[question.id] === null || nextAnswers[question.id] === undefined,
        )
        setIndex(firstUnanswered >= 0 ? firstUnanswered : 0)

        const seconds =
          typeof nextAttempt.time_remaining_seconds === 'number'
            ? nextAttempt.time_remaining_seconds
            : secondsRemaining(nextAttempt.expires_at)
        setRemaining(seconds === null ? 0 : seconds)

        if (nextAttempt.status && nextAttempt.status !== 'in_progress') {
          finish({ attemptId: nextAttempt.id, reason: 'already-finished', attempt: nextAttempt })
        } else {
          setPhase('ready')
        }
      })
      .catch((requestError) => {
        if (cancelled) return
        setError(requestError)
        setPhase('error')
      })

    return () => {
      cancelled = true
    }
  }, [testId, finish])

  /* Countdown: only runs while the exam is ready. */
  useEffect(() => {
    if (phase !== 'ready') return undefined
    startTimer()
    return () => stopTimer()
  }, [phase, startTimer, stopTimer])

  /* Auto-submit the moment the countdown reaches 00:00. */
  useEffect(() => {
    if (phase !== 'ready' || remaining !== 0) return
    submitRef.current()
  }, [phase, remaining])

  /* Clear every timer/debounce when the exam unmounts. */
  useEffect(() => {
    return () => {
      if (timerRef.current) clearInterval(timerRef.current)
      if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
      if (savedFlashRef.current) clearTimeout(savedFlashRef.current)
      const pending = pendingRef.current
      const attemptId = attemptRef.current
      if (pending && attemptId) {
        api.saveAnswer(attemptId, pending.questionId, pending.value).catch(() => {})
      }
      pendingStarts.clear()
    }
  }, [])

  const answeredCount = questions.reduce(
    (total, question) => (answers[question.id] === null || answers[question.id] === undefined ? total : total + 1),
    0,
  )

  return {
    phase,
    error,
    attempt,
    questions,
    answers,
    flags,
    index,
    remaining,
    saveState,
    saveError,
    submitting,
    submitError,
    answeredCount,
    skippedCount: questions.length - answeredCount,
    goTo,
    selectOption,
    clearAnswer,
    toggleFlag,
    submit,
    retrySave,
    setSubmittingFalse: () => setSubmitting(false),
  }
}
