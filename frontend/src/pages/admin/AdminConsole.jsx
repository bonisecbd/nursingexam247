/**
 * Admin console: users, subjects, question bank, model tests, exam attempts,
 * results/analytics and gamification reward rules.
 *
 * This module was lifted verbatim out of the original single-file React app so
 * the student-facing screens could become proper routes. It keeps its own
 * legacy call style and styling (`App.css`), and exposes one entry component
 * that the router renders at /admin/*.
 */
import { useEffect, useState } from 'react'

import '../../App.css'
import Icon from '../../components/Icon'
import { apiRequest as request } from '../../lib/api'

/**
 * Compatibility shim for the console's original request helper:
 * `apiRequest(path, { token, body: JSON.stringify(payload) })`.
 * The shared client serialises plain objects itself, so parse the body back
 * before delegating. Everything else (headers, auth, error shape) is shared.
 */
function apiRequest(path, options = {}) {
  const { body } = options
  if (typeof body !== 'string') return request(path, options)
  try {
    return request(path, { ...options, body: JSON.parse(body) })
  } catch {
    return request(path, options)
  }
}

const adminMenu = [
  { heading: 'OVERVIEW', items: [{ id: 'dashboard', label: 'Dashboard', icon: 'dashboard' }] },
  { heading: 'PEOPLE & CONTENT', items: [
    { id: 'users', label: 'Users', icon: 'users', adminOnly: true, children: [
      { id: 'users-all', label: 'All Users', page: 'users-all' },
      { id: 'users-active', label: 'Active Users', page: 'users-active' },
      { id: 'users-blocked', label: 'Blocked Users', page: 'users-blocked' },
      { id: 'users-details', label: 'Student Details', page: 'users-details' },
      { id: 'users-activity', label: 'User Activity', page: 'users-activity' },
    ] },
    { id: 'question-bank', label: 'Question Bank', icon: 'question', children: [
      { id: 'question-bank-all', label: 'All Questions', page: 'question-bank' },
      { id: 'question-bank-create', label: 'Add Question', action: 'create-question' },
      { id: 'question-bank-import', label: 'Bulk Import', soon: true },
      { id: 'question-bank-categories', label: 'Categories', soon: true },
      { id: 'question-bank-subjects', label: 'Subjects', page: 'subjects' },
      { id: 'question-bank-difficulty', label: 'Difficulty Level', page: 'question-bank' },
      { id: 'question-bank-reports', label: 'Question Reports', soon: true },
    ] },
    { id: 'model-tests', label: 'Model Tests', icon: 'book', children: [
      { id: 'model-tests-all', label: 'All Tests', page: 'model-tests' },
      { id: 'model-tests-create', label: 'Create Test', action: 'create-test' },
      { id: 'model-tests-drafts', label: 'Draft Tests', page: 'model-tests-drafts' },
      { id: 'model-tests-published', label: 'Published Tests', page: 'model-tests' },
      { id: 'model-tests-scheduled', label: 'Scheduled Tests', soon: true },
      { id: 'model-tests-categories', label: 'Test Categories', soon: true },
    ] },
    { id: 'subjects', label: 'Subjects', icon: 'book', page: 'subjects' },
  ] },
  { heading: 'EXAMS & LEARNING', items: [
    { id: 'exams', label: 'Exams', icon: 'clock', page: 'exams-attempts', children: [
      { id: 'exams-live', label: 'Live Exams', page: 'exams-live' },
      { id: 'exams-completed', label: 'Completed Exams', page: 'exams-completed' },
      { id: 'exams-attempts', label: 'Exam Attempts', page: 'exams-attempts' },
      { id: 'exams-suspicious', label: 'Suspicious Attempts', soon: true },
    ] },
    { id: 'results', label: 'Results & Analytics', icon: 'chart', page: 'results-all', children: [
      { id: 'results-all', label: 'All Results', page: 'results-all' },
      { id: 'results-students', label: 'Student Performance', soon: true },
      { id: 'results-subjects', label: 'Subject Performance', soon: true },
      { id: 'results-tests', label: 'Test Performance', soon: true },
      { id: 'results-analytics', label: 'Analytics', page: 'results-analytics' },
    ] },
    { id: 'leaderboard', label: 'Leaderboard', icon: 'trophy', page: 'leaderboard' },
    { id: 'gamification', label: 'Gamification', icon: 'spark', adminOnly: true, children: [
      { id: 'gamification-rules', label: 'Reward rules', page: 'gamification' },
      { id: 'gamification-progress', label: 'My progress', page: 'gamification-progress' },
    ] },
    { id: 'challenges', label: 'Challenges', icon: 'star', soon: true, children: ['Daily Challenge', 'Weekly Challenge', 'Competition'] },
  ] },
  { heading: 'COMMERCE', items: [
    { id: 'coupons', label: 'Coupons', icon: 'tag', soon: true, children: ['All Coupons', 'Create Coupon', 'Active', 'Expired'] },
    { id: 'referrals', label: 'Referrals', icon: 'users', page: 'referrals' },
    { id: 'wallet', label: 'Wallet', icon: 'wallet', soon: true, children: ['User Wallets', 'Transactions', 'Credits', 'Debits'] },
    { id: 'payments', label: 'Payments', icon: 'payment', soon: true, children: ['Transactions', 'Successful', 'Failed', 'Pending', 'Refunds'] },
    { id: 'subscriptions', label: 'Subscriptions', icon: 'star', soon: true, children: ['Plans', 'Premium Users', 'Expired', 'Renewals'] },
  ] },
  { heading: 'ENGAGEMENT & SYSTEM', items: [
    { id: 'notifications', label: 'Notifications', icon: 'bell', soon: true, children: ['Send Notification', 'Push Notification', 'Announcements', 'Notification History'] },
    { id: 'advertising', label: 'Advertisement', icon: 'megaphone', soon: true, children: ['Banners', 'Ads', 'Campaigns'] },
    { id: 'support', label: 'Support', icon: 'heart', soon: true, children: ['Tickets', 'User Complaints', 'FAQ'] },
    { id: 'security', label: 'Admin & Security', icon: 'shield', soon: true, children: ['Admin Users', 'Roles', 'Permissions', 'Login History', 'Audit Logs'] },
    { id: 'settings', label: 'Settings', icon: 'settings', soon: true, children: ['General', 'Exam Settings', 'Payment Settings', 'Referral Settings', 'Gamification Settings', 'System Settings'] },
  ] },
]

function AdminUsers({ token, page, onNotice }) {
  const [listResult, setListResult] = useState(null)
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [reloadKey, setReloadKey] = useState(0)
  const [selectedId, setSelectedId] = useState(null)
  const [detailsResult, setDetailsResult] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [actionError, setActionError] = useState('')
  const status = page === 'users-active' ? 'active' : page === 'users-blocked' ? 'blocked' : 'all'
  const activityView = page === 'users-activity'
  const title = page === 'users-active' ? 'Active students'
    : page === 'users-blocked' ? 'Blocked students'
      : activityView ? 'User activity'
        : page === 'users-details' ? 'Student details'
          : 'All users'
  const requestKey = `${status}:${activityView}:${currentPage}:${search}:${reloadKey}`

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({
      status,
      sort: activityView ? 'activity' : 'newest',
      per_page: '15',
      page: String(currentPage),
    })
    if (search) params.set('search', search)
    apiRequest(`/admin/users?${params.toString()}`, { token })
      .then((result) => {
        if (!active) return
        setListResult({ key: requestKey, users: result.data || [], meta: result.meta || null })
      })
      .catch((requestError) => {
        if (active) setListResult({ key: requestKey, users: [], meta: null, error: requestError.message })
      })

    return () => { active = false }
  }, [activityView, currentPage, reloadKey, requestKey, search, status, token])

  useEffect(() => {
    if (selectedId === null) return undefined

    let active = true
    apiRequest(`/admin/users/${selectedId}`, { token })
      .then((result) => {
        if (active) setDetailsResult({ key: `${selectedId}:${reloadKey}`, result })
      })
      .catch((requestError) => {
        if (active) setDetailsResult({ key: `${selectedId}:${reloadKey}`, error: requestError.message })
      })

    return () => { active = false }
  }, [reloadKey, selectedId, token])

  const listIsCurrent = listResult?.key === requestKey
  const users = listIsCurrent ? listResult.users : []
  const meta = listIsCurrent ? listResult.meta : null
  const loading = !listIsCurrent
  const error = listIsCurrent ? listResult.error : ''
  const detailsKey = selectedId === null ? null : `${selectedId}:${reloadKey}`
  const detailsIsCurrent = detailsKey !== null && detailsResult?.key === detailsKey
  const details = detailsIsCurrent ? detailsResult.result : null
  const detailsLoading = selectedId !== null && !detailsIsCurrent
  const detailsError = detailsIsCurrent ? detailsResult.error : ''

  function submitSearch(event) {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchText.trim())
  }

  async function updateStatus(student) {
    const nextStatus = !student.is_active
    const action = nextStatus ? 'activate' : 'block'
    if (!window.confirm(`Are you sure you want to ${action} ${student.name}'s account?${nextStatus ? '' : ' Their active API sessions will be revoked.'}`)) return

    setUpdatingId(student.id)
    setActionError('')
    try {
      const result = await apiRequest(`/admin/users/${student.id}/status`, {
        method: 'PATCH',
        token,
        body: JSON.stringify({ is_active: nextStatus }),
      })
      onNotice(result.message)
      setReloadKey((value) => value + 1)
    } catch (requestError) {
      setActionError(requestError.message)
    } finally {
      setUpdatingId(null)
    }
  }

  const number = (value) => Number(value || 0).toLocaleString()
  const date = (value) => value ? new Date(value).toLocaleDateString() : 'Never'

  return (
    <section className="admin-module-page admin-users-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">PEOPLE MANAGEMENT</span><h1>{title}</h1><p>Search student accounts, review profiles and exam activity, and manage account access.</p></div>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{meta ? number(meta.total) : '—'}</b> matching students</span>
        <span><b>{meta ? number(meta.active) : '—'}</b> active accounts</span>
        <span><b>{meta ? number(meta.blocked) : '—'}</b> blocked accounts</span>
      </div>
      <section className="admin-panel admin-users-panel">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">{activityView ? 'RECENTLY ACTIVE FIRST' : status === 'all' ? 'STUDENT ACCOUNTS' : `${status.toUpperCase()} STUDENT ACCOUNTS`}</span><h2>{activityView ? 'Account activity' : 'Student directory'}</h2></div>
          <form className="admin-user-search" onSubmit={submitSearch}>
            <label className="sr-only" htmlFor="admin-user-search">Search students</label>
            <input id="admin-user-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Name, email or phone" maxLength={100} />
            <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
          </form>
        </div>
        {actionError && <div className="form-error dashboard-error" role="alert">{actionError}</div>}
        {error && <div className="form-error dashboard-error" role="alert">{error}</div>}
        {loading ? <div className="dashboard-loading">Loading student accounts…</div> : users.length ? (
          <div className="admin-user-table-wrap">
            <table className={`admin-user-table${activityView ? ' admin-user-activity-table' : ''}`}>
              <thead><tr><th>Student</th><th>Contact</th>{activityView && <><th>Exam attempts</th><th>Last activity</th></>}<th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
              <tbody>{users.map((student) => <tr key={student.id}>
                <td><b>{student.name}</b><small>Student #{student.id}</small></td>
                <td>{student.email}<small>{student.phone || 'No phone added'}</small></td>
                {activityView && <><td>{number(student.attempts_count)}</td><td>{date(student.last_activity_at)}</td></>}
                <td><span className={`admin-user-status ${student.is_active ? 'is-active' : 'is-blocked'}`}>{student.is_active ? 'Active' : 'Blocked'}</span></td>
                <td>{date(student.created_at)}</td>
                <td><div className="admin-user-actions"><button className="admin-user-view" onClick={() => setSelectedId(student.id)}>View details</button><button className={`admin-user-toggle${student.is_active ? ' is-block' : ''}`} onClick={() => updateStatus(student)} disabled={updatingId === student.id}>{updatingId === student.id ? 'Saving…' : student.is_active ? 'Block' : 'Activate'}</button></div></td>
              </tr>)}</tbody>
            </table>
          </div>
        ) : !error ? <div className="admin-users-empty"><Icon name="users" size={22} /><b>No students found</b><span>Try changing the search or account status filter.</span></div> : null}
        {meta && meta.last_page > 1 && <div className="admin-users-pagination"><span>Page {meta.current_page} of {meta.last_page} · {number(meta.total)} students</span><div><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.max(1, value - 1))} disabled={meta.current_page <= 1 || loading}>Previous</button><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.min(meta.last_page, value + 1))} disabled={meta.current_page >= meta.last_page || loading}>Next</button></div></div>}
      </section>
      <p className="admin-data-note"><Icon name="shield" size={15} /> Personal student data and account controls are available to administrators only. Blocking a student revokes all active API sessions.</p>
      {selectedId !== null && <div className="auth-backdrop admin-user-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedId(null) }}>
        <section className="admin-user-detail" role="dialog" aria-modal="true" aria-labelledby="admin-user-detail-title">
          <button className="auth-close" onClick={() => setSelectedId(null)} aria-label="Close student details"><Icon name="close" /></button>
          {detailsLoading ? <div className="dashboard-loading">Loading student details…</div> : detailsError ? <div className="form-error" role="alert">{detailsError}</div> : details && <>
            <span className="eyebrow">STUDENT PROFILE</span>
            <h2 id="admin-user-detail-title">{details.user.name}</h2>
            <p className="admin-user-detail-email">{details.user.email}</p>
            <span className={`admin-user-status ${details.user.is_active ? 'is-active' : 'is-blocked'}`}>{details.user.is_active ? 'Active account' : 'Blocked account'}</span>
            <div className="admin-user-profile-grid">
              <div><small>Phone</small><b>{details.user.phone || 'Not provided'}</b></div>
              <div><small>Date of birth</small><b>{details.user.date_of_birth || 'Not provided'}</b></div>
              <div><small>Gender</small><b>{details.user.gender || 'Not provided'}</b></div>
              <div><small>Email verified</small><b>{details.user.email_verified_at ? date(details.user.email_verified_at) : 'Not verified'}</b></div>
              <div><small>Joined</small><b>{date(details.user.created_at)}</b></div>
              <div><small>Address</small><b>{details.user.address || 'Not provided'}</b></div>
            </div>
            <div className="admin-user-activity-summary"><span><b>{number(details.activity.total_attempts)}</b> attempts</span><span><b>{number(details.activity.completed_attempts)}</b> completed</span><span><b>{details.activity.average_score == null ? '—' : `${Number(details.activity.average_score).toFixed(1)}%`}</b> average score</span></div>
            <h3>Recent exam activity</h3>
            {details.activity.recent_attempts.length ? <div className="admin-user-attempts">{details.activity.recent_attempts.map((attempt) => <div key={attempt.id}><span><b>{attempt.test?.title || 'Unavailable test'}</b><small>{attempt.started_at ? new Date(attempt.started_at).toLocaleString() : 'Date unavailable'}</small></span><span className={`admin-user-attempt-status ${attempt.status}`}>{attempt.status.replace('_', ' ')}</span><b>{attempt.percentage == null ? '—' : `${Number(attempt.percentage).toFixed(1)}%`}</b></div>)}</div> : <p className="empty-state">No exam attempts recorded for this student yet.</p>}
          </>}
        </section>
      </div>}
    </section>
  )
}

