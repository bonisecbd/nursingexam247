import { useCallback, useEffect, useRef, useState } from 'react'

import { api } from '../lib/api'
import { formatDuration, formatDateTime, formatNumber, formatPercentage } from '../lib/format'
import { Link, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Badge, ErrorState, Loading } from '../components/ui'

const RING_RADIUS = 70
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS

/** Draws the arc from 0 → target once the payload is on screen. */
function ScoreRing({ percentage, passed }) {
  const [revealed, setRevealed] = useState(false)

  useEffect(() => {
    const frame = requestAnimationFrame(() => setRevealed(true))
    return () => cancelAnimationFrame(frame)
  }, [])

  const clamped = Math.min(100, Math.max(0, percentage))
  const offset = RING_CIRCUMFERENCE - (revealed ? (clamped / 100) * RING_CIRCUMFERENCE : 0)
  const tone = passed ? 'is-pass' : 'is-fail'

  return (
    <div className={`score-ring ${tone}`}>
      <svg viewBox="0 0 176 176" role="img" aria-label={`Score ${clamped.toFixed(1)} percent`}>
        <circle className="score-ring-track" cx="88" cy="88" r={RING_RADIUS} fill="none" strokeWidth="12" />
        <circle
          className="score-ring-value"
          cx="88"
          cy="88"
          r={RING_RADIUS}
          fill="none"
          strokeWidth="12"
          strokeLinecap="round"
          strokeDasharray={RING_CIRCUMFERENCE}
          strokeDashoffset={offset}
        />
      </svg>
      <div className="score-ring-center">
        <strong>{clamped.toFixed(1)}%</strong>
        <span>Score</span>
      </div>
    </div>
  )
}

export default function Result({ attemptId }) {
  const navigate = useNavigate()
  const [state, setState] = useState({ status: 'loading', result: null, error: '' })
  const requestRef = useRef(0)

  const load = useCallback(() => {
    const requestId = ++requestRef.current
    setState((current) => ({ ...current, status: 'loading', error: '' }))
    api
      .result(attemptId)
      .then((payload) => {
        if (requestRef.current !== requestId) return
        const result = payload.result || payload.attempt || payload
        setState({ status: 'ready', result, error: '' })
      })
      .catch((error) => {
        if (requestRef.current !== requestId) return
        setState({ status: 'error', result: null, error: error.message })
      })
  }, [attemptId])

  useEffect(() => {
    load()
  }, [load])

  if (state.status === 'loading') {
    return (
      <main className="page">
        <div className="container">
          <Loading label="Crunching your result…" />
        </div>
      </main>
    )
  }

  if (state.status === 'error') {
    return (
      <main className="page">
        <div className="container">
          <ErrorState message={state.error} onRetry={load} />
          <div className="row" style={{ justifyContent: 'center', marginTop: 16 }}>
            <Link to="/history" className="btn btn-outline">
              <Icon name="chevronLeft" size={15} /> Back to history
            </Link>
          </div>
        </div>
      </main>
    )
  }

  const result = state.result || {}
  const test = result.test || {}
  const percentage = formatPercentage(result.percentage)
  const passed = Boolean(result.passed ?? result.is_passed)
  const correct = formatNumber(result.correct_count)
  const wrong = formatNumber(result.wrong_count ?? result.incorrect_count)
  const skipped = formatNumber(result.skipped_count ?? result.unanswered_count)
  const score = formatNumber(result.score)
  const totalMarks = formatNumber(result.total_marks)
  const testId = test.id || result.test_id
  const finishedStatus = result.status || 'submitted'

  return (
    <main className="page">
      <div className="container">
        <section className="result-hero">
          <ScoreRing percentage={percentage} passed={passed} />

          <div className="result-copy">
            <span className="kicker">Test result</span>
            <h1>{test.title || result.test_title || 'Model test result'}</h1>
            <p>
              {passed
                ? 'Excellent work — you cleared the passing bar. Review the solutions to lock in the wins.'
                : 'You fell short of the passing score this time. Every wrong answer in the solutions is a free lesson.'}
            </p>

            <div className="result-badges">
              <Badge tone={passed ? 'success' : 'danger'} icon={passed ? 'trophy' : 'alert'}>
                {passed ? 'Passed' : 'Not passed'}
              </Badge>
              <Badge tone={finishedStatus === 'expired' ? 'warning' : 'primary'}>{finishedStatus}</Badge>
              <Badge tone="neutral" icon="clock">
                {formatDuration(result.duration_seconds)}
              </Badge>
              <Badge tone="info">
                Pass mark {formatNumber(test.passing_score, 50)}%
              </Badge>
            </div>

            <div className="result-actions">
              <button type="button" className="btn btn-primary" onClick={() => navigate(`/solutions/${attemptId}`)}>
                <Icon name="book" size={16} /> View solutions
              </button>
              <button type="button" className="btn btn-outline" onClick={() => navigate('/history')}>
                <Icon name="clock" size={16} /> Attempt history
              </button>
              {testId ? (
                <button type="button" className="btn btn-ghost" onClick={() => navigate(`/exam/${testId}`)}>
                  <Icon name="refresh" size={16} /> Retry test
                </button>
              ) : null}
            </div>
          </div>
        </section>

        <div className="result-stats">
          <div className="result-stat">
            <b>{score}</b>
            <span>Score</span>
            <small>out of {totalMarks} marks</small>
          </div>
          <div className="result-stat correct">
            <b>{correct}</b>
            <span>Correct</span>
            <small>awarded full marks</small>
          </div>
          <div className="result-stat wrong">
            <b>{wrong}</b>
            <span>Wrong</span>
            <small>negative marking applied</small>
          </div>
          <div className="result-stat skipped">
            <b>{skipped}</b>
            <span>Skipped</span>
            <small>left unanswered</small>
          </div>
        </div>

        <section className="card card-pad" style={{ marginTop: 16 }}>
          <div className="result-details">
            <div className="result-detail">
              <span>Started</span>
              <b>{formatDateTime(result.started_at)}</b>
            </div>
            <div className="result-detail">
              <span>Finished</span>
              <b>{formatDateTime(result.finished_at)}</b>
            </div>
            <div className="result-detail">
              <span>Time taken</span>
              <b>{formatDuration(result.duration_seconds)}</b>
            </div>
          </div>
        </section>

        <div className="row" style={{ justifyContent: 'center', gap: 10, marginTop: 20 }}>
          <Link to="/tests" className="btn btn-outline">
            <Icon name="list" size={16} /> Take another test
          </Link>
          <Link to="/dashboard" className="btn btn-ghost">
            <Icon name="dashboard" size={16} /> Dashboard
          </Link>
        </div>
      </div>
    </main>
  )
}
