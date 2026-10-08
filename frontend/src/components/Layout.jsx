/** App chrome: primary header, footer and the shared authentication shell. */
import { useEffect, useState } from 'react'
import { useAuth } from '../lib/auth'
import { Link, useLocation } from '../lib/router'
import Icon from './Icon'
import { Avatar } from './ui'

const NAV_ITEMS = [
  { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
  { to: '/tests', label: 'Model Tests', icon: 'book' },
  { to: '/history', label: 'History', icon: 'clock' },
  { to: '/profile', label: 'Profile', icon: 'users' },
]

function isActivePath(pathname, to) {
  return pathname === to || pathname.startsWith(`${to}/`)
}

export function SiteHeader() {
  const { user, logout } = useAuth()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)
  const [loggingOut, setLoggingOut] = useState(false)

  useEffect(() => {
    setOpen(false)
  }, [pathname])

  async function handleLogout() {
    if (loggingOut) return
    setLoggingOut(true)
    try {
      await logout()
    } finally {
      setLoggingOut(false)
    }
  }

  const isStaff = user && (user.role === 'admin' || user.role === 'editor')

  return (
    <header className="app-header">
      <div className="app-header-inner">
        <Link to="/" className="app-brand" aria-label="NurseExam247 home">
          <span className="app-brand-mark" aria-hidden="true">
            <Icon name="graduation" size={20} />
          </span>
          <span className="app-brand-text">
            NurseExam<span>247</span>
          </span>
        </Link>

        <button
          type="button"
          className="nav-toggle"
          aria-expanded={open}
          aria-controls="primary-nav"
          aria-label={open ? 'Close navigation menu' : 'Open navigation menu'}
          onClick={() => setOpen((value) => !value)}
        >
          <Icon name={open ? 'close' : 'menu'} size={20} />
        </button>

        <nav id="primary-nav" className={`app-nav${open ? ' is-open' : ''}`} aria-label="Primary">
          {user &&
            NAV_ITEMS.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`app-nav-link${isActivePath(pathname, item.to) ? ' is-active' : ''}`}
                aria-current={isActivePath(pathname, item.to) ? 'page' : undefined}
              >
                <Icon name={item.icon} size={16} />
                <span>{item.label}</span>
              </Link>
            ))}

          {isStaff && (
            <Link
              to="/admin/dashboard"
              className={`app-nav-link${isActivePath(pathname, '/admin') ? ' is-active' : ''}`}
            >
              <Icon name="shield" size={16} />
              <span>Admin</span>
            </Link>
          )}

          {user ? (
            <div className="app-nav-user">
              <Link to="/profile" className="nav-user-chip">
                <Avatar name={user.name} avatarUrl={user.avatar_url} size="sm" />
                <span className="nav-user-meta">
                  <strong>{user.name}</strong>
                  <small>{user.role}</small>
                </span>
              </Link>
              <button
                type="button"
                className="btn btn-ghost btn-sm"
                onClick={handleLogout}
                disabled={loggingOut}
                aria-label="Sign out of your account"
              >
                <Icon name="logout" size={16} />
                <span className="nav-logout-label">{loggingOut ? 'Signing out…' : 'Sign out'}</span>
              </button>
            </div>
          ) : (
            <div className="app-nav-actions">
              <Link to="/login" className="btn btn-ghost btn-sm">
                Log in
              </Link>
              <Link to="/register" className="btn btn-primary btn-sm">
                Create free account <Icon name="arrow" size={15} />
              </Link>
            </div>
          )}
        </nav>
      </div>
    </header>
  )
}

const FOOTER_YEAR = new Date().getFullYear()

export function SiteFooter() {
  const year = FOOTER_YEAR
  return (
    <footer className="app-footer">
      <div className="app-footer-inner">
        <div className="app-footer-brand">
          <span className="app-brand-mark" aria-hidden="true">
            <Icon name="graduation" size={18} />
          </span>
          <div>
            <strong>
              NurseExam<span>247</span>
            </strong>
            <p>বাংলাদেশের নার্সিং পরীক্ষার পূর্ণাঙ্গ প্রস্তুতি — 15 বিষয়, ১০০ প্রশ্ন, ৬০ মিনিট।</p>
          </div>
        </div>
        <nav className="app-footer-links" aria-label="Footer">
          <Link to="/tests">Model tests</Link>
          <Link to="/history">Attempt history</Link>
          <Link to="/profile">Profile</Link>
          <Link to="/register">Create account</Link>
        </nav>
        <p className="app-footer-note">© {year} NurseExam247. Built for future nurses.</p>
      </div>
    </footer>
  )
}

/** Centred card layout used by login / register / password reset. */
export function AuthShell({ eyebrow, title, subtitle, children, footer }) {
  return (
    <main className="auth-layout">
      <section className="auth-panel">
        <div className="auth-panel-copy">
          <span className="kicker">{eyebrow}</span>
          <h1>নির্ভরযোগ্য প্রস্তুতি, প্রতিটি প্রশ্নে।</h1>
          <ul className="auth-panel-points">
            <li>
              <Icon name="check" size={15} /> 15 nursing &amp; general subjects in Bengali
            </li>
            <li>
              <Icon name="check" size={15} /> Real 60-minute model tests with −0.25 negative marking
            </li>
            <li>
              <Icon name="check" size={15} /> Instant score, pass/fail and full solutions
            </li>
          </ul>
        </div>

        <div className="auth-box">
          <div className="auth-box-head">
            <span className="kicker">{eyebrow}</span>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
          </div>
          {children}
          {footer && <div className="auth-box-foot">{footer}</div>}
        </div>
      </section>
    </main>
  )
}