function AdminSubjects({ token, onNotice }) {
  const [result, setResult] = useState(null)
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('all')
  const [reloadKey, setReloadKey] = useState(0)
  const [formSubject, setFormSubject] = useState(undefined)
  const [formBusy, setFormBusy] = useState(false)
  const [formError, setFormError] = useState('')
  const requestKey = `${search}:${status}:${reloadKey}`

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ per_page: '100', status })
    if (search) params.set('search', search)
    apiRequest(`/admin/subjects?${params.toString()}`, { token })
      .then((data) => {
        if (active) setResult({ key: requestKey, data })
      })
      .catch((requestError) => {
        if (active) setResult({ key: requestKey, error: requestError.message })
      })

    return () => { active = false }
  }, [requestKey, search, status, token])

  const currentResult = result?.key === requestKey ? result : null
  const subjects = currentResult?.data?.data || []
  const meta = currentResult?.data?.meta
  const loading = !currentResult
  const loadError = currentResult?.error || ''

  function submitSearch(event) {
    event.preventDefault()
    setSearch(searchText.trim())
  }

  async function saveSubject(event) {
    event.preventDefault()
    setFormError('')
    setFormBusy(true)
    const fields = new FormData(event.currentTarget)
    const payload = {
      name: fields.get('name').trim(),
      code: fields.get('code').trim().toUpperCase(),
      description: fields.get('description').trim() || null,
      is_active: fields.get('is_active') === 'on',
    }

    try {
      const editing = formSubject !== null
      const response = await apiRequest(
        editing ? `/admin/subjects/${formSubject.id}` : '/admin/subjects',
        { method: editing ? 'PATCH' : 'POST', token, body: JSON.stringify(payload) },
      )
      setFormSubject(undefined)
      onNotice(response.message)
      setReloadKey((value) => value + 1)
    } catch (requestError) {
      setFormError(requestError.message)
    } finally {
      setFormBusy(false)
    }
  }

  const number = (value) => Number(value || 0).toLocaleString()

  return (
    <section className="admin-module-page admin-subjects-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">LEARNING CATALOGUE</span><h1>Subjects</h1><p>Create and manage the subjects students can browse and take tests in.</p></div>
        <button className="button button-primary" onClick={() => { setFormError(''); setFormSubject(null) }}><Icon name="arrow" size={17} /> Add subject</button>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{meta ? number(meta.total) : '—'}</b> matching subjects</span>
        <span><b>{meta ? number(meta.active) : '—'}</b> active</span>
        <span><b>{meta ? number(meta.inactive) : '—'}</b> inactive</span>
      </div>
      <section className="admin-panel admin-users-panel">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">SUBJECT DIRECTORY</span><h2>All subjects</h2></div>
          <div className="admin-subject-tools">
            <label className="sr-only" htmlFor="admin-subject-status">Filter subjects by status</label>
            <select id="admin-subject-status" value={status} onChange={(event) => setStatus(event.target.value)}><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <form className="admin-user-search" onSubmit={submitSearch}>
              <label className="sr-only" htmlFor="admin-subject-search">Search subjects</label>
              <input id="admin-subject-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Name or code" maxLength={100} />
              <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
            </form>
          </div>
        </div>
        {loadError && <div className="form-error dashboard-error" role="alert">{loadError}</div>}
        {loading ? <div className="dashboard-loading">Loading subjects…</div> : subjects.length ? <div className="admin-user-table-wrap"><table className="admin-subject-table">
          <thead><tr><th>Subject</th><th>Code</th><th>Questions</th><th>Tests</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>{subjects.map((subject) => <tr key={subject.id}>
            <td><b>{subject.name}</b><small>{subject.description || 'No description'}</small></td>
            <td><code>{subject.code}</code></td>
            <td>{number(subject.questions_count)}</td>
            <td>{number(subject.tests_count)}</td>
            <td><span className={`admin-user-status ${subject.is_active ? 'is-active' : 'is-blocked'}`}>{subject.is_active ? 'Active' : 'Inactive'}</span></td>
            <td><button className="admin-user-view" onClick={() => { setFormError(''); setFormSubject(subject) }}>Edit subject</button></td>
          </tr>)}</tbody>
        </table></div> : !loadError ? <div className="admin-users-empty"><Icon name="book" size={22} /><b>No subjects found</b><span>Add a subject or adjust the filters.</span></div> : null}
      </section>
      <p className="admin-data-note"><Icon name="info" size={15} /> Inactive subjects are hidden from the student catalogue. Subjects cannot be deleted because existing questions and tests may depend on them.</p>
      {formSubject !== undefined && <div className="auth-backdrop admin-create-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !formBusy) setFormSubject(undefined) }}>
        <section className="auth-card admin-create-card admin-subject-form-card" role="dialog" aria-modal="true" aria-labelledby="subject-form-title">
          <button className="auth-close" onClick={() => setFormSubject(undefined)} aria-label="Close subject form" disabled={formBusy}><Icon name="close" /></button>
          <div className="auth-eyebrow">SUBJECT MANAGEMENT</div>
          <h2 id="subject-form-title">{formSubject ? 'Edit subject' : 'Add a subject'}</h2>
          <p className="auth-intro">Subjects organize questions and practice tests for students.</p>
          {formError && <div className="form-error" role="alert">{formError}</div>}
          <form className="auth-form admin-test-form" onSubmit={saveSubject}>
            <label>Subject name<input name="name" required maxLength={255} defaultValue={formSubject?.name || ''} placeholder="e.g. Nursing" /></label>
            <label>Subject code<input name="code" required maxLength={20} pattern="[A-Za-z0-9_-]+" defaultValue={formSubject?.code || ''} placeholder="e.g. NUR" /></label>
            <label>Description (optional)<textarea name="description" maxLength={2000} rows="4" defaultValue={formSubject?.description || ''} placeholder="Briefly describe this subject" /></label>
            <label className="admin-checkbox-label"><input name="is_active" type="checkbox" defaultChecked={formSubject?.is_active ?? true} /> Available in the student catalogue</label>
            <button className="button button-primary auth-submit" disabled={formBusy}>{formBusy ? 'Saving subject…' : formSubject ? 'Save changes' : 'Create subject'} {!formBusy && <Icon name="arrow" size={17} />}</button>
          </form>
        </section>
      </div>}
    </section>
  )
}

