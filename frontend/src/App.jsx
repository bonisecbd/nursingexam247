/**
 * Application shell: route table, guards and page chrome.
 *
 * Routing is handled by the tiny history router in `lib/router.jsx`, so every
 * path below is matched here once and rendered with the layout it needs
 * (site header/footer, bare exam runner, or the admin console).
 */
import { useEffect, useState } from 'react'

import { AuthProvider, useAuth } from './lib/auth'
import { RouterProvider, useLocation, useNavigate } from './lib/router'
import Icon from './components/Icon'
import { SiteFooter, SiteHeader } from './components/Layout'
import { Loading } from './components/ui'

import Landing from './pages/Landing'
import Login from './pages/Login'
import Register from './pages/Register'
import ForgotPassword from './pages/ForgotPassword'
import Dashboard from './pages/Dashboard'
import Tests from './pages/Tests'
import Exam from './pages/Exam'
import Result from './pages/Result'
import Solutions from './pages/Solutions'
import History from './pages/History'
import Profile from './pages/Profile'
import AdminConsole from './pages/admin/AdminConsole'

const STAFF_ROLES = ['admin', 'editor']

const TITLES = {
  landing: 'NurseExam247 — নার্সিং পরীক্ষার পূর্ণাঙ্গ প্রস্তুতি',
  login: 'Log in · NurseExam247',
  register: 'Create account · NurseExam247',
  'forgot-password': 'Reset password · NurseExam247',
  dashboard: 'Dashboard · NurseExam247',
  tests: 'Model tests · NurseExam247',
  exam: 'Exam in progress · NurseExam247',
  result: 'Result · NurseExam247',
  solutions: 'Solutions · NurseExam247',
  history: 'Attempt history · NurseExam247',
  profile: 'Profile · NurseExam247',
  admin: 'Admin console · NurseExam247',
  notfound: 'Page not found · NurseExam247',
}

/**
 * Maps a pathname to a route descriptor:
 *   { name, auth: 'required' | 'guests' | 'staff', chrome: 'site' | 'none', params }
 * Returns `null` for unknown paths so the 404 screen can render.
 */
function resolveRoute(pathname) {
  const path = pathname.replace(/\/+$/, '') || '/'
  if (path === '/') return { name: 'landing', chrome: 'site' }
  if (path.startsWith('/admin')) return { name: 'admin', auth: 'staff', chrome: 'none' }

  const parts = path.split('/').filter(Boolean)
  if (parts.length !== 1 && parts.length !== 2) return null

  const [head, param] = parts
  const single = {
    login: { name: 'login', auth: 'guests' },
    register: { name: 'register', auth: 'guests' },
    'forgot-password': { name: 'forgot-password', auth: 'guests' },
    dashboard: { name: 'dashboard', auth: 'required' },
    tests: { name: 'tests', auth: 'required' },
    history: { name: 'history', auth: 'required' },
    profile: { name: 'profile', auth: 'required' },
  }

  if (parts.length === 1 && single[head]) return { ...single[head], chrome: 'site' }

  if (parts.length === 2 && param) {
    if (head === 'exam') return { name: 'exam', auth: 'required', chrome: 'none', testId: param }
    if (head === 'result') return { name: 'result', auth: 'required', chrome: 'site', attemptId: param }
    if (head === 'solutions') return { name: 'solutions', auth: 'required', chrome: 'site', attemptId: param }
  }

  return null
}

/** Replaces the current history entry once a guard has decided where to go. */
function Redirect({ to }) {
  const navigate = useNavigate()
  useEffect(() => {
    navigate(to, { replace: true })
  }, [navigate, to])
  return (
    <div className="route-blocked">
      <div className="route-blocked-card">
        <Loading label="Taking you to the right page…" />
      </div>
    </div>
  )
}

