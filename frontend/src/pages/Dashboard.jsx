import { useCallback, useEffect, useState } from 'react'

import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { Link, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Avatar, Badge, EmptyState, ErrorState, Loading } from '../components/ui'

function statFromHistory(history) {
  const items = (history && history.data) || []
  const percentages = items
    .map((item) => Number(item.percentage))
    .filter((value) => Number.isFinite(value))
  const correct = items.reduce((sum, item) => sum + (Number(item.correct_count) || 0), 0)
  const wrong = items.reduce(
    (sum, item) => sum + (Number(item.wrong_count ?? item.incorrect_count) || 0),
    0,
  )
  return {
    total_tests: history && typeof history.total === 'number' ? history.total : items.length,
    test_count: history && typeof history.total === 'number' ? history.total : items.length,
    average_score: percentages.length
      ? percentages.reduce((sum, value) => sum + value, 0) / percentages.length
      : null,
    accuracy_rate: correct + wrong > 0 ? (correct / (correct + wrong)) * 100 : null,
    last_score: percentages.length ? percentages[0] : null,
  }
}

function formatPercent(value) {
  return value === null || value === undefined || Number.isNaN(Number(value))
    ? '—'
    : `${Number(value).toFixed(1)}%`
}

export default function Dashboard() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [overview, setOverview] = useState({ status: 'loading', data: null, note: '' })
  const [tests, setTests] = useState({ status: 'loading', items: [], total: 0 })
  const [attempts, setAttempts] = useState({ status: 'loading', items: [] })
  const [reloadKey, setReloadKey] = useState(0)

  const loadStats = useCallback(async () => {
    setOverview((current) => ({ ...current, status: 'loading' }))
    try {
      const data = await api.analyticsOverview()
      setOverview({ status: 'ready', data, note: '' })
      return
    } catch (error) {
      if (error.status === 404 || error.status === 405) {
        try {
          const history = await api.attemptHistory({ per_page: 100 })
          setOverview({
            status: 'ready',
            data: statFromHistory(history),
            note: 'Live analytics are unavailable on this server, so these figures are calculated from your recent attempts.',
          })
          return
        } catch (historyError) {
          setOverview({ status: 'error', data: null, note: historyError.message })
          return
        }
      }
      setOverview({ status: 'error', data: null, note: error.message })
    }
  }, [])

  useEffect(() => {
    let active = true
    loadStats()
    api
      .tests({ per_page: 6 })
      .then((payload) => {
        if (!active) return
        setTests({ status: 'ready', items: payload.data || [], total: payload.total || 0 })
      })
      .catch((error) => {
        if (active) setTests({ status: 'error', items: [], total: 0, error: error.message })
      })
    api
      .attemptHistory({ per_page: 5 })
      .then((payload) => {
        if (active) setAttempts({ status: 'ready', items: payload.data || [] })
      })
      .catch((error) => {
        if (active) setAttempts({ status: 'error', items: [], error: error.message })
      })
    return () => {
      active = false
    }
  }, [loadStats, reloadKey])

  const stats = overview.data || {}
  const firstName = (user?.name || '').trim().split(/\s+/)[0] || 'there'

  const cards = [
    { icon: 'book', value: stats.total_tests ?? '—', label: 'Tests available', note: 'Published model tests' },
    {
      icon: 'check',
      value: stats.test_count ?? '—',
      label: 'Tests completed',
      note: 'Submitted or expired attempts',
    },
    { icon: 'chart', value: formatPercent(stats.average_score), label: 'Average score', note: 'Across finished attempts' },
    { icon: 'target', value: formatPercent(stats.accuracy_rate), label: 'Accuracy', note: 'Correct answers vs attempts' },
  ]

  return (
    <main className="page">
      <div className="container">
        <section className="dash-welcome">
          <div>
            <span className="kicker">Your study space</span>
            <h1>Welcome back, {firstName} 👋</h1>
            <p>Every question you practise is a step closer to the nurse you are training to become.</p>
          </div>
          <div className="dash-welcome-actions">
            <Link to="/tests" className="btn btn-primary">
              <Icon name="play" size={16} /> Browse model tests
            </Link>
            <Link to="/history" className="btn btn-outline">
              <Icon name="clock" size={16} /> Attempt history
            </Link>
          </div>
        </section>

        {overview.status === 'error' && (
          <div className="dash-note" role="alert">
            <Icon name="alert" size={16} />
            <span>Progress stats could not be loaded: {overview.note}</span>
            <button type="button" className="link-button" onClick={() => setReloadKey((value) => value + 1)}>
              Retry
            </button>
          </div>
        )}
        {overview.note && overview.status === 'ready' && (
          <div className="dash-note" role="status">
            <Icon name="info" size={16} /> <span>{overview.note}</span>
          </div>
        )}

        <section className="dash-stats" aria-label="Progress overview">
          {cards.map((card) => (
            <article className="stat-card" key={card.label}>
              <span className="stat-icon" aria-hidden="true">
                <Icon name={card.icon} size={19} />
              </span>
              <div>
                <b className="stat-value">{overview.status === 'loading' ? '…' : card.value}</b>
                <span className="stat-label">{card.label}</span>
                <span className="stat-note">{card.note}</span>
              </div>
            </article>
          ))}
        </section>

        <div className="dash-grid">
          <section className="card">
            <div className="card-head">
              <h2>Available model tests</h2>
              <Link to="/tests" className="btn btn-ghost btn-sm">
                View all <Icon name="arrow" size={15} />
              </Link>
            </div>
            <div className="card-body">
              {tests.status === 'loading' && <Loading label="Loading your tests…" />}
              {tests.status === 'error' && (
                <ErrorState message={tests.error} onRetry={() => setReloadKey((value) => value + 1)} />
              )}
              {tests.status === 'ready' && tests.items.length === 0 && (
                <EmptyState
                  icon="book"
                  title="No published tests yet"
                  hint="New model tests appear here as soon as the team publishes them."
                />
              )}
              {tests.status === 'ready' && tests.items.length > 0 && (
                <div className="test-list">
                  {tests.items.map((test) => (
                    <article className="test-item" key={test.id}>
                      <div className="test-item-main">
                        <div className="test-item-badges">
                          <Badge tone="primary">{test.code || 'TEST'}</Badge>
                          <Badge tone="neutral">{test.subject?.name || 'General'}</Badge>
                        </div>
                        <h3>{test.title}</h3>
                        <div className="test-item-meta">
                          <span>
                            <Icon name="list" size={14} /> {test.question_count} questions
                          </span>
                          <span>
                            <Icon name="clock" size={14} /> {test.duration_minutes} min
                          </span>
                          <span>
                            <Icon name="award" size={14} /> {test.total_marks} marks
                          </span>
                        </div>
                      </div>
                      <div className="test-item-actions">
                        <button
                          type="button"
                          className="btn btn-primary btn-sm"
                          onClick={() => navigate(`/exam/${test.id}`)}
                        >
                          <Icon name="play" size={15} /> Start
                        </button>
                      </div>
                    </article>
                  ))}
                </div>
              )}
            </div>
          </section>

          <section className="card">
            <div className="card-head">
              <h2>Recent attempts</h2>
              <Link to="/history" className="btn btn-ghost btn-sm">
                History <Icon name="arrow" size={15} />
              </Link>
            </div>
            <div className="card-body">
              {attempts.status === 'loading' && <Loading label="Loading recent attempts…" />}
              {attempts.status === 'error' && <ErrorState message={attempts.error} />}
              {attempts.status === 'ready' && attempts.items.length === 0 && (
                <EmptyState
                  icon="clock"
                  title="No attempts yet"
                  hint="Start your first model test and your results will show up here."
                  action={
                    <Link to="/tests" className="btn btn-primary btn-sm">
                      Browse tests
                    </Link>
                  }
                />
              )}
              {attempts.status === 'ready' &&
                attempts.items.length > 0 &&
                attempts.items.map((attempt) => (
                  <div className="test-item" key={attempt.attempt_id ?? attempt.id} style={{ padding: '13px 0', border: 0, boxShadow: 'none', background: 'transparent' }}>
                    <div className="test-item-main">
                      <h3 style={{ fontSize: '0.95rem' }}>{attempt.test?.title || 'Model test'}</h3>
                      <div className="test-item-meta" style={{ marginTop: 6 }}>
                        <span>
                          <Icon name="award" size={14} /> {attempt.score ?? '—'} / {attempt.total_marks ?? 100}
                        </span>
                        <span>
                          <Icon name="chart" size={14} /> {attempt.percentage ?? '—'}%
                        </span>
                      </div>
                    </div>
                    <div className="test-item-actions">
                      <Badge tone={attempt.status === 'expired' ? 'warning' : 'success'}>{attempt.status}</Badge>
                      <button
                        type="button"
                        className="btn btn-outline btn-sm"
                        onClick={() => navigate(`/result/${attempt.attempt_id ?? attempt.id}`)}
                      >
                        View result
                      </button>
                    </div>
                  </div>
                ))}
            </div>
          </section>
        </div>

        <section className="card" style={{ marginTop: 16 }}>
          <div className="card-body">
            <div className="row" style={{ justifyContent: 'space-between' }}>
              <div className="row">
                <Avatar name={user?.name || ''} avatarUrl={user?.avatar_url} size="md" />
                <div>
                  <strong style={{ display: 'block' }}>{user?.name}</strong>
                  <span className="muted small">{user?.email}</span>
                </div>
              </div>
              <Link to="/profile" className="btn btn-outline btn-sm">
                <Icon name="settings" size={15} /> Edit profile
              </Link>
            </div>
          </div>
        </section>
      </div>
    </main>
  )
}