function AdminQuestionBank({ token, page, onNotice }) {
  const [result, setResult] = useState(null)
  const [subjectsResult, setSubjectsResult] = useState(null)
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [subjectId, setSubjectId] = useState('')
  const [difficulty, setDifficulty] = useState('')
  const [status, setStatus] = useState('all')
  const [reloadKey, setReloadKey] = useState(0)
  const [editorQuestion, setEditorQuestion] = useState(page === 'question-bank-create' ? null : undefined)
  const [actionError, setActionError] = useState('')
  const [updatingId, setUpdatingId] = useState(null)
  const requestKey = `${search}:${subjectId}:${difficulty}:${status}:${currentPage}:${reloadKey}`

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ per_page: '100', status: 'all' })
    apiRequest(`/admin/subjects?${params.toString()}`, { token })
      .then((data) => {
        if (active) setSubjectsResult({ data: data.data || [] })
      })
      .catch((error) => {
        if (active) setSubjectsResult({ error: error.message })
      })
    return () => { active = false }
  }, [token])

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ per_page: '15', status, page: String(currentPage) })
    if (search) params.set('search', search)
    if (subjectId) params.set('subject_id', subjectId)
    if (difficulty) params.set('difficulty', difficulty)
    apiRequest(`/admin/questions?${params.toString()}`, { token })
      .then((data) => {
        if (active) setResult({ key: requestKey, data })
      })
      .catch((error) => {
        if (active) setResult({ key: requestKey, error: error.message })
      })
    return () => { active = false }
  }, [requestKey, search, subjectId, difficulty, status, currentPage, token])

  const currentResult = result?.key === requestKey ? result : null
  const questions = currentResult?.data?.data || []
  const meta = currentResult?.data?.meta
  const loading = !currentResult
  const loadError = currentResult?.error || ''
  const subjects = subjectsResult?.data || []
  const editorSubjects = subjects.filter((subject) => subject.is_active || subject.id === editorQuestion?.subject?.id)

  function submitSearch(event) {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchText.trim())
  }

  async function saveQuestion(payload) {
    setActionError('')
    const editing = editorQuestion !== null
    try {
      const result = await apiRequest(
        editing ? `/admin/questions/${editorQuestion.id}` : '/admin/questions',
        { method: editing ? 'PATCH' : 'POST', token, body: JSON.stringify(payload) },
      )
      setEditorQuestion(undefined)
      onNotice(result.message)
      setReloadKey((value) => value + 1)
    } catch (error) {
      return error.message
    }
    return ''
  }

  async function toggleQuestion(question) {
    const nextStatus = !question.is_active
    if (!window.confirm(`${nextStatus ? 'Activate' : 'Deactivate'} this question?${nextStatus ? '' : ' It will not be available in future tests.'}`)) return
    setUpdatingId(question.id)
    setActionError('')
    try {
      const response = await apiRequest(`/admin/questions/${question.id}`, {
        method: 'PATCH',
        token,
        body: JSON.stringify({ is_active: nextStatus }),
      })
      onNotice(response.message)
      setReloadKey((value) => value + 1)
    } catch (error) {
      setActionError(error.message)
    } finally {
      setUpdatingId(null)
    }
  }

  const number = (value) => Number(value || 0).toLocaleString()
  const statusLabel = status === 'active' ? 'Active questions' : status === 'inactive' ? 'Inactive questions' : 'All questions'

  return (
    <section className="admin-module-page admin-question-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">LEARNING CONTENT</span><h1>Question bank</h1><p>Create and maintain subject-based multiple-choice questions used by model tests.</p></div>
        <button className="button button-primary" onClick={() => setEditorQuestion(null)} disabled={!subjects.length}><Icon name="arrow" size={17} /> Add question</button>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{meta ? number(meta.total) : '—'}</b> matching questions</span>
        <span><b>{meta ? number(meta.active) : '—'}</b> active</span>
        <span><b>{meta ? number(meta.inactive) : '—'}</b> inactive</span>
      </div>
      {subjectsResult?.error && <div className="form-error dashboard-error" role="alert">{subjectsResult.error}</div>}
      {!subjectsResult?.error && !subjects.length && !subjectsResult && <div className="dashboard-loading">Loading subject list…</div>}
      <section className="admin-panel admin-users-panel">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">QUESTION DIRECTORY</span><h2>{statusLabel}</h2></div>
          <div className="admin-question-tools">
            <label className="sr-only" htmlFor="question-subject-filter">Filter by subject</label>
            <select id="question-subject-filter" value={subjectId} onChange={(event) => { setCurrentPage(1); setSubjectId(event.target.value) }}><option value="">All subjects</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select>
            <label className="sr-only" htmlFor="question-difficulty-filter">Filter by difficulty</label>
            <select id="question-difficulty-filter" value={difficulty} onChange={(event) => { setCurrentPage(1); setDifficulty(event.target.value) }}><option value="">All difficulty</option><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select>
            <label className="sr-only" htmlFor="question-status-filter">Filter by status</label>
            <select id="question-status-filter" value={status} onChange={(event) => { setCurrentPage(1); setStatus(event.target.value) }}><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <form className="admin-user-search" onSubmit={submitSearch}>
              <label className="sr-only" htmlFor="question-search">Search questions</label>
              <input id="question-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Search question text" maxLength={200} />
              <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
            </form>
          </div>
        </div>
        {actionError && <div className="form-error dashboard-error" role="alert">{actionError}</div>}
        {loadError && <div className="form-error dashboard-error" role="alert">{loadError}</div>}
        {loading ? <div className="dashboard-loading">Loading questions…</div> : questions.length ? <div className="admin-user-table-wrap"><table className="admin-question-table">
          <thead><tr><th>Question</th><th>Subject</th><th>Difficulty</th><th>Tests</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>{questions.map((question) => <tr key={question.id}>
            <td><b>#{question.id} · {question.question_text}</b><small>{question.options.length} options{question.explanation ? ' · Explanation provided' : ''}</small></td>
            <td>{question.subject?.name || '—'}<small>{question.subject?.code || ''}</small></td>
            <td><span className={`admin-question-difficulty ${question.difficulty}`}>{question.difficulty}</span></td>
            <td>{number(question.tests_count)}<small>{number(question.published_tests_count)} published</small></td>
            <td><span className={`admin-user-status ${question.is_active ? 'is-active' : 'is-blocked'}`}>{question.is_active ? 'Active' : 'Inactive'}</span></td>
            <td><div className="admin-user-actions"><button className="admin-user-view" onClick={() => setEditorQuestion(question)} disabled={question.published_tests_count > 0} title={question.published_tests_count > 0 ? 'Assigned to published tests; editing would change their content.' : 'Edit question'}>{question.published_tests_count > 0 ? 'Published' : 'Edit'}</button><button className={`admin-user-toggle${question.is_active ? ' is-block' : ''}`} onClick={() => toggleQuestion(question)} disabled={updatingId === question.id || question.published_tests_count > 0}>{updatingId === question.id ? 'Saving…' : question.is_active ? 'Deactivate' : 'Activate'}</button></div></td>
          </tr>)}</tbody>
        </table></div> : !loadError ? <div className="admin-users-empty"><Icon name="question" size={22} /><b>No questions found</b><span>Add a question or adjust the filters.</span></div> : null}
        {meta && meta.last_page > 1 && <div className="admin-users-pagination"><span>Page {meta.current_page} of {meta.last_page} · {number(meta.total)} questions</span><div><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.max(1, value - 1))} disabled={meta.current_page <= 1 || loading}>Previous</button><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.min(meta.last_page, value + 1))} disabled={meta.current_page >= meta.last_page || loading}>Next</button></div></div>}
      </section>
      <p className="admin-data-note"><Icon name="shield" size={15} /> Questions assigned to published tests are locked to preserve test content. Create a new question for future tests instead of editing published content.</p>
      {editorQuestion !== undefined && <AdminQuestionEditor question={editorQuestion} subjects={editorSubjects} onClose={() => setEditorQuestion(undefined)} onSave={saveQuestion} />}
    </section>
  )
}

function AdminQuestionEditor({ question, subjects, onClose, onSave }) {
  const [options, setOptions] = useState(() => question?.options?.length ? question.options : ['', '', '', ''])
  const [correctOption, setCorrectOption] = useState(question?.correct_option || 1)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const availableOptions = options.map((text, index) => ({ text: text.trim(), index })).filter((option) => option.text)

  function updateOption(index, value) {
    setOptions((current) => current.map((option, optionIndex) => optionIndex === index ? value : option))
  }

  function addOption() {
    if (options.length < 6) setOptions((current) => [...current, ''])
  }

  function removeOption(index) {
    if (options.length <= 2) return
    const removedIsCorrect = correctOption === index + 1
    setOptions((current) => current.filter((_, optionIndex) => optionIndex !== index))
    if (removedIsCorrect) setCorrectOption(1)
    else if (correctOption > index + 1) setCorrectOption((value) => value - 1)
  }

  async function submit(event) {
    event.preventDefault()
    setError('')
    const form = new FormData(event.currentTarget)
    const finalOptions = options.map((option) => option.trim())
    while (finalOptions.length > 0 && !finalOptions[finalOptions.length - 1]) finalOptions.pop()
    if (finalOptions.length < 2) {
      setError('Enter at least two answer options.')
      return
    }
    if (finalOptions.some((option) => !option)) {
      setError('Fill each option in order or remove the unused option at the end.')
      return
    }
    if (correctOption > finalOptions.length) {
      setError('Choose a correct answer from the available options.')
      return
    }
    const payload = {
      subject_id: Number(form.get('subject_id')),
      question_text: form.get('question_text').trim(),
      options: finalOptions,
      correct_option: Number(correctOption),
      explanation: form.get('explanation').trim() || null,
      difficulty: form.get('difficulty'),
      is_active: form.get('is_active') === 'on',
    }
    setBusy(true)
    const saveError = await onSave(payload)
    if (saveError) {
      setError(saveError)
      setBusy(false)
    }
  }

  return (
    <div className="auth-backdrop admin-create-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !busy) onClose() }}>
      <section className="auth-card admin-create-card admin-question-editor" role="dialog" aria-modal="true" aria-labelledby="question-editor-title">
        <button className="auth-close" onClick={onClose} aria-label="Close question form" disabled={busy}><Icon name="close" /></button>
        <div className="auth-eyebrow">QUESTION BANK</div>
        <h2 id="question-editor-title">{question ? 'Edit question' : 'Add a question'}</h2>
        <p className="auth-intro">Answer options are saved in order. Select the correct option for scoring and solutions.</p>
        {error && <div className="form-error" role="alert">{error}</div>}
        <form className="auth-form admin-test-form" onSubmit={submit}>
          <label>Subject<select name="subject_id" required defaultValue={question?.subject?.id || ''}><option value="" disabled>Select a subject</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select></label>
          <label>Question text<textarea name="question_text" required maxLength={10000} rows="4" defaultValue={question?.question_text || ''} placeholder="Enter the question" /></label>
          <div className="admin-question-options"><div className="admin-question-options-heading"><b>Answer options</b><button className="admin-text-button" type="button" onClick={addOption} disabled={options.length >= 6}>+ Add option</button></div>{options.map((option, index) => <div className="admin-question-option-row" key={index}><span>{String.fromCharCode(65 + index)}</span><textarea aria-label={`Option ${String.fromCharCode(65 + index)}`} value={option} onChange={(event) => updateOption(index, event.target.value)} maxLength={1000} rows="2" placeholder={`Answer option ${String.fromCharCode(65 + index)}`} /><button type="button" onClick={() => removeOption(index)} disabled={options.length <= 2} aria-label={`Remove option ${String.fromCharCode(65 + index)}`}>×</button></div>)}</div>
          <div className="admin-form-row">
            <label>Correct answer<select value={correctOption} onChange={(event) => setCorrectOption(Number(event.target.value))} required>{availableOptions.map((option) => <option key={option.index} value={option.index + 1}>{String.fromCharCode(65 + option.index)} — {option.text.slice(0, 45)}</option>)}</select></label>
            <label>Difficulty<select name="difficulty" defaultValue={question?.difficulty || 'medium'}><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select></label>
          </div>
          <label>Explanation (optional)<textarea name="explanation" maxLength={10000} rows="3" defaultValue={question?.explanation || ''} placeholder="Explain why the correct answer is right" /></label>
          <label className="admin-checkbox-label"><input name="is_active" type="checkbox" defaultChecked={question?.is_active ?? true} /> Available for future tests</label>
          <button className="button button-primary auth-submit" type="submit" disabled={busy || !subjects.length}>{busy ? 'Saving question…' : question ? 'Save changes' : 'Create question'} {!busy && <Icon name="arrow" size={17} />}</button>
        </form>
      </section>
    </div>
  )
}

