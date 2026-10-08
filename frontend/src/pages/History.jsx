import { useCallback, useEffect, useRef, useState } from 'react'

import { api } from '../lib/api'
import { formatDateTime, formatNumber } from '../lib/format'
import { useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Badge, EmptyState, ErrorState, Loading, Pagination } from '../components/ui'

const STATUS_TONE = {
  submitted: 'success',
  expired: 'warning',
  in_progress: 'info',
}

const STATUS_LABEL = {
  submitted: 'Submitted',
  expired: 'Time expired',
  in_progress: 'In progress',
}

function attemptId(item) {
  return item.attempt_id ?? item.id
}

function testIdOf(item) {
  return item.test?.id ?? item.test_id ?? null
}

export default function History() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [state, setState] = useState({ status: 'loading', items: [], meta: null, error: '' })
  const requestRef = useRef(0)

  const load = useCallback(() => {
    const requestId = ++requestRef.current
    setState((current) => ({ ...current, status: 'loading', error: '' }))
    api
      .attemptHistory({ page, per_page: 15 })
      .then((payload) => {
        if (requestRef.current !== requestId) return
        const items = Array.isArray(payload.data) ? payload.data : payload.attempts || []
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
  }, [page])

  useEffect(() => {
    load()
  }, [load])

  return (
    <main className="page">
      <div className="container">
        <div className="page-head">
          <div>
            <span className="kicker">Attempt history</span>
            <h1>Every test you have taken</h1>
            <p>Resume a test in progress, or revisit any submitted attempt with its full solutions.</p>
          </div>
          <button type="button" className="btn btn-primary btn-sm" onClick={() => navigate('/tests')}>
            <Icon name="play" size={15} /> Start a new test
          </button>
        </div>

        {state.status === 'loading' && <Loading label="Loading your attempts…" />}

        {state.status === 'error' && <ErrorState message={state.error} onRetry={load} />}

        {state.status === 'ready' && state.items.length === 0 && (
          <EmptyState
            icon="clock"
            title="No attempts yet"
            hint="Start your first model test and it will show up here with its score and solutions."
            action={
              <button type="button" className="btn btn-primary btn-sm" onClick={() => navigate('/tests')}>
                <Icon name="list" size={15} /> Browse model tests
              </button>
            }
          />
        )}

        {state.status === 'ready' && state.items.length > 0 && (
          <>
            <div className="history-table-wrap">
              <table className="history-table">
                <caption className="visually-hidden">Your previous test attempts</caption>
                <thead>
                  <tr>
                    <th scope="col">Test</th>
                    <th scope="col">Status</th>
                    <th scope="col">Score</th>
                    <th scope="col">Percentage</th>
                    <th scope="col">Date</th>
                    <th scope="col">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {state.items.map((item) => {
                    const id = attemptId(item)
                    const linkedTestId = testIdOf(item)
                    const status = item.status || ''
                    const title = item.test_title || item.test?.title || (linkedTestId ? `Test #${linkedTestId}` : 'Model test')
                    const score = item.score
                    const percentage = item.percentage
                    const when = item.finished_at || item.started_at || item.created_at
                    const isFinished = status === 'submitted' || status === 'expired'

                    return (
                      <tr key={id ?? `${title}-${when}`}>
                        <td>
                          <strong>{title}</strong>
                          <small>{item.test?.subject?.name || item.subject_name || 'General'}</small>
                        </td>
                        <td>
                          <Badge tone={STATUS_TONE[status] || 'neutral'}>{STATUS_LABEL[status] || status || 'Unknown'}</Badge>
                        </td>
                        <td className="history-score">
                          {score !== null && score !== undefined ? `${score} / ${item.total_marks ?? '—'}` : '—'}
                        </td>
                        <td className="history-score">
                          {percentage !== null && percentage !== undefined ? `${percentage}%` : '—'}
                        </td>
                        <td>{formatDateTime(when)}</td>
                        <td>
                          <div className="history-actions">
                            {status === 'in_progress' && linkedTestId ? (
                              <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                onClick={() => navigate(`/exam/${linkedTestId}`)}
                              >
                                <Icon name="play" size={14} /> Resume
                              </button>
                            ) : null}
                            {isFinished && id !== null && id !== undefined ? (
                              <>
                                <button
                                  type="button"
                                  className="btn btn-outline btn-sm"
                                  onClick={() => navigate(`/result/${id}`)}
                                >
                                  Result
                                </button>
                                <button
                                  type="button"
                                  className="btn btn-ghost btn-sm"
                                  onClick={() => navigate(`/solutions/${id}`)}
                                >
                                  Solutions
                                </button>
                              </>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <Pagination
              page={state.meta.page}
              lastPage={state.meta.lastPage}
              total={state.meta.total}
              from={state.meta.from}
              to={state.meta.to}
              onPageChange={setPage}
              label="attempts"
            />
          </>
        )}
      </div>
    </main>
  )
}
