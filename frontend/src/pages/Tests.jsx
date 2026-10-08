import { useEffect, useMemo, useRef, useState } from 'react'

import { api } from '../lib/api'
import { useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Badge, EmptyState, ErrorState, Loading, Pagination } from '../components/ui'

/** Tracks whether the (documented) unlock-status endpoint exists on this server. */
let unlockEndpoint = 'unknown'

function normaliseUnlock(data) {
  if (!data || typeof data !== 'object') return null
  const locked =
    data.locked === true ||
    data.is_locked === true ||
    data.available === false ||
    (typeof data.unlocked === 'boolean' && !data.unlocked)
  return {
    locked,
    reason: data.reason || data.message || data.description || '',
    attempt: data.attempt || null,
  }
}

async function fetchUnlock(testId) {
  if (unlockEndpoint === 'missing') return null
  try {
    const data = await api.unlockStatus(testId)
    unlockEndpoint = 'available'
    return normaliseUnlock(data)
  } catch (error) {
    if (error.status === 404) unlockEndpoint = 'missing'
    return null
  }
}

export default function Tests() {
  const navigate = useNavigate()
  const [subjects, setSubjects] = useState({ status: 'loading', items: [] })
  const [subjectId, setSubjectId] = useState('')
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [page, setPage] = useState(1)
  const [tests, setTests] = useState({ status: 'loading', items: [], meta: null, error: '' })
  const [locks, setLocks] = useState({})
  const [startingId, setStartingId] = useState(null)
  const [startError, setStartError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const requestIdRef = useRef(0)

  useEffect(() => {
    api
      .subjects({ per_page: 100 })
      .then((payload) => setSubjects({ status: 'ready', items: payload.data || [] }))
      .catch(() => setSubjects({ status: 'ready', items: [] }))
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search.trim())
      setPage(1)
    }, 350)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    const requestId = ++requestIdRef.current
    setTests((current) => ({ ...current, status: 'loading', error: '' }))

    api
      .tests({ status: 'published', per_page: 10, page, subject_id: subjectId, search: debouncedSearch })
      .then(async (payload) => {
        if (requestIdRef.current !== requestId) return
        const items = payload.data || []
        setTests({
          status: 'ready',
          items,
          meta: {
            total: payload.total ?? items.length,
            page: payload.current_page ?? page,
            lastPage: payload.last_page ?? 1,
            from: payload.from,
            to: payload.to,
          },
          error: '',
        })

        /* Unlock status is checked for the first test to detect endpoint support. */
        if (items.length) {
          const first = await fetchUnlock(items[0].id)
          if (requestIdRef.current !== requestId) return
          const map = {}
          if (first) map[items[0].id] = first
          if (unlockEndpoint !== 'missing') {
            const rest = await Promise.all(
              items.slice(1).map(async (test) => [test.id, await fetchUnlock(test.id)]),
            )
            if (requestIdRef.current !== requestId) return
            rest.forEach(([id, value]) => {
              if (value) map[id] = value
            })
          }
          setLocks(map)
        }
      })
      .catch((error) => {
        if (requestIdRef.current !== requestId) return
        setTests({ status: 'error', items: [], meta: null, error: error.message })
      })
  }, [subjectId, debouncedSearch, page, reloadKey])

  const subjectOptions = useMemo(() => subjects.items, [subjects.items])

  async function handleStart(test) {
    if (startingId) return
    setStartError('')
    setStartingId(test.id)
    try {
      const payload = await api.startAttempt(test.id)
      const attempt = payload.attempt || payload
      if (attempt.status && attempt.status !== 'in_progress') {
        navigate(`/result/${attempt.id}`)
        return
      }
      navigate(`/exam/${test.id}`)
    } catch (error) {
      if (error.status === 409) {
        const resumeId = error.data?.attempt?.id || error.data?.attempt_id
        if (resumeId) {
          navigate(`/exam/${test.id}`)
          return
        }
        setStartError('You already have this test in progress. Open your history to resume it.')
      } else if (error.status === 403) {
        setStartError(error.message)
      } else {
        setStartError(error.message)
      }
    } finally {
      setStartingId(null)
    }
  }

  return (
    <main className="page">
      <div className="container">
        <div className="page-head">
          <div>
            <span className="kicker">Model tests</span>
            <h1>Choose a test to begin</h1>
            <p>100 questions, 60 minutes, −0.25 negative marking and instant results.</p>
          </div>
        </div>

        <div className="tests-toolbar">
          <div className="search-field">
            <Icon name="search" size={17} />
            <label htmlFor="test-search" className="visually-hidden">
              Search tests
            </label>
            <input
              id="test-search"
              className="form-control"
              type="search"
              placeholder="Search by test title…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>

          <label htmlFor="test-subject" className="visually-hidden">
            Filter by subject
          </label>
          <select
            id="test-subject"
            className="form-control"
            value={subjectId}
            onChange={(event) => {
              setSubjectId(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All subjects</option>
            {subjectOptions.map((subject) => (
              <option key={subject.id} value={subject.id}>
                {subject.name}
              </option>
            ))}
          </select>

          {tests.meta && (
            <span className="toolbar-count">
              {tests.meta.total} test{tests.meta.total === 1 ? '' : 's'} found
            </span>
          )}
        </div>

        {startError && (
          <div className="alert alert-error" role="alert" style={{ marginBottom: 16 }}>
            <Icon name="alert" size={16} /> <span>{startError}</span>
          </div>
        )}

        {tests.status === 'loading' && <Loading label="Loading model tests…" />}
        {tests.status === 'error' && <ErrorState message={tests.error} onRetry={() => setReloadKey((value) => value + 1)} />}
        {tests.status === 'ready' && tests.items.length === 0 && (
          <EmptyState
            icon="search"
            title="No tests match your filters"
            hint="Try a different subject or clear the search box."
          />
        )}

        {tests.status === 'ready' && tests.items.length > 0 && (
          <div className="test-list">
            {tests.items.map((test) => {
              const lock = locks[test.id]
              const isLocked = Boolean(lock && lock.locked)
              const isStarting = startingId === test.id
              return (
                <article className={`test-item${isLocked ? ' is-locked' : ''}`} key={test.id}>
                  <div className="test-item-main">
                    <div className="test-item-badges">
                      <Badge tone="primary" icon="book">
                        {test.code || `TEST ${test.id}`}
                      </Badge>
                      <Badge tone="neutral">{test.subject?.name || 'General'}</Badge>
                      {test.is_premium && <Badge tone="warning" icon="star">Premium</Badge>}
                      {isLocked && <Badge tone="danger" icon="lock">Locked</Badge>}
                    </div>
                    <h3>{test.title}</h3>
                    {test.description && <p className="test-item-desc">{test.description}</p>}
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
                      <span>
                        <Icon name="check" size={14} /> Pass {test.passing_score}%
                      </span>
                      {test.is_negative_marking_enabled && (
                        <span style={{ color: 'var(--danger)' }}>
                          <Icon name="alert" size={14} /> −{test.negative_marking} per wrong answer
                        </span>
                      )}
                    </div>
                  </div>

                  <div className="test-item-actions">
                    <button
                      type="button"
                      className={`btn ${isLocked ? 'btn-outline' : 'btn-primary'}`}
                      onClick={() => handleStart(test)}
                      disabled={isLocked || Boolean(startingId)}
                      aria-label={isLocked ? `${test.title} is locked` : `Start ${test.title}`}
                    >
                      {isLocked ? (
                        <>
                          <Icon name="lock" size={15} /> Locked
                        </>
                      ) : isStarting ? (
                        'Preparing…'
                      ) : (
                        <>
                          <Icon name="play" size={15} /> Start test
                        </>
                      )}
                    </button>
                    {isLocked && lock.reason && <p className="lock-note">{lock.reason}</p>}
                  </div>
                </article>
              )
            })}
          </div>
        )}

        {tests.meta && (
          <Pagination
            page={Number(tests.meta.page) || 1}
            lastPage={Number(tests.meta.lastPage) || 1}
            total={tests.meta.total}
            from={tests.meta.from}
            to={tests.meta.to}
            onPageChange={setPage}
            label="tests"
          />
        )}
      </div>
    </main>
  )
}