function AdminResults({ token, page }) {
  const [result, setResult] = useState(null)
  const [subjects, setSubjects] = useState([])
  const [tests, setTests] = useState([])
  const [filterError, setFilterError] = useState('')
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [subjectId, setSubjectId] = useState('')
  const [testId, setTestId] = useState('')
  const [status, setStatus] = useState('all')
  const [currentPage, setCurrentPage] = useState(1)
  const requestKey = `${search}:${subjectId}:${testId}:${status}:${currentPage}`

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest('/admin/subjects?status=all&per_page=100', { token }),
      apiRequest('/admin/tests?status=all&per_page=100', { token }),
    ]).then(([subjectData, testData]) => {
      if (!active) return
      setSubjects(subjectData.data || [])
      setTests(testData.data || [])
    }).catch((error) => {
      if (active) setFilterError(error.message)
    })
    return () => { active = false }
  }, [token])

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ status, per_page: '15', page: String(currentPage) })
    if (search) params.set('search', search)
    if (subjectId) params.set('subject_id', subjectId)
    if (testId) params.set('test_id', testId)
    apiRequest(`/admin/results?${params.toString()}`, { token })
      .then((data) => {
        if (active) setResult({ key: requestKey, data })
      })
      .catch((error) => {
        if (active) setResult({ key: requestKey, error: error.message })
      })
    return () => { active = false }
  }, [requestKey, search, subjectId, testId, status, currentPage, token])

  const currentResult = result?.key === requestKey ? result : null
  const results = currentResult?.data?.data || []
  const meta = currentResult?.data?.meta
  const analytics = currentResult?.data?.analytics
  const loading = !currentResult
  const error = currentResult?.error || ''
  const number = (value) => Number(value || 0).toLocaleString()
  const percent = (value) => value == null ? '—' : `${Number(value).toFixed(1)}%`

  function submitSearch(event) {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchText.trim())
  }

  return (
    <section className="admin-module-page admin-results-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">RESULTS &amp; ANALYTICS</span><h1>{page === 'results-analytics' ? 'Results analytics' : 'All results'}</h1><p>Review finalized student scores and compare performance across subjects and tests.</p></div>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{analytics ? number(analytics.completed_attempts) : '—'}</b> completed attempts</span>
        <span><b>{analytics ? percent(analytics.average_percentage) : '—'}</b> average score</span>
        <span><b>{analytics ? percent(analytics.pass_rate) : '—'}</b> overall pass rate</span>
      </div>
      <section className="admin-panel admin-results-analytics">
        <div className="admin-panel-heading"><div><span className="eyebrow">PERFORMANCE SNAPSHOT</span><h2>Subject performance</h2></div></div>
        {loading ? <div className="dashboard-loading">Calculating result summaries…</div> : analytics?.subjects?.length ? <div className="admin-results-breakdown">
          <div className="admin-results-breakdown-head"><span>SUBJECT</span><span>ATTEMPTS</span><span>AVERAGE</span><span>PASS RATE</span></div>
          {analytics.subjects.map((subject) => <div className="admin-results-breakdown-row" key={subject.id}><b>{subject.name}</b><span>{number(subject.attempts)}</span><span>{percent(subject.average_percentage)}</span><span>{percent(subject.pass_rate)}</span></div>)}
        </div> : !error ? <p className="empty-state">Subject analytics will appear after students finish tests.</p> : null}
      </section>
      <section className="admin-panel admin-results-analytics">
        <div className="admin-panel-heading"><div><span className="eyebrow">TEST PERFORMANCE</span><h2>Most attempted tests</h2></div></div>
        {loading ? <div className="dashboard-loading">Loading test performance…</div> : analytics?.tests?.length ? <div className="admin-results-breakdown admin-results-test-breakdown">
          <div className="admin-results-breakdown-head"><span>TEST</span><span>ATTEMPTS</span><span>AVERAGE</span><span>PASS RATE</span></div>
          {analytics.tests.map((test) => <div className="admin-results-breakdown-row" key={test.id}><span><b>{test.title}</b><small>{test.code}</small></span><span>{number(test.attempts)}</span><span>{percent(test.average_percentage)}</span><span>{percent(test.pass_rate)}</span></div>)}
        </div> : !error ? <p className="empty-state">Test analytics will appear after students finish tests.</p> : null}
      </section>
      <section className="admin-panel admin-users-panel admin-results-directory">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">FINALIZED ATTEMPTS</span><h2>Result directory</h2></div>
          <div className="admin-question-tools admin-results-tools">
            <label className="sr-only" htmlFor="admin-result-subject">Filter by subject</label>
            <select id="admin-result-subject" value={subjectId} onChange={(event) => { setCurrentPage(1); setSubjectId(event.target.value) }}><option value="">All subjects</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select>
            <label className="sr-only" htmlFor="admin-result-test">Filter by test</label>
            <select id="admin-result-test" value={testId} onChange={(event) => { setCurrentPage(1); setTestId(event.target.value) }}><option value="">All tests</option>{tests.map((test) => <option key={test.id} value={test.id}>{test.title}</option>)}</select>
            <label className="sr-only" htmlFor="admin-result-status">Filter by result status</label>
            <select id="admin-result-status" value={status} onChange={(event) => { setCurrentPage(1); setStatus(event.target.value) }}><option value="all">All results</option><option value="passed">Passed</option><option value="failed">Failed</option><option value="submitted">Submitted</option><option value="expired">Expired</option></select>
            <form className="admin-user-search" onSubmit={submitSearch}>
              <label className="sr-only" htmlFor="admin-result-search">Search result directory</label>
              <input id="admin-result-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Student, test title or code" maxLength={100} />
              <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
            </form>
          </div>
        </div>
        {filterError && <div className="form-error dashboard-error" role="alert">{filterError}</div>}
        {error && <div className="form-error dashboard-error" role="alert">{error}</div>}
        {loading ? <div className="dashboard-loading">Loading finalized results…</div> : results.length ? <div className="admin-user-table-wrap">
          <table className="admin-exam-table admin-result-table">
            <thead><tr><th>Student</th><th>Test</th><th>Correct / Wrong / Skip</th><th>Score</th><th>Percentage</th><th>Outcome</th><th>Finished</th></tr></thead>
            <tbody>{results.map((item) => <tr key={item.attempt_id}>
              <td><b>{item.student?.name || 'Unavailable student'}</b><small>Student #{item.student?.id ?? '—'}</small></td>
              <td><b>{item.test?.title || 'Unavailable test'}</b><small>{item.test?.subject || '—'} · {item.test?.code || '—'}</small></td>
              <td>{number(item.correct_count)} / {number(item.wrong_count)} / {number(item.skipped_count)}</td>
              <td>{item.score} / {item.total_marks}</td>
              <td>{percent(item.percentage)}</td>
              <td><span className={`admin-exam-status ${item.passed ? 'submitted' : 'expired'}`}>{item.passed ? 'Passed' : 'Failed'}</span></td>
              <td>{item.finished_at ? new Date(item.finished_at).toLocaleString() : '—'}</td>
            </tr>)}</tbody>
          </table>
        </div> : !error ? <div className="admin-users-empty"><Icon name="chart" size={22} /><b>No finalized results found</b><span>Completed student attempts matching the filters will appear here.</span></div> : null}
        {meta && meta.last_page > 1 && <div className="admin-users-pagination"><span>Page {meta.current_page} of {meta.last_page} · {number(meta.total)} results</span><div><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.max(1, value - 1))} disabled={meta.current_page <= 1 || loading}>Previous</button><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.min(meta.last_page, value + 1))} disabled={meta.current_page >= meta.last_page || loading}>Next</button></div></div>}
      </section>
      <p className="admin-data-note"><Icon name="shield" size={15} /> Analytics use submitted or expired student attempts only; in-progress attempts and answer-level data are excluded.</p>
    </section>
  )
}

function AdminExams({ token, page }) {
  const [result, setResult] = useState(null)
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedAttempt, setSelectedAttempt] = useState(null)
  const [detail, setDetail] = useState(null)
  const [detailError, setDetailError] = useState('')
  const [detailLoading, setDetailLoading] = useState(false)
  const status = page === 'exams-live' ? 'in_progress' : page === 'exams-completed' ? 'completed' : 'all'
  const requestKey = `${status}:${search}:${currentPage}`

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ status, per_page: '15', page: String(currentPage) })
    if (search) params.set('search', search)
    apiRequest(`/admin/exams?${params.toString()}`, { token })
      .then((data) => {
        if (active) setResult({ key: requestKey, data })
      })
      .catch((error) => {
        if (active) setResult({ key: requestKey, error: error.message })
      })
    return () => { active = false }
  }, [requestKey, status, search, currentPage, token])

  const currentResult = result?.key === requestKey ? result : null
  const attempts = currentResult?.data?.data || []
  const meta = currentResult?.data?.meta
  const loading = !currentResult
  const error = currentResult?.error || ''
  const heading = page === 'exams-live' ? 'Live exams' : page === 'exams-completed' ? 'Completed exams' : 'Exam attempts'

  function submitSearch(event) {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchText.trim())
  }

  async function openAttempt(attempt) {
    setSelectedAttempt(attempt)
    setDetail(null)
    setDetailError('')
    setDetailLoading(true)
    try {
      const response = await apiRequest(`/admin/exams/${attempt.id}`, { token })
      setDetail(response.attempt)
    } catch (requestError) {
      setDetailError(requestError.message)
    } finally {
      setDetailLoading(false)
    }
  }

  const number = (value) => Number(value || 0).toLocaleString()
  const dateTime = (value) => value ? new Date(value).toLocaleString() : '—'

  return (
    <section className="admin-module-page admin-exams-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">EXAMS &amp; LEARNING</span><h1>{heading}</h1><p>Monitor student exam attempts and review safe result summaries.</p></div>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{meta ? number(meta.total) : '—'}</b> matching attempts</span>
        <span><b>{meta ? number(meta.in_progress) : '—'}</b> in progress</span>
        <span><b>{meta ? number(meta.submitted + meta.expired) : '—'}</b> completed</span>
      </div>
      <section className="admin-panel admin-users-panel">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">ATTEMPT DIRECTORY</span><h2>{heading}</h2></div>
          <form className="admin-user-search" onSubmit={submitSearch}>
            <label className="sr-only" htmlFor="admin-exam-search">Search by student or test</label>
            <input id="admin-exam-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Student name, test title or code" maxLength={100} />
            <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
          </form>
        </div>
        {error && <div className="form-error dashboard-error" role="alert">{error}</div>}
        {loading ? <div className="dashboard-loading">Loading exam attempts…</div> : attempts.length ? (
          <div className="admin-user-table-wrap">
            <table className="admin-exam-table">
              <thead><tr><th>Student</th><th>Test</th><th>Progress</th><th>Status</th><th>Started</th><th>Result</th><th>Details</th></tr></thead>
              <tbody>{attempts.map((attempt) => <tr key={attempt.id}>
                <td><b>{attempt.student?.name || 'Unavailable student'}</b><small>Student #{attempt.student?.id ?? '—'}</small></td>
                <td><b>{attempt.test?.title || 'Unavailable test'}</b><small>{attempt.test?.subject || '—'} · {attempt.test?.code || '—'}</small></td>
                <td>{number(attempt.answered_count)} / {number(attempt.question_count)} answered</td>
                <td><span className={`admin-exam-status ${attempt.status}`}>{attempt.status.replace('_', ' ')}</span></td>
                <td>{dateTime(attempt.started_at)}</td>
                <td>{attempt.score == null ? '—' : `${attempt.score} / ${attempt.total_marks}`}</td>
                <td><button className="admin-user-view" onClick={() => openAttempt(attempt)}>View summary</button></td>
              </tr>)}</tbody>
            </table>
          </div>
        ) : !error ? <div className="admin-users-empty"><Icon name="clock" size={22} /><b>No exam attempts found</b><span>Attempts matching this filter will appear here.</span></div> : null}
        {meta && meta.last_page > 1 && <div className="admin-users-pagination"><span>Page {meta.current_page} of {meta.last_page} · {number(meta.total)} attempts</span><div><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.max(1, value - 1))} disabled={meta.current_page <= 1 || loading}>Previous</button><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.min(meta.last_page, value + 1))} disabled={meta.current_page >= meta.last_page || loading}>Next</button></div></div>}
      </section>
      <p className="admin-data-note"><Icon name="shield" size={15} /> This view does not expose answer choices or student contact details. Suspicious-attempt flags are not available because no detection policy is configured.</p>
      {selectedAttempt && <div className="auth-backdrop admin-user-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedAttempt(null) }}>
        <section className="admin-user-detail admin-exam-detail" role="dialog" aria-modal="true" aria-labelledby="admin-exam-detail-title">
          <button className="auth-close" onClick={() => setSelectedAttempt(null)} aria-label="Close attempt summary"><Icon name="close" /></button>
          {detailLoading ? <div className="dashboard-loading">Loading attempt summary…</div> : detailError ? <div className="form-error" role="alert">{detailError}</div> : detail && <>
            <span className="eyebrow">EXAM ATTEMPT #{detail.id}</span>
            <h2 id="admin-exam-detail-title">{detail.student?.name || 'Unavailable student'}</h2>
            <p className="admin-user-detail-email">{detail.test?.title || 'Unavailable test'} · {detail.test?.subject || '—'}</p>
            <span className={`admin-exam-status ${detail.status}`}>{detail.status.replace('_', ' ')}</span>
            <div className="admin-user-profile-grid">
              <div><small>Started</small><b>{dateTime(detail.started_at)}</b></div>
              <div><small>Deadline</small><b>{dateTime(detail.expires_at)}</b></div>
              <div><small>Finished</small><b>{dateTime(detail.finished_at)}</b></div>
              <div><small>Duration</small><b>{detail.duration_seconds == null ? '—' : `${Math.floor(detail.duration_seconds / 60)} min ${detail.duration_seconds % 60} sec`}</b></div>
              <div><small>Answered</small><b>{number(detail.answered_count)} / {number(detail.question_count)}</b></div>
              <div><small>Score</small><b>{detail.score == null ? 'In progress' : `${detail.score} / ${detail.total_marks}`}</b></div>
              <div><small>Correct</small><b>{detail.correct_count ?? '—'}</b></div>
              <div><small>Wrong</small><b>{detail.incorrect_count ?? '—'}</b></div>
              <div><small>Skipped</small><b>{detail.unanswered_count ?? '—'}</b></div>
              <div><small>Percentage</small><b>{detail.percentage == null ? '—' : `${Number(detail.percentage).toFixed(1)}%`}</b></div>
              <div><small>Passed</small><b>{detail.passed == null ? '—' : detail.passed ? 'Yes' : 'No'}</b></div>
            </div>
          </>}
        </section>
      </div>}
    </section>
  )
}