function Blocked({ title, message, actionLabel = 'Back to home', actionTo = '/' }) {
  const navigate = useNavigate()
  return (
    <main className="route-blocked">
      <section className="route-blocked-card">
        <span className="state-icon" aria-hidden="true">
          <Icon name="shield" size={24} />
        </span>
        <h1>{title}</h1>
        <p>{message}</p>
        <button type="button" className="btn btn-primary" onClick={() => navigate(actionTo)}>
          {actionLabel} <Icon name="arrow" size={16} />
        </button>
      </section>
    </main>
  )
}

function NotFound() {
  const navigate = useNavigate()
  return (
    <main className="route-blocked">
      <section className="route-blocked-card">
        <span className="state-icon" aria-hidden="true">
          <Icon name="question" size={24} />
        </span>
        <h1>Page not found</h1>
        <p>The page you were looking for does not exist. It may have been moved or the link is broken.</p>
        <button type="button" className="btn btn-primary" onClick={() => navigate('/')}>
          Back to home <Icon name="arrow" size={16} />
        </button>
      </section>
    </main>
  )
}

function RouteView({ route }) {
  switch (route.name) {
    case 'landing':
      return <Landing />
    case 'login':
      return <Login />
    case 'register':
      return <Register />
    case 'forgot-password':
      return <ForgotPassword />
    case 'dashboard':
      return <Dashboard />
    case 'tests':
      return <Tests />
    case 'exam':
      return <Exam testId={route.testId} />
    case 'result':
      return <Result attemptId={route.attemptId} />
    case 'solutions':
      return <Solutions attemptId={route.attemptId} />
    case 'history':
      return <History />
    case 'profile':
      return <Profile />
    default:
      return <NotFound />
  }
}

function AppRoutes() {
  const { pathname } = useLocation()
  const { user, token, ready, sessionError, logout, clearSessionError } = useAuth()
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)

  const route = resolveRoute(pathname)
  const name = route ? route.name : 'notfound'
  const isStaff = Boolean(user && STAFF_ROLES.includes(user.role))

  useEffect(() => {
    document.title = TITLES[name] || TITLES.notfound
  }, [name])

  /* Session restored? Never judge auth state before the boot request settles. */
  if (!ready) {
    return (
      <div className="route-blocked">
        <div className="route-blocked-card">
          <Loading label="Restoring your session…" />
        </div>
      </div>
    )
  }

  if (route && route.auth === 'required' && !user) return <Redirect to="/login" />
  if (route && route.auth === 'guests' && user) return <Redirect to="/dashboard" />
  if (route && route.auth === 'staff' && !isStaff) {
    return (
      <Blocked
        title={user ? 'Staff access required' : 'Admin console'}
        message={
          user
            ? 'The admin console is only available to users with an admin or editor role.'
            : 'Sign in with an assigned admin or editor account to continue.'
        }
        actionLabel={user ? 'Go to dashboard' : 'Log in'}
        actionTo={user ? '/dashboard' : '/login'}
      />
    )
  }

  async function handleLogout() {
    if (loggingOut) return
    setLoggingOut(true)
    try {
      await logout()
    } finally {
      setLoggingOut(false)
    }
  }

  function renderPage() {
    if (!route) return <NotFound />
    if (route.name === 'admin') {
      return (
        <AdminConsole
          user={user}
          token={token}
          onLogout={handleLogout}
          busy={loggingOut}
          sessionError={sessionError}
          navigateTo={(path) => navigate(path)}
        />
      )
    }
    return <RouteView route={route} />
  }

  if (!route || route.chrome !== 'none') {
    return (
      <>
        {sessionError && (
          <div className="session-notice" role="status">
            {sessionError}{' '}
            <button type="button" className="link-button" onClick={clearSessionError}>
              Dismiss
            </button>
          </div>
        )}
        <SiteHeader />
        {renderPage()}
        <SiteFooter />
      </>
    )
  }

  return renderPage()
}

export default function App() {
  return (
    <RouterProvider>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </RouterProvider>
  )
}
