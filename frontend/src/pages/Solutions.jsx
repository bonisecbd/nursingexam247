import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

import { api } from '../lib/api'
import { formatNumber } from '../lib/format'
import { parseOptions } from '../lib/options'
import { Link, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Badge, EmptyState, ErrorState, Loading, Pagination } from '../components/ui'

const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E', 'F']
const PER_PAGE = 5

function statusOf(item) {
  if (item.is_correct === null || item.is_correct === undefined) return 'skipped'
  return item.is_correct ? 'correct' : 'wrong'
}

const STATUS_LABEL = {
  correct: 'Correct',
  wrong: 'Wrong',
  skipped: 'Skipped',
}

const STATUS_TONE = {
  correct: 'success',
  wrong: 'danger',
  skipped: 'warning',
}

function SolutionCard({ item }) {
  const options = useMemo(() => parseOptions(item.options), [item])
  const status = statusOf(item)
  const correctOption = Number(item.correct_option)
  const selectedOption = item.selected_option === null || item.selected_option === undefined ? null : Number(item.selected_option)
  const points = formatNumber(item.points)

  return (
    <article className={`solution-card is-${status}`}>
      <div className="solution-head">
        <div className="solution-head-left">
          <Badge tone={STATUS_TONE[status]} icon={status === 'correct' ? 'check' : status === 'wrong' ? 'close' : 'alert'}>
            Q{item.sequence} — {STATUS_LABEL[status]}
          </Badge>
          <span className="small muted">
            {points} mark{points === 1 ? '' : 's'}
          </span>
        </div>
        <span className="small muted">
          {selectedOption === null ? 'No answer recorded' : `Your answer: ${OPTION_KEYS[selectedOption - 1] || selectedOption}`}
        </span>
      </div>

      <p className="solution-text">{item.question_text}</p>

      <div className="solution-options">
        {options.map((option, position) => {
          const value = position + 1
          const isCorrect = value === correctOption
          const isSelected = value === selectedOption
          const classes = ['solution-option', isCorrect ? 'is-correct' : '', !isCorrect && isSelected ? 'is-wrong' : '']
            .filter(Boolean)
            .join(' ')
          return (
            <div className={classes} key={value}>
              <b aria-hidden="true">{OPTION_KEYS[position] || value}</b>
              <span>{String(option)}</span>
              {isCorrect && <span className="option-tag">Correct answer</span>}
              {!isCorrect && isSelected && <span className="option-tag">Your answer</span>}
            </div>
          )
        })}
      </div>

      {item.explanation && (
        <div className="explanation">
          <Icon name="spark" size={17} />
          <p>
            <strong>ব্যাখ্যা / Explanation:</strong> {item.explanation}
          </p>
        </div>
      )}
    </article>
  )
}

export default function Solutions({ attemptId }) {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [state, setState] = useState({ status: 'loading', items: [], meta: null, error: '' })
  const [summary, setSummary] = useState(null)
  const requestRef = useRef(0)

  useEffect(() => {
    let active = true
    api
      .result(attemptId)
      .then((payload) => {
        if (active) setSummary(payload.result || payload.attempt || payload)
      })
      .catch(() => {
        /* the review page works without the summary banner */
      })
    return () => {
      active = false
    }
  }, [attemptId])

  const load = useCallback(() => {
    const requestId = ++requestRef.current
    setState((current) => ({ ...current, status: 'loading', error: '' }))
    api
      .solutions(attemptId, { page, per_page: PER_PAGE })
      .then((payload) => {
        if (requestRef.current !== requestId) return
        const items = Array.isArray(payload.data) ? payload.data : payload.solutions || []
        setState({
          status: 'ready',
          items,
          meta: {
            page: formatNumber(payload.current_page, page),
            lastPage: formatNumber(payload.last_page, 1),
            total: formatNumber(payload.total, items.length),
            from: payload.from,
            to: payload.to,
          },
          error: '',
        })
      })
      .catch((error) => {
        if (requestRef.current !== requestId) return
        setState({ status: 'error', items: [], meta: null, error: error.message })
      })
  }, [attemptId, page])

  useEffect(() => {
    load()
  }, [load])

  const reviewCounts = state.items.reduce(
    (totals, item) => {
      const status = statusOf(item)
      totals[status] += 1
      return totals
    },
    { correct: 0, wrong: 0, skipped: 0 },
  )

  return (
    <main className="page">
      <div className="container">
        <div className="page-head">
          <div>
            <span className="kicker">Solutions &amp; review</span>
            <h1>{summary?.test?.title || 'Question-by-question review'}</h1>
            <p>
              Compare your answers with the correct ones, then read the Bengali explanation for every question you missed.
            </p>
          </div>
          <div className="row" style={{ gap: 10 }}>
            <button type="button" className="btn btn-outline btn-sm" onClick={() => navigate(`/result/${attemptId}`)}>
              <Icon name="chevronLeft" size={15} /> Result
            </button>
            <Link to="/history" className="btn btn-ghost btn-sm">
              History
            </Link>
          </div>
        </div>

        {summary && (
          <div className="result-badges" style={{ marginBottom: 18 }}>
            <Badge tone="success" icon="check">
              {formatNumber(summary.correct_count)} correct
            </Badge>
            <Badge tone="danger" icon="close">
              {formatNumber(summary.wrong_count ?? summary.incorrect_count)} wrong
            </Badge>
            <Badge tone="warning" icon="alert">
              {formatNumber(summary.skipped_count ?? summary.unanswered_count)} skipped
            </Badge>
            <Badge tone="primary">
              {formatNumber(summary.score)} / {formatNumber(summary.total_marks)} marks
            </Badge>
          </div>
        )}

        {state.status === 'loading' && <Loading label="Loading solutions…" />}

        {state.status === 'error' && <ErrorState message={state.error} onRetry={load} />}

        {state.status === 'ready' && state.items.length === 0 && (
          <EmptyState
            icon="book"
            title="No solutions for this attempt"
            hint="Answer reviews are generated once an attempt has been submitted."
            action={
              <button type="button" className="btn btn-primary btn-sm" onClick={() => navigate('/tests')}>
                Browse tests
              </button>
            }
          />
        )}

        {state.status === 'ready' && state.items.length > 0 && (
          <>
            <p className="small muted" style={{ marginBottom: 12 }} aria-live="polite">
              Showing page {state.meta?.page} of {state.meta?.lastPage} — {reviewCounts.correct} correct,{' '}
              {reviewCounts.wrong} wrong, {reviewCounts.skipped} skipped on this page.
            </p>
            <div className="solution-list">
              {state.items.map((item) => (
                <SolutionCard key={`${item.question_id ?? item.id}-${item.sequence}`} item={item} />
              ))}
            </div>
          </>
        )}

        {state.meta && (
          <Pagination
            page={state.meta.page}
            lastPage={state.meta.lastPage}
            total={state.meta.total}
            from={state.meta.from}
            to={state.meta.to}
            onPageChange={setPage}
            label="questions"
          />
        )}
      </div>
    </main>
  )
}