function AdminModelTests({ token, page, onCreateTest, onRefresh, onNotice, reloadKey }) {
  const [result, setResult] = useState(null)
  const [status, setStatus] = useState(page === 'model-tests-drafts' ? 'draft' : 'all')
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [dialogTest, setDialogTest] = useState(null)
  const [dialogLoading, setDialogLoading] = useState(false)
  const [questionResult, setQuestionResult] = useState(null)
  const [questionSearchText, setQuestionSearchText] = useState('')
  const [questionSearch, setQuestionSearch] = useState('')
  const [questionPage, setQuestionPage] = useState(1)
  const [selectedQuestions, setSelectedQuestions] = useState({})
  const [actionBusy, setActionBusy] = useState(false)
  const [actionError, setActionError] = useState('')
  const [listReloadKey, setListReloadKey] = useState(0)
  const requestKey = `${status}:${search}:${currentPage}:${reloadKey}:${listReloadKey}`
  const questionRequestKey = dialogTest ? `${dialogTest.id}:${questionSearch}:${questionPage}` : ''

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ status, search, per_page: '15', page: String(currentPage) })
    apiRequest(`/admin/tests?${params.toString()}`, { token })
      .then((data) => {
        if (active) setResult({ key: requestKey, data })
      })
      .catch((error) => {
        if (active) setResult({ key: requestKey, error: error.message })
      })
    return () => { active = false }
  }, [requestKey, status, search, currentPage, reloadKey, token])

  useEffect(() => {
    if (!dialogTest) return undefined
    let active = true
    const params = new URLSearchParams({
      subject_id: String(dialogTest.subject.id),
      status: 'active',
      per_page: '10',
      page: String(questionPage),
    })
    if (questionSearch) params.set('search', questionSearch)
    apiRequest(`/admin/questions?${params.toString()}`, { token })
      .then((data) => {
        if (active) setQuestionResult({ key: questionRequestKey, data })
      })
      .catch((error) => {
        if (active) setQuestionResult({ key: questionRequestKey, error: error.message })
      })
    return () => { active = false }
  }, [dialogTest, questionRequestKey, questionSearch, questionPage, token])

  const currentResult = result?.key === requestKey ? result : null
  const tests = currentResult?.data?.data || []
  const meta = currentResult?.data?.meta
  const loading = !currentResult
  const error = currentResult?.error || ''
  const currentQuestionResult = questionResult?.key === questionRequestKey ? questionResult : null
  const questions = currentQuestionResult?.data?.data || []
  const questionMeta = currentQuestionResult?.data?.meta
  const questionLoading = Boolean(dialogTest) && !currentQuestionResult
  const selected = Object.values(selectedQuestions)
  const selectedIds = selected.map((question) => question.id)

  function submitSearch(event) {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchText.trim())
  }

  async function openQuestionManager(test) {
    setDialogLoading(true)
    setActionError('')
    setQuestionResult(null)
    setQuestionPage(1)
    setQuestionSearch('')
    setQuestionSearchText('')
    try {
      const response = await apiRequest(`/admin/tests/${test.id}`, { token })
      const assignedQuestions = response.test.questions || []
      setDialogTest(response.test)
      setSelectedQuestions(Object.fromEntries(assignedQuestions.map((question) => [question.id, question])))
    } catch (requestError) {
      setActionError(requestError.message)
    } finally {
      setDialogLoading(false)
    }
  }

  function toggleQuestion(question) {
    setSelectedQuestions((current) => {
      if (current[question.id]) {
        const next = { ...current }
        delete next[question.id]
        return next
      }
      if (Object.keys(current).length >= dialogTest.question_count) return current
      return { ...current, [question.id]: question }
    })
  }

  async function saveQuestions() {
    setActionError('')
    setActionBusy(true)
    try {
      await apiRequest(`/admin/tests/${dialogTest.id}/questions`, {
        method: 'PUT',
        token,
        body: JSON.stringify({ question_ids: selectedIds }),
      })
      setListReloadKey((value) => value + 1)
      onNotice('Draft test questions saved.')
      return true
    } catch (requestError) {
      setActionError(requestError.message)
      return false
    } finally {
      setActionBusy(false)
    }
  }

  async function publishTest() {
    if (selectedIds.length !== dialogTest.question_count) {
      setActionError(`Select exactly ${dialogTest.question_count} questions before publishing.`)
      return
    }
    if (!window.confirm(`Publish “${dialogTest.title}”? Students will be able to take this test, and it can no longer be edited.`)) return
    setActionError('')
    setActionBusy(true)
    try {
      await apiRequest(`/admin/tests/${dialogTest.id}/questions`, {
        method: 'PUT',
        token,
        body: JSON.stringify({ question_ids: selectedIds }),
      })
      const response = await apiRequest(`/admin/tests/${dialogTest.id}/publish`, { method: 'POST', token })
      setDialogTest(null)
      onNotice(response.message || 'Test published successfully.')
      onRefresh()
    } catch (requestError) {
      setActionError(requestError.message)
    } finally {
      setActionBusy(false)
    }
  }

  const number = (value) => Number(value || 0).toLocaleString()
  const statusLabel = status === 'draft' ? 'Draft tests' : status === 'published' ? 'Published tests' : 'All tests'

  return (
    <section className="admin-module-page admin-model-tests-page">
      <div className="admin-page-heading">
        <div><span className="eyebrow">LEARNING CONTENT</span><h1>Model tests</h1><p>Create test drafts, assign active questions, and publish the finished tests for students.</p></div>
        <button className="button button-primary" onClick={onCreateTest}><Icon name="arrow" size={17} /> Create model test</button>
      </div>
      <div className="admin-module-stats admin-users-stats">
        <span><b>{meta ? number(meta.total) : '—'}</b> matching tests</span>
        <span><b>{meta ? number(meta.published) : '—'}</b> published</span>
        <span><b>{meta ? number(meta.draft) : '—'}</b> drafts</span>
      </div>
      <section className="admin-panel admin-users-panel">
        <div className="admin-users-toolbar">
          <div><span className="eyebrow">TEST DIRECTORY</span><h2>{statusLabel}</h2></div>
          <div className="admin-question-tools">
            <label className="sr-only" htmlFor="model-test-status">Filter tests by status</label>
            <select id="model-test-status" value={status} onChange={(event) => { setCurrentPage(1); setStatus(event.target.value) }}><option value="all">All statuses</option><option value="draft">Draft</option><option value="published">Published</option></select>
            <form className="admin-user-search" onSubmit={submitSearch}>
              <label className="sr-only" htmlFor="model-test-search">Search tests</label>
              <input id="model-test-search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Title or test code" maxLength={100} />
              <button className="button button-outline" type="submit"><Icon name="search" size={16} /> Search</button>
            </form>
          </div>
        </div>
        {actionError && !dialogTest && <div className="form-error dashboard-error" role="alert">{actionError}</div>}
        {error && <div className="form-error dashboard-error" role="alert">{error}</div>}
        {loading ? <div className="dashboard-loading">Loading model tests…</div> : tests.length ? (
          <div className="admin-test-table">
            <div className="admin-table-row admin-model-test-row admin-table-head"><span>TEST</span><span>SUBJECT</span><span>QUESTIONS</span><span>STATUS</span><span>ACTIONS</span></div>
            {tests.map((test) => <div className="admin-table-row admin-model-test-row" key={test.id}>
              <span><b>{test.title}</b><small>{test.code} · {test.duration_minutes} min · {number(test.total_marks)} marks</small></span>
              <span>{test.subject?.name || '—'}</span>
              <span>{number(test.assigned_question_count)} / {number(test.question_count)}</span>
              <span><i className={test.status === 'published' ? 'published-dot' : 'draft-dot'} /> {test.status}</span>
              <span>{test.status === 'draft' ? <button className="admin-test-action" onClick={() => openQuestionManager(test)} disabled={dialogLoading}>Manage questions</button> : <span className="admin-test-readonly">Locked after publish</span>}</span>
            </div>)}
          </div>
        ) : !error ? <div className="admin-users-empty"><Icon name="book" size={22} /><b>No model tests found</b><span>Create a draft, or change the status filter or search.</span></div> : null}
        {meta && meta.last_page > 1 && <div className="admin-users-pagination"><span>Page {meta.current_page} of {meta.last_page} · {number(meta.total)} tests</span><div><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.max(1, value - 1))} disabled={meta.current_page <= 1 || loading}>Previous</button><button className="button button-outline" onClick={() => setCurrentPage((value) => Math.min(meta.last_page, value + 1))} disabled={meta.current_page >= meta.last_page || loading}>Next</button></div></div>}
      </section>
      <p className="admin-data-note"><Icon name="shield" size={15} /> Published tests are immutable so students see consistent questions and scoring throughout their attempts.</p>
      {dialogTest && <div className="auth-backdrop admin-create-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !actionBusy) setDialogTest(null) }}>
        <section className="auth-card admin-create-card admin-test-assignment-card" role="dialog" aria-modal="true" aria-labelledby="test-assignment-title">
          <button className="auth-close" onClick={() => setDialogTest(null)} aria-label="Close question assignment" disabled={actionBusy}><Icon name="close" /></button>
          <div className="auth-eyebrow">DRAFT TEST BUILDER</div>
          <h2 id="test-assignment-title">{dialogTest.title}</h2>
          <p className="auth-intro">{dialogTest.subject?.name} · Select {dialogTest.question_count} active questions. Each selected question receives an even share of the test marks.</p>
          {actionError && <div className="form-error" role="alert">{actionError}</div>}
          <div className="admin-assignment-summary"><b>{selected.length} / {dialogTest.question_count} selected</b><span>{number(dialogTest.total_marks)} total marks · {dialogTest.duration_minutes} min</span></div>
          {selected.length > 0 && <div className="admin-assigned-list"><b>Selected questions</b>{selected.map((question, index) => <div key={question.id}><span>{index + 1}. {question.question_text}</span><button type="button" onClick={() => toggleQuestion(question)} disabled={actionBusy} aria-label={`Remove selected question ${index + 1}`}>Remove</button></div>)}</div>}
          <form className="admin-assignment-search" onSubmit={(event) => { event.preventDefault(); setQuestionPage(1); setQuestionSearch(questionSearchText.trim()) }}>
            <label htmlFor="assignment-question-search">Find active questions in {dialogTest.subject?.name}</label>
            <div><input id="assignment-question-search" value={questionSearchText} onChange={(event) => setQuestionSearchText(event.target.value)} placeholder="Search question text" maxLength={200} /><button className="button button-outline" type="submit">Search</button></div>
          </form>
          {currentQuestionResult?.error && <div className="form-error" role="alert">{currentQuestionResult.error}</div>}
          {questionLoading ? <div className="dashboard-loading">Loading active questions…</div> : questions.length ? <div className="admin-assignment-questions">{questions.map((question) => <label key={question.id} className={selectedQuestions[question.id] ? 'is-selected' : ''}><input type="checkbox" checked={Boolean(selectedQuestions[question.id])} onChange={() => toggleQuestion(question)} disabled={actionBusy || (!selectedQuestions[question.id] && selected.length >= dialogTest.question_count)} /><span><b>{question.question_text}</b><small>{question.difficulty} · {question.options.length} options</small></span></label>)}</div> : !currentQuestionResult?.error ? <p className="empty-state">No active questions match this search. Add or activate questions for this subject first.</p> : null}
          {questionMeta && questionMeta.last_page > 1 && <div className="admin-users-pagination"><span>Question page {questionMeta.current_page} of {questionMeta.last_page}</span><div><button className="button button-outline" onClick={() => setQuestionPage((value) => Math.max(1, value - 1))} disabled={questionMeta.current_page <= 1 || questionLoading || actionBusy}>Previous</button><button className="button button-outline" onClick={() => setQuestionPage((value) => Math.min(questionMeta.last_page, value + 1))} disabled={questionMeta.current_page >= questionMeta.last_page || questionLoading || actionBusy}>Next</button></div></div>}
          <div className="admin-assignment-actions"><button className="button button-outline" onClick={saveQuestions} disabled={actionBusy}>{actionBusy ? 'Saving…' : 'Save question set'}</button><button className="button button-primary" onClick={publishTest} disabled={actionBusy}>{actionBusy ? 'Publishing…' : 'Publish test'} <Icon name="arrow" size={16} /></button></div>
        </section>
      </div>}
    </section>
  )
}

