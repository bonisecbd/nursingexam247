import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

import { useExam } from '../hooks/useExam'
import { parseOptions } from '../lib/options'
import { useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { ErrorState, Loading, Modal } from '../components/ui'

const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E', 'F']
const WARNING_SECONDS = 5 * 60
const DANGER_SECONDS = 60

function formatClock(totalSeconds) {
  if (totalSeconds === null || totalSeconds === undefined) return '--:--'
  const safe = Math.max(0, Math.floor(totalSeconds))
  const minutes = Math.floor(safe / 60)
  const seconds = safe % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

function saveChip(state) {
  if (state === 'saving') return { text: 'Saving…', className: 'save-chip is-saving', icon: 'refresh' }
  if (state === 'saved') return { text: 'Saved', className: 'save-chip is-saved', icon: 'check' }
  if (state === 'error') return { text: 'Not saved', className: 'save-chip is-error', icon: 'alert' }
  return { text: 'All changes stored', className: 'save-chip', icon: 'check' }
}

export default function Exam({ testId }) {
  const navigate = useNavigate()
  const [showSubmit, setShowSubmit] = useState(false)
  const navigateRef = useRef(navigate)

  useEffect(() => {
    navigateRef.current = navigate
  }, [navigate])

  const handleFinish = useCallback((info) => {
    const target = info?.attemptId ? `/result/${info.attemptId}` : '/history'
    navigateRef.current(target, { replace: true })
  }, [])

  const exam = useExam(testId, { onFinish: handleFinish })
  const {
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
    skippedCount,
    goTo,
    selectOption,
    clearAnswer,
    toggleFlag,
    submit,
    retrySave,
  } = exam

  const question = questions[index]
  const total = questions.length
  const options = useMemo(() => parseOptions(question?.options), [question])
  const selected = question ? answers[question.id] : null
  const flagged = question ? Boolean(flags[question.id]) : false

  const timerTone =
    remaining !== null && remaining <= DANGER_SECONDS ? 'is-danger' : remaining !== null && remaining <= WARNING_SECONDS ? 'is-warning' : ''
  const chip = saveChip(saveState)
  const progressPercent = total ? Math.round((answeredCount / total) * 100) : 0

  /* Warn before closing the tab while a live attempt is open. */
  useEffect(() => {
    if (phase !== 'ready' || answeredCount === 0) return undefined
    function handleBeforeUnload(event) {
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [phase, answeredCount])

  const handleSelect = useCallback(
    (optionIndex) => {
      if (!question) return
      selectOption(question.id, optionIndex)
    },
    [question, selectOption],
  )

  const handleClear = useCallback(() => {
    if (!question) return
    clearAnswer(question.id)
  }, [question, clearAnswer])

  const handleFlag = useCallback(() => {
    if (!question) return
    toggleFlag(question.id)
  }, [question, toggleFlag])

  /* The dialog stays open on failure so the error is visible; on success the
     hook navigates to the result page and this component unmounts. */
  function confirmSubmit() {
    submit()
  }

  if (phase === 'loading') {
    return (
      <div className="exam-shell">
        <main className="exam-body">
          <div className="exam-main">
            <Loading label="Preparing your attempt…" />
          </div>
        </main>
      </div>
    )
  }

  if (phase === 'error') {
    return (
      <div className="exam-shell">
        <main className="exam-body">
          <div className="exam-main">
            <ErrorState
              message={error?.message || 'We could not start this test.'}
              onRetry={() => window.location.reload()}
              retryLabel="Reload test"
            />
            <div className="exam-actions">
              <div className="exam-actions-group">
                <button type="button" className="btn btn-outline" onClick={() => navigate('/tests')}>
                  <Icon name="chevronLeft" size={15} /> Back to tests
                </button>
              </div>
            </div>
          </div>
        </main>
      </div>
    )
  }

  return (
    <div className="exam-shell">
      <header className="exam-topbar">
        <div className="exam-topbar-left">
          <span className="app-brand-mark" aria-hidden="true">
            <Icon name="graduation" size={20} />
          </span>
          <div className="exam-title-block">
            <h1>{attempt?.test?.title || 'Model test'}</h1>
            <p className="exam-progress-text">
              <span>
                Question {total ? index + 1 : 0} of {total}
              </span>
              <span aria-hidden="true">•</span>
              <span>{progressPercent}% answered</span>
              <span aria-hidden="true">•</span>
              <span className={chip.className} role="status" aria-live="polite">
                <Icon name={chip.icon} size={13} /> {chip.text}
              </span>
            </p>
          </div>
        </div>

        <div className="exam-topbar-right">
          {saveState === 'error' && (
            <button type="button" className="btn btn-outline btn-sm" onClick={retrySave}>
              <Icon name="refresh" size={14} /> Retry save
            </button>
          )}
          <div
            className={`timer-chip${timerTone ? ` ${timerTone}` : ''}`}
            role="timer"
            aria-label="Time remaining"
            aria-live={timerTone === 'is-danger' ? 'assertive' : 'off'}
          >
            <span className="timer-label">Time left</span>
            <span className="timer-value">{formatClock(remaining)}</span>
          </div>
          <button type="button" className="btn btn-success btn-sm" onClick={() => setShowSubmit(true)}>
            <Icon name="check" size={15} /> Submit
          </button>
        </div>
      </header>

      <div className="exam-body">
        <div className="exam-main">
          <p className="exam-notice">
            <Icon name="info" size={16} />
            <span>
              Answers save automatically as you go. Wrong answers carry −{attempt?.test?.negative_marking ?? '0.25'} marks —
              skip a question if you are unsure.
            </span>
          </p>

          {saveState === 'error' && saveError && (
            <div className="alert alert-error" role="alert">
              <Icon name="alert" size={16} /> <span>{saveError}</span>
            </div>
          )}

          {question ? (
            <section className="qcard" aria-labelledby="question-heading">
              <div className="qcard-meta">
                <div className="qcard-meta-left">
                  <span className="badge badge-primary">
                    Question {index + 1}/{total}
                  </span>
                  {Number(question.points) > 0 && (
                    <span className="badge badge-info">
                      {question.points} mark{Number(question.points) === 1 ? '' : 's'}
                    </span>
                  )}
                  {selected !== null && selected !== undefined && <span className="badge badge-success">Answered</span>}
                </div>
                <button
                  type="button"
                  className={`btn btn-sm ${flagged ? 'btn-warning' : 'btn-outline'}`}
                  onClick={handleFlag}
                  aria-pressed={flagged}
                >
                  <Icon name="flag" size={14} /> {flagged ? 'Flagged' : 'Flag for review'}
                </button>
              </div>

              <h2 className="qcard-text" id="question-heading">
                {question.question_text}
              </h2>

              <div className="option-list" role="radiogroup" aria-labelledby="question-heading">
                {options.length === 0 && <p className="muted">This question has no options right now.</p>}
                {options.map((option, position) => {
                  const value = position + 1
                  const isChosen = selected === value
                  return (
                    <label className={`option-card${isChosen ? ' is-selected' : ''}`} key={value}>
                      <input
                        type="radio"
                        name={`question-${question.id}`}
                        value={value}
                        checked={isChosen}
                        onChange={() => handleSelect(value)}
                      />
                      <span className="option-key" aria-hidden="true">
                        {OPTION_KEYS[position] || value}
                      </span>
                      <span className="option-text">{String(option)}</span>
                    </label>
                  )
                })}
              </div>
            </section>
          ) : (
            <ErrorState message="This test has no questions available right now." />
          )}

          <nav className="exam-actions" aria-label="Question navigation">
            <div className="exam-actions-group">
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => goTo(index - 1)}
                disabled={index === 0}
              >
                <Icon name="chevronLeft" size={15} /> Previous
              </button>
              <button type="button" className="btn btn-ghost" onClick={handleClear} disabled={!question}>
                Clear answer
              </button>
            </div>
            <div className="exam-actions-group">
              {index < total - 1 ? (
                <button type="button" className="btn btn-primary" onClick={() => goTo(index + 1)}>
                  Next <Icon name="chevronRight" size={15} />
                </button>
              ) : (
                <button type="button" className="btn btn-success" onClick={() => setShowSubmit(true)}>
                  <Icon name="check" size={15} /> Submit test
                </button>
              )}
            </div>
          </nav>
        </div>

        <aside className="palette" aria-label="Question palette">
          <h2>Question palette</h2>
          <p className="palette-summary">
            <span>
              <b>{answeredCount}</b> answered
            </span>
            <span>
              <b>{skippedCount}</b> left
            </span>
            <span>
              <b>{total}</b> total
            </span>
          </p>

          <div className="palette-grid">
            {questions.map((item, position) => {
              const isAnswered = answers[item.id] !== null && answers[item.id] !== undefined
              const isCurrent = position === index
              const isFlagged = Boolean(flags[item.id])
              const classes = [
                'palette-btn',
                isAnswered ? 'is-answered' : '',
                isCurrent ? 'is-current' : '',
                isFlagged ? 'is-flagged' : '',
              ]
                .filter(Boolean)
                .join(' ')
              const stateLabel = isCurrent ? 'current' : isAnswered ? 'answered' : isFlagged ? 'flagged' : 'not answered'
              return (
                <button
                  type="button"
                  className={classes}
                  key={item.id}
                  onClick={() => goTo(position)}
                  aria-label={`Question ${position + 1}, ${stateLabel}`}
                  aria-current={isCurrent ? 'true' : undefined}
                >
                  {position + 1}
                </button>
              )
            })}
          </div>

          <div className="palette-legend">
            <span>
              <i className="legend-swatch answered" aria-hidden="true" /> Answered
            </span>
            <span>
              <i className="legend-swatch" aria-hidden="true" /> Not answered
            </span>
            <span>
              <i className="legend-swatch current" aria-hidden="true" /> Current
            </span>
            <span>
              <i className="legend-swatch flagged" aria-hidden="true" /> Flagged for review
            </span>
          </div>
        </aside>
      </div>

      {showSubmit && (
        <Modal
          title="Submit this test?"
          description="You cannot change your answers after submitting. Review the summary below first."
          tone={skippedCount > 0 ? 'warning' : 'default'}
          onClose={submitting ? undefined : () => setShowSubmit(false)}
          footer={
            <>
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => setShowSubmit(false)}
                disabled={submitting}
              >
                Keep working
              </button>
              <button type="button" className="btn btn-success" onClick={confirmSubmit} disabled={submitting}>
                <Icon name="check" size={15} /> {submitting ? 'Submitting…' : 'Yes, submit test'}
              </button>
            </>
          }
        >
          <div className="modal-stat-grid">
            <div className="modal-stat">
              <b>{answeredCount}</b>
              <span>Answered</span>
            </div>
            <div className="modal-stat">
              <b>{skippedCount}</b>
              <span>Skipped</span>
            </div>
            <div className="modal-stat">
              <b>{total}</b>
              <span>Total</span>
            </div>
          </div>

          <p className="field-note">
            <Icon name="info" size={15} />
            <span>
              Wrong answers deduct {attempt?.test?.negative_marking ?? '0.25'} marks where negative marking is enabled.
              Unanswered questions are simply skipped.
            </span>
          </p>

          {skippedCount > 0 && (
            <div className="alert alert-info" role="status">
              <Icon name="alert" size={16} />
              <span>
                You still have {skippedCount} unanswered question{skippedCount === 1 ? '' : 's'}. You can submit anyway.
              </span>
            </div>
          )}

          {submitError && (
            <div className="alert alert-error" role="alert">
              <Icon name="alert" size={16} /> <span>{submitError}</span>
            </div>
          )}
        </Modal>
      )}
    </div>
  )
}