function AdminDashboard({ user, token, onLogout, busy, sessionError, navigateTo }) {
  const [metrics, setMetrics] = useState(null)
  const [activity, setActivity] = useState([])
  const [subjectList, setSubjectList] = useState([])
  const [tests, setTests] = useState([])
  const [activePage, setActivePage] = useState('dashboard')
  const [expandedMenu, setExpandedMenu] = useState(null)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [createOpen, setCreateOpen] = useState(false)
  const [createBusy, setCreateBusy] = useState(false)
  const [createError, setCreateError] = useState('')
  const [notice, setNotice] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const adminName = user.name.trim().split(/\s+/)[0]
  const isAdmin = user.role === 'admin'

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest('/admin/dashboard', { token }),
      apiRequest('/subjects?per_page=100', { token }),
      apiRequest('/tests?per_page=100', { token }),
    ]).then(([dashboardData, subjectData, testData]) => {
      if (!active) return
      setMetrics(dashboardData.metrics)
      setActivity(dashboardData.exam_activity || [])
      setSubjectList(subjectData.data || [])
      setTests(testData.data || [])
    }).catch((requestError) => {
      if (active) setLoadError(requestError.message)
    }).finally(() => {
      if (active) setLoading(false)
    })

    return () => { active = false }
  }, [reloadKey, token])

  function selectPage(id) {
    setActivePage(id)
    setSidebarOpen(false)
    setNotice('')
    if (id === 'dashboard') window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function activateMenuItem(item) {
    if (item.action === 'create-test') {
      setCreateError('')
      setCreateOpen(true)
      setSidebarOpen(false)
      return
    }
    if (item.action === 'create-question') {
      selectPage('question-bank-create')
      return
    }

    selectPage(item.page || item.id)
  }

  async function createTest(event) {
    event.preventDefault()
    setCreateError('')
    setCreateBusy(true)
    const fields = new FormData(event.currentTarget)
    const payload = {
      title: fields.get('title').trim(),
      subject_id: Number(fields.get('subject_id')),
      duration_minutes: Number(fields.get('duration_minutes')),
      question_count: Number(fields.get('question_count')),
      total_marks: Number(fields.get('total_marks')),
      passing_score: Number(fields.get('passing_score')),
      negative_marking: Number(fields.get('negative_marking')),
      is_negative_marking_enabled: fields.get('is_negative_marking_enabled') === 'on',
      is_premium: fields.get('is_premium') === 'on',
      description: fields.get('description').trim() || null,
    }

    try {
      const result = await apiRequest('/admin/tests', {
        method: 'POST',
        token,
        body: JSON.stringify(payload),
      })
      setCreateOpen(false)
      setActivePage('model-tests')
      setNotice(result.message || 'Draft model test created.')
      setLoadError('')
      setLoading(true)
      setReloadKey((value) => value + 1)
    } catch (requestError) {
      setCreateError(requestError.message)
    } finally {
      setCreateBusy(false)
    }
  }

  const number = (value) => value === null || value === undefined ? '—' : Number(value).toLocaleString()
  const metricCards = [
    { label: 'Total students', value: metrics?.students, icon: 'users', tone: 'mint', note: 'Registered student accounts' },
    { label: 'New students today', value: metrics?.new_students_today, icon: 'users', tone: 'peach', note: 'Joined since midnight' },
    { label: 'Active students', value: metrics?.active_students, icon: 'heart', tone: 'lavender', note: 'Active student accounts' },
    { label: 'Questions', value: metrics?.questions, icon: 'question', tone: 'peach', note: 'In the question bank' },
    { label: 'Total tests', value: metrics?.tests, icon: 'book', tone: 'mint', note: `${number(metrics?.draft_tests)} drafts` },
    { label: 'Published tests', value: metrics?.published_tests, icon: 'book', tone: 'yellow', note: `${number(metrics?.draft_tests)} drafts` },
    { label: 'Exams today', value: metrics?.attempts_today, icon: 'clock', tone: 'lavender', note: `${number(metrics?.finished_attempts)} completed all time` },
    { label: 'Completed attempts', value: metrics?.finished_attempts, icon: 'check', tone: 'mint', note: 'Submitted or expired exams' },
    { label: 'Average score', value: metrics?.average_score == null ? '—' : `${Number(metrics.average_score).toFixed(1)}%`, icon: 'chart', tone: 'mint', note: metrics?.average_score == null ? 'No finished attempts yet' : 'Finished attempts' },
    { label: 'Completion rate', value: metrics?.completion_rate == null ? '—' : `${Number(metrics.completion_rate).toFixed(1)}%`, icon: 'check', tone: 'peach', note: metrics?.completion_rate == null ? 'No exam attempts yet' : 'Submitted or expired attempts' },
    { label: 'Premium users', value: null, icon: 'star', tone: 'lavender', note: 'Subscriptions module not connected' },
    { label: 'Revenue', value: null, icon: 'wallet', tone: 'yellow', note: 'Payments module not connected' },
  ]
  const maxAttempts = Math.max(1, ...activity.map((day) => day.attempts))
  const selectedItem = adminMenu.flatMap((group) => group.items.flatMap((item) => [
    item,
    ...(item.children || []).map((child) => typeof child === 'string'
      ? { id: `${item.id}-${child.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`, label: child, parent: item.id, soon: true }
      : { ...child, parent: item.id }),
  ])).find((item) => item.id === activePage)

  return (
    <main className="admin-app">
      <button className="admin-mobile-menu" onClick={() => setSidebarOpen(!sidebarOpen)} aria-expanded={sidebarOpen}>
        <Icon name={sidebarOpen ? 'close' : 'menu'} /> <span>{sidebarOpen ? 'Close menu' : 'Admin menu'}</span>
      </button>
      {sidebarOpen && <button className="admin-sidebar-backdrop" onClick={() => setSidebarOpen(false)} aria-label="Close admin navigation" />}
      <aside className={`admin-sidebar${sidebarOpen ? ' admin-sidebar-open' : ''}`}>
        <button className="admin-brand" onClick={() => selectPage('dashboard')}><span className="brand-mark"><Icon name="book" size={21} /></span><span>NurseExam<span>247</span><small>ADMIN CONSOLE</small></span></button>
        <div className="admin-sidebar-scroll">
          {adminMenu.map((group) => (
            <section className="admin-nav-group" key={group.heading}>
              <h2>{group.heading}</h2>
              {group.items.map((item) => (
                <div key={item.id}>
                  <button
                    className={`admin-nav-item${activePage === item.id ? ' admin-nav-active' : ''}${item.soon || (item.adminOnly && !isAdmin) ? ' admin-nav-soon' : ''}`}
                    onClick={() => {
                      if (item.children) setExpandedMenu((current) => current === item.id ? null : item.id)
                      if (!item.adminOnly || isAdmin) activateMenuItem(item)
                    }}
                    title={item.soon ? 'This management module is not implemented yet.' : item.adminOnly && !isAdmin ? 'User management is restricted to administrators.' : item.label}
                  >
                    <Icon name={item.icon} size={17} /><span>{item.label}</span>
                    {item.soon ? <small>SOON</small> : item.id === 'model-tests' && metrics ? <small>{number(metrics.tests)}</small> : null}
                    {item.children && <span className="admin-nav-caret">{expandedMenu === item.id ? '−' : '+'}</span>}
                  </button>
                  {item.children && expandedMenu === item.id && <div className="admin-subnav">{item.children.map((child) => {
                    const subitem = typeof child === 'string'
                      ? { id: `${item.id}-${child.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`, label: child, page: `${item.id}-${child.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`, soon: true }
                      : child
                    return <button className={`admin-subnav-item${activePage === (subitem.page || subitem.id) ? ' admin-subnav-active' : ''}`} key={subitem.id} onClick={() => activateMenuItem(subitem)} disabled={item.adminOnly && !isAdmin} title={item.adminOnly && !isAdmin ? 'User management is restricted to administrators.' : undefined}>{subitem.label}{subitem.soon && <small>SOON</small>}{item.adminOnly && !isAdmin && <small>ADMIN</small>}</button>
                  })}</div>}
                </div>
              ))}
            </section>
          ))}
        </div>
        <div className="admin-sidebar-profile">
          <span className="profile-avatar">{adminName.charAt(0).toUpperCase()}</span>
          <span><b>{user.name}</b><small>{user.role}</small></span>
          <button onClick={onLogout} disabled={busy} aria-label="Sign out"><Icon name="logout" size={17} /></button>
        </div>
      </aside>
      <section className="admin-main">
        <header className="admin-topbar">
          <div><span className="admin-breadcrumb">NURSEEXAM247 <span>/</span> {selectedItem?.label.toUpperCase() || 'DASHBOARD'}</span><p>Platform management center</p></div>
          <div className="admin-top-actions"><span className="admin-role-chip"><Icon name="shield" size={15} /> {user.role}</span><button className="admin-view-site" onClick={() => navigateTo('/')}>View site <Icon name="arrow" size={15} /></button></div>
        </header>
        <div className="admin-content">
          {sessionError && <div className="form-error dashboard-error" role="alert">{sessionError}</div>}
          {loadError && <div className="form-error dashboard-error" role="alert">{loadError}</div>}
          {notice && <div className="admin-success" role="status"><Icon name="check" size={17} /> {notice}<button onClick={() => setNotice('')} aria-label="Dismiss message"><Icon name="close" size={15} /></button></div>}
          {activePage === 'dashboard' ? (
            <>
              <div className="admin-page-heading"><div><span className="eyebrow">OVERVIEW</span><h1>Good day, {adminName} <span>✳</span></h1><p>Here’s what’s happening across your learning platform.</p></div><button className="button button-primary" onClick={() => { setCreateError(''); setCreateOpen(true) }}><Icon name="arrow" size={17} /> Create model test</button></div>
              <div className="admin-quick-actions">
                <span>QUICK ACTIONS</span>
                <button onClick={() => { setActivePage('question-bank-create'); setSidebarOpen(false) }}><Icon name="question" size={16} /> Add question</button>
                <button onClick={() => { setCreateError(''); setCreateOpen(true) }}><Icon name="book" size={16} /> Create model test</button>
                <button onClick={() => { setActivePage('subjects'); setSidebarOpen(false) }}><Icon name="book" size={16} /> Manage subjects</button>
                <button onClick={() => { setActivePage('coupons'); setSidebarOpen(false) }}><Icon name="tag" size={16} /> Create coupon <small>SOON</small></button>
                <button onClick={() => { setActivePage('notifications'); setSidebarOpen(false) }}><Icon name="bell" size={16} /> Send notification <small>SOON</small></button>
              </div>
              <div className="admin-kpi-grid">
                {metricCards.map((card) => <article className="admin-kpi-card" key={card.label}><div className={`admin-kpi-icon ${card.tone}`}><Icon name={card.icon} size={19} /></div><div className="admin-kpi-label">{card.label}</div><div className={`admin-kpi-value${card.value === null || card.value === undefined ? ' kpi-unavailable' : ''}`}>{loading ? '…' : card.value === null || card.value === undefined ? 'Not tracked' : typeof card.value === 'number' ? number(card.value) : card.value}</div><div className="admin-kpi-note">{card.note}</div></article>)}
              </div>
              <div className="admin-dashboard-panels">
                <section className="admin-panel admin-activity-panel">
                  <div className="admin-panel-heading"><div><span className="eyebrow">LAST 7 DAYS</span><h2>Exam attempts</h2></div><span className="admin-panel-icon"><Icon name="chart" /></span></div>
                  {loading ? <div className="dashboard-loading">Loading activity…</div> : <div className="activity-chart">{activity.map((day) => <div className="activity-column" key={day.date}><span className="activity-count">{day.attempts || ''}</span><div className="activity-bar-track"><i style={{ height: `${Math.max(day.attempts > 0 ? 10 : 3, (day.attempts / maxAttempts) * 100)}%` }} /></div><span className="activity-day">{day.day}</span></div>)}</div>}
                  <div className="activity-caption"><span><i /> Attempts started</span><span>{number(metrics?.attempts_today)} today</span></div>
                </section>
                <section className="admin-panel admin-status-panel">
                  <div className="admin-panel-heading"><div><span className="eyebrow">CONTENT SNAPSHOT</span><h2>Learning catalogue</h2></div><span className="admin-panel-icon"><Icon name="book" /></span></div>
                  <div className="catalogue-row"><span className="catalogue-marker mint-marker"><Icon name="book" size={16} /></span><span><b>Active subjects</b><small>Ready for study</small></span><strong>{loading ? '…' : number(metrics?.active_subjects)}</strong></div>
                  <div className="catalogue-row"><span className="catalogue-marker peach-marker"><Icon name="check" size={16} /></span><span><b>Published model tests</b><small>Visible to students</small></span><strong>{loading ? '…' : number(metrics?.published_tests)}</strong></div>
                  <div className="catalogue-row"><span className="catalogue-marker yellow-marker"><Icon name="question" size={16} /></span><span><b>Question bank</b><small>Questions available</small></span><strong>{loading ? '…' : number(metrics?.questions)}</strong></div>
                </section>
              </div>
              <section className="admin-panel admin-recent-panel" id="admin-tests">
                <div className="admin-panel-heading"><div><span className="eyebrow">PUBLIC CATALOGUE</span><h2>Published model tests</h2></div><button className="admin-text-button" onClick={() => selectPage('model-tests')}>View all <Icon name="arrow" size={15} /></button></div>
                {loading ? <div className="dashboard-loading">Loading published tests…</div> : tests.length ? <div className="admin-test-table"><div className="admin-table-row admin-table-head"><span>TEST</span><span>SUBJECT</span><span>QUESTIONS</span><span>STATUS</span></div>{tests.slice(0, 5).map((test) => <div className="admin-table-row" key={test.id}><span><b>{test.title}</b><small>{test.code}</small></span><span>{test.subject?.name || '—'}</span><span>{number(test.question_count)}</span><span><i className="published-dot" /> Published</span></div>)}</div> : <p className="empty-state">No published tests yet. Create a draft model test to begin building the catalogue.</p>}
              </section>
              <p className="admin-data-note"><Icon name="shield" size={15} /> Revenue and premium subscriptions are not tracked. Referral invitations are attributed, but rewards await a purchase qualification policy.</p>
            </>
          ) : activePage === 'results-all' || activePage === 'results-analytics' ? (
            <AdminResults token={token} page={activePage} />
          ) : activePage === 'exams-live' || activePage === 'exams-completed' || activePage === 'exams-attempts' ? (
            <AdminExams token={token} page={activePage} />
          ) : activePage === 'model-tests' || activePage === 'model-tests-drafts' ? (
            <AdminModelTests token={token} page={activePage} onCreateTest={() => { setCreateError(''); setCreateOpen(true) }} onRefresh={() => { setLoadError(''); setLoading(true); setReloadKey((value) => value + 1) }} onNotice={setNotice} reloadKey={reloadKey} />
          ) : activePage === 'subjects' ? (
            <AdminSubjects token={token} onNotice={setNotice} />
          ) : activePage === 'question-bank' || activePage === 'question-bank-create' ? (
            <AdminQuestionBank token={token} page={activePage} onNotice={setNotice} />
          ) : activePage === 'users' || activePage.startsWith('users-') ? (
            <AdminUsers token={token} page={activePage} onNotice={setNotice} />
          ) : activePage === 'gamification' ? (
            <AdminGamificationRules token={token} isAdmin={isAdmin} />
          ) : activePage === 'gamification-progress' ? (
            <GamificationPanel token={token} />
          ) : activePage === 'leaderboard' ? (
            <LeaderboardPanel token={token} />
          ) : activePage === 'referrals' ? (
            <ReferralPanel token={token} />
          ) : (
            <section className="admin-coming-page"><span className="admin-coming-icon"><Icon name={selectedItem?.icon || 'settings'} size={27} /></span><span className="eyebrow">PLATFORM MODULE</span><h1>{selectedItem?.label || 'Management'}</h1><p>This section is part of the platform management plan, but its management API and interface are not implemented yet.</p><div className="admin-coming-status"><Icon name="clock" size={16} /> Coming soon — no data or actions are available here yet.</div><button className="button button-outline" onClick={() => selectPage('dashboard')}>Back to dashboard <Icon name="arrow" size={15} /></button></section>
          )}
        </div>
      </section>
      {createOpen && <div className="auth-backdrop admin-create-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !createBusy) setCreateOpen(false) }}><section className="auth-card admin-create-card" role="dialog" aria-modal="true" aria-labelledby="create-test-title"><button className="auth-close" onClick={() => setCreateOpen(false)} aria-label="Close create test form"><Icon name="close" /></button><div className="auth-eyebrow">MODEL TEST BUILDER</div><h2 id="create-test-title">Create a test draft</h2><p className="auth-intro">Set the exam basics, then assign questions and publish from the test directory.</p>{createError && <div className="form-error" role="alert">{createError}</div>}<form className="auth-form admin-test-form" onSubmit={createTest}><label>Test title<input name="title" required maxLength="255" placeholder="e.g. Nursing Practice Set 01" /></label><label>Subject<select name="subject_id" required defaultValue=""><option value="" disabled>Select a subject</option>{subjectList.map((subject) => <option value={subject.id} key={subject.id}>{subject.name}</option>)}</select></label><div className="admin-form-row"><label>Duration (minutes)<input name="duration_minutes" type="number" min="1" max="600" defaultValue="30" required /></label><label>Question count<input name="question_count" type="number" min="1" max="500" defaultValue="20" required /></label></div><div className="admin-form-row"><label>Total marks<input name="total_marks" type="number" min="0.01" step="0.01" defaultValue="20" required /></label><label>Passing score<input name="passing_score" type="number" min="0" step="0.01" defaultValue="10" required /></label></div><div className="admin-form-row"><label>Wrong answer penalty<input name="negative_marking" type="number" min="0" step="0.01" defaultValue="0.25" /></label><label className="admin-checkbox-label"><input name="is_negative_marking_enabled" type="checkbox" defaultChecked /> Enable negative marking</label></div><label>Description (optional)<input name="description" maxLength="10000" placeholder="Short description for this test" /></label><label className="admin-checkbox-label"><input name="is_premium" type="checkbox" /> Premium test</label><button className="button button-primary auth-submit" disabled={createBusy || subjectList.length === 0}>{createBusy ? 'Creating draft…' : 'Create test draft'} {!createBusy && <Icon name="arrow" size={17} />}</button>{subjectList.length === 0 && <span className="admin-data-note">An active subject is required before creating a test.</span>}</form></section></div>}
    </main>
  )
}

function LeaderboardPanel({ token }) {
  const [period, setPeriod] = useState('daily')
  const [result, setResult] = useState(null)
  const requestKey = `${period}:${token}`

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest(`/leaderboards?period=${period}&per_page=10`, { token }),
      apiRequest(`/leaderboards/${period}/me`, { token }),
    ]).then(([leaders, ownPosition]) => {
      if (active) setResult({ key: requestKey, leaders, ownPosition })
    }).catch((error) => {
      if (active) setResult({ key: requestKey, error: error.message })
    })
    return () => { active = false }
  }, [period, token, requestKey])

  const current = result?.key === requestKey ? result : null
  const loading = !current
  const leaders = current?.leaders?.data || []
  const position = current?.ownPosition?.position

  return (
    <section className="dashboard-section dashboard-leaderboard">
      <div className="dashboard-section-heading"><div><span className="eyebrow">STUDY COMMUNITY</span><h2>Leaderboard</h2></div><span className="dashboard-count">{current?.leaders?.meta?.timezone || 'Asia/Dhaka'}</span></div>
      <div className="leaderboard-period-tabs" role="group" aria-label="Leaderboard period">
        {['daily', 'weekly', 'monthly', 'overall'].map((item) => <button type="button" key={item} className={period === item ? 'is-active' : ''} onClick={() => setPeriod(item)}>{item}</button>)}
      </div>
      {current?.error && <div className="form-error dashboard-error" role="alert">{current.error}</div>}
      {current && !current.error && <div className="leaderboard-my-rank" role="status">
        {current.ownPosition.leaderboard_opt_in
          ? position?.rank ? <>Your rank: <b>#{position.rank}</b> · {Number(position.score).toFixed(2)} points across {position.eligible_test_count} tests</> : 'You are not ranked for this period yet. Complete a test to join the board.'
          : <>Your leaderboard profile is hidden. Opt in from <b>Edit profile</b> to appear in rankings.</>}
      </div>}
      {loading ? <div className="dashboard-loading">Loading leaderboard…</div> : leaders.length ? <div className="leaderboard-list">
        {leaders.map((leader) => <div className="leaderboard-row" key={leader.rank}>
          <span className={`leaderboard-rank${leader.rank <= 3 ? ' top-rank' : ''}`}>{leader.rank}</span>
          {leader.avatar_url ? <img src={leader.avatar_url} alt="" /> : <span className="leaderboard-avatar">{leader.display_name.charAt(0).toUpperCase()}</span>}
          <b>{leader.display_name}</b>
          <span>{Number(leader.score).toFixed(2)} <small>pts</small></span>
          <small>{leader.eligible_test_count} tests</small>
        </div>)}
      </div> : !current?.error ? <p className="empty-state">No opted-in students have completed a test in this period yet.</p> : null}
    </section>
  )
}

function GamificationPanel({ token }) {
  const [result, setResult] = useState(null)

  useEffect(() => {
    let active = true
    apiRequest('/gamification/me', { token })
      .then((response) => { if (active) setResult({ data: response.gamification }) })
      .catch((error) => { if (active) setResult({ error: error.message }) })
    return () => { active = false }
  }, [token])

  const gamification = result?.data
  const nextLevel = gamification?.next_level
  const levelProgress = nextLevel && gamification?.level
    ? Math.min(100, ((gamification.xp_total - gamification.level.xp_required) / (nextLevel.xp_required - gamification.level.xp_required)) * 100)
    : 100

  return (
    <section className="dashboard-section gamification-panel">
      <div className="dashboard-section-heading"><div><span className="eyebrow">YOUR LEARNING REWARDS</span><h2>Progress &amp; badges</h2></div><span className="dashboard-count">{gamification ? `${gamification.completed_test_count} tests completed` : 'Gamification'}</span></div>
      {result?.error && <div className="form-error dashboard-error" role="alert">{result.error}</div>}
      {!result ? <div className="dashboard-loading">Loading your rewards…</div> : gamification ? <>
        <div className="gamification-stats">
          <div><span>LEVEL {gamification.level?.number || 1}</span><b>{gamification.level?.title || 'New Learner'}</b></div>
          <div><span>EXPERIENCE</span><b>{gamification.xp_total.toLocaleString()} XP</b></div>
          <div><span>POINTS</span><b>{gamification.points_balance.toLocaleString()}</b></div>
        </div>
        <div className="gamification-progress">
          <div><span>{nextLevel ? `${nextLevel.xp_remaining} XP to ${nextLevel.title}` : 'Highest level reached'}</span><b>{Math.round(levelProgress)}%</b></div>
          <div className="gamification-progress-track"><i style={{ width: `${levelProgress}%` }} /></div>
        </div>
        <div className="gamification-badges">
          {gamification.badges.map((badge) => <article className={`gamification-badge${badge.earned ? ' is-earned' : ''}`} key={badge.code}>
            <span><Icon name={badge.icon} size={18} /></span>
            <div><b>{badge.name}</b><small>{badge.earned ? 'Earned' : badge.description}</small></div>
          </article>)}
        </div>
        {gamification.recent_point_transactions.length > 0 && <div className="gamification-latest-reward"><Icon name="spark" size={15} /> Latest reward: <b>{gamification.recent_point_transactions[0].amount > 0 ? '+' : ''}{gamification.recent_point_transactions[0].amount} points</b> · {gamification.recent_point_transactions[0].description}</div>}
      </> : null}
    </section>
  )
}

function ReferralPanel({ token }) {
  const [result, setResult] = useState(null)
  const [copyError, setCopyError] = useState('')
  const [copied, setCopied] = useState(false)
  const [redeemBusy, setRedeemBusy] = useState(false)
  const [redeemError, setRedeemError] = useState('')
  const [redeemNotice, setRedeemNotice] = useState('')

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest('/referrals/me', { token }),
      apiRequest('/referrals/me/invites?per_page=5', { token }),
    ]).then(([referralResponse, inviteResponse]) => {
      if (active) setResult({ referral: referralResponse.referral, invites: inviteResponse })
    }).catch((error) => {
      if (active) setResult({ error: error.message })
    })
    return () => { active = false }
  }, [token])

  async function copyInviteLink() {
    setCopyError('')
    setCopied(false)
    const url = new URL('/', window.location.origin)
    url.searchParams.set('ref', result.referral.code)
    try {
      await navigator.clipboard.writeText(url.toString())
      setCopied(true)
    } catch {
      setCopyError('Could not copy automatically. Select and copy the invite link below.')
    }
  }

  async function redeemCode(event) {
    event.preventDefault()
    setRedeemError('')
    setRedeemNotice('')
    setRedeemBusy(true)
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    try {
      await apiRequest('/referrals/redeem', {
        method: 'POST',
        token,
        body: JSON.stringify({ code: form.get('code').trim() }),
      })
      formElement.reset()
      setRedeemNotice('Referral code linked. It is pending qualification; account creation does not issue a reward.')
      const [referralResponse, inviteResponse] = await Promise.all([
        apiRequest('/referrals/me', { token }),
        apiRequest('/referrals/me/invites?per_page=5', { token }),
      ])
      setResult({ referral: referralResponse.referral, invites: inviteResponse })
    } catch (error) {
      setRedeemError(error.message)
    } finally {
      setRedeemBusy(false)
    }
  }

  return (
    <section className="dashboard-section referral-panel">
      <div className="dashboard-section-heading"><div><span className="eyebrow">GROW YOUR STUDY COMMUNITY</span><h2>Invite a friend</h2></div><span className="dashboard-count">{result?.referral ? `${result.referral.counts.total} joined` : 'Referrals'}</span></div>
      {result?.error && <div className="form-error dashboard-error" role="alert">{result.error}</div>}
      {!result ? <div className="dashboard-loading">Preparing your invite link…</div> : result.referral ? <>
        <div className="referral-share-card">
          <div><span>Your referral code</span><b>{result.referral.code}</b></div>
          <button type="button" className="button button-primary" onClick={copyInviteLink}><Icon name="users" size={15} /> {copied ? 'Copied!' : 'Copy invite link'}</button>
          <label>Invite link<input readOnly value={`${window.location.origin}/?ref=${encodeURIComponent(result.referral.code)}`} onFocus={(event) => event.target.select()} /></label>
        </div>
        {copyError && <div className="form-error dashboard-error" role="alert">{copyError}</div>}
        <div className="referral-stats">
          <span><b>{result.referral.counts.total}</b> invited</span>
          <span><b>{result.referral.counts.pending}</b> pending</span>
          <span><b>{result.referral.counts.qualified}</b> qualified</span>
        </div>
        {result.referral.attribution ? <div className="referral-attribution" role="status">You joined with a referral code. Status: <b>{result.referral.attribution.status}</b></div> : result.referral.can_redeem && <form className="referral-redeem-form" onSubmit={redeemCode}>
          <label>Have a friend’s code? Enter it within 24 hours of joining and before starting a test.<input name="code" required maxLength="24" pattern="[A-Za-z0-9]+" autoComplete="off" placeholder="Referral code" /></label>
          <button type="submit" className="button button-outline" disabled={redeemBusy}>{redeemBusy ? 'Linking…' : 'Apply code'}</button>
        </form>}
        {redeemError && <div className="form-error dashboard-error" role="alert">{redeemError}</div>}
        {redeemNotice && <div className="referral-attribution" role="status">{redeemNotice}</div>}
        <div className="referral-invite-list">
          <div className="referral-invite-heading"><b>Recent invitations</b><small>Invitee details are kept private</small></div>
          {result.invites.data.length ? result.invites.data.map((invite, index) => <div className="referral-invite-row" key={`${invite.referred_at}-${index}`}>
            <span>Invitation {result.invites.meta.total - index}</span><b className={`referral-status ${invite.status}`}>{invite.status}</b>
          </div>) : <p className="empty-state">No one has joined with your code yet. Share your link to invite a study partner.</p>}
        </div>
        <p className="referral-policy-note">Referral rewards are not available yet. Your invites are recorded, but no points or money are granted for account creation.</p>
      </> : null}
    </section>
  )
}

function AdminGamificationRules({ token, isAdmin }) {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('/admin/gamification/rules', { token })
      .then((response) => { if (active) setData(response) })
      .catch((requestError) => { if (active) setError(requestError.message) })
    return () => { active = false }
  }, [token])

  async function updateRules(event) {
    event.preventDefault()
    setError('')
    setNotice('')
    setBusy(true)
    const fields = new FormData(event.currentTarget)
    try {
      const response = await apiRequest('/admin/gamification/rules', {
        method: 'PATCH',
        token,
        body: JSON.stringify({
          points_per_test: Number(fields.get('points_per_test')),
          xp_per_test: Number(fields.get('xp_per_test')),
        }),
      })
      setData((current) => ({ ...current, rules: response.rules }))
      setNotice(response.message)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="admin-module-page admin-gamification-page">
      <div className="admin-page-heading"><div><span className="eyebrow">GAMIFICATION</span><h1>Reward rules</h1><p>Configure rewards for the first valid completion of each published test. Existing ledger entries are never rewritten.</p></div></div>
      {error && <div className="form-error dashboard-error" role="alert">{error}</div>}
      {notice && <div className="admin-success" role="status"><Icon name="check" size={17} /> {notice}</div>}
      {!data ? <div className="dashboard-loading">Loading reward rules…</div> : <>
        <section className="admin-panel gamification-rule-panel">
          <div className="admin-panel-heading"><div><span className="eyebrow">FIRST COMPLETION ONLY</span><h2>Test completion rewards</h2></div><span className="admin-panel-icon"><Icon name="spark" /></span></div>
          {!isAdmin ? <p className="empty-state">Only an administrator can change reward rules.</p> : <form className="auth-form admin-gamification-form" onSubmit={updateRules}>
            <div className="admin-form-row">
              <label>Points per test<input name="points_per_test" type="number" min="1" max="10000" defaultValue={data.rules.points_per_test} required /></label>
              <label>XP per test<input name="xp_per_test" type="number" min="1" max="10000" defaultValue={data.rules.xp_per_test} required /></label>
            </div>
            <button className="button button-primary auth-submit" type="submit" disabled={busy}>{busy ? 'Saving rules…' : 'Save reward rules'} {!busy && <Icon name="arrow" size={17} />}</button>
          </form>}
          <p className="admin-data-note">XP cannot be spent. Points and XP are recorded in separate append-only ledgers. Re-submitting or retrying the same test never earns duplicate rewards.</p>
        </section>
        <div className="admin-gamification-grid">
          <section className="admin-panel"><div className="admin-panel-heading"><div><span className="eyebrow">VERSIONED THRESHOLDS</span><h2>Levels</h2></div></div>
            {data.level_rules.map((level) => <div className="gamification-admin-row" key={level.level}><b>Level {level.level} · {level.title}</b><span>{Number(level.xp_required).toLocaleString()} XP</span></div>)}
          </section>
          <section className="admin-panel"><div className="admin-panel-heading"><div><span className="eyebrow">AUTOMATIC AWARDS</span><h2>Badges</h2></div></div>
            {data.badges.map((badge) => <div className="gamification-admin-row" key={badge.code}><b>{badge.name}</b><span>{badge.description}</span></div>)}
          </section>
        </div>
      </>}
    </section>
  )
}
/**
 * Router entry point for every /admin/* route. All console pages share the
 * dashboard shell, which renders the selected section itself.
 */
export default function AdminConsole(props) {
  return <AdminDashboard {...props} />
}
