import { useEffect, useState } from 'react'
import './App.css'

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'nurseexam247_token'
const COPYRIGHT_YEAR = new Date().getFullYear()

async function apiRequest(path, { token, ...options } = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    const validationMessage = data.errors
      ? Object.values(data.errors).flat()[0]
      : null
    throw Object.assign(
      new Error(validationMessage || data.message || 'Something went wrong. Please try again.'),
      { status: response.status },
    )
  }

  return data
}

function Icon({ name, size = 20 }) {
  const common = {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
  }

  const paths = {
    arrow: <><path d="M5 12h14" /><path d="m12 5 7 7-7 7" /></>,
    book: <><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22z" /><path d="M4 5.5v14A2.5 2.5 0 0 1 6.5 17H20" /><path d="M8 7h8M8 11h7" /></>,
    check: <><path d="m5 12 4 4L19 6" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    shield: <><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11" /><path d="m9 12 2 2 4-4" /></>,
    star: <><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z" /></>,
    users: <><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="10" cy="7" r="4" /><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></>,
    logout: <><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" /></>,
    spark: <><path d="m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z" /><path d="m19 14 1.1 2.9L23 18l-2.9 1.1L19 22l-1.1-2.9L15 18l2.9-1.1z" /></>,
    eye: <><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7" /><circle cx="12" cy="12" r="3" /></>,
    close: <><path d="m18 6-12 12M6 6l12 12" /></>,
    menu: <><path d="M4 6h16M4 12h16M4 18h16" /></>,
    heart: <><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8" /></>,
    dashboard: <><rect x="3" y="3" width="8" height="8" rx="1.5" /><rect x="13" y="3" width="8" height="5" rx="1.5" /><rect x="13" y="10" width="8" height="11" rx="1.5" /><rect x="3" y="13" width="8" height="8" rx="1.5" /></>,
    info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></>,
    chart: <><path d="M3 3v18h18" /><path d="m19 9-5 5-4-4-5 5" /></>,
    question: <><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 1 1 4.3 1.7c-1.2 1.1-1.8 1.3-1.8 3" /><path d="M12 17h.01" /></>,
    trophy: <><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z" /><path d="M7 6H4v2a4 4 0 0 0 4 4M17 6h3v2a4 4 0 0 1-4 4" /></>,
    tag: <><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8" /><circle cx="7.5" cy="7.5" r="1" /></>,
    wallet: <><rect x="3" y="5" width="18" height="15" rx="2" /><path d="M3 8h18M16 14h2" /><path d="M6 5V3h12" /></>,
    payment: <><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h3" /></>,
    bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></>,
    megaphone: <><path d="m3 11 18-5v12L3 13z" /><path d="M11 15 13 22H8l-2-8" /><path d="M21 10h1" /></>,
    settings: <><circle cx="12" cy="12" r="3" /><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7-.2.1-1.2.7-.3 1.8h-2.8l-.3-1.8-1.4-.8-1.7.7-1.4-2.4 1.4-1.1v-1.6l-1.4-1.1 1.4-2.4 1.7.7 1.4-.8.3-1.8h2.8l.3 1.8 1.4.8 1.7-.7 1.4 2.4-1.4 1.1z" /></>,
  }

  return <svg {...common}>{paths[name] || paths.spark}</svg>
}

function Brand({ light = false }) {
  return (
    <a className={`brand${light ? ' brand-light' : ''}`} href="#home" aria-label="NurseExam247 home">
      <span className="brand-mark"><Icon name="book" size={22} /></span>
      <span>NurseExam<span className="brand-number">247</span></span>
    </a>
  )
}

function Header({ onAuth, user, onLogout, busy, onNavigate, isStaff }) {
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <header className="site-header">
      <div className="nav-wrap">
        <Brand />
        <button
          className="mobile-menu-button"
          aria-label="Toggle navigation"
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen(!menuOpen)}
        >
          <Icon name={menuOpen ? 'close' : 'menu'} />
        </button>
        <nav className={`main-nav${menuOpen ? ' nav-open' : ''}`} aria-label="Main navigation">
          {user && isStaff ? (
            <a href="/admin/dashboard" onClick={(event) => { event.preventDefault(); onNavigate('/admin/dashboard'); setMenuOpen(false) }}>Admin dashboard</a>
          ) : (
            <>
              <a href="#how-it-works" onClick={() => setMenuOpen(false)}>How it works</a>
              <a href="#subjects" onClick={() => setMenuOpen(false)}>Subjects</a>
              <a href="#why-us" onClick={() => setMenuOpen(false)}>Why NurseExam</a>
            </>
          )}
          {user ? (
            <div className="nav-user">
              <span className="nav-user-name">{user.name}</span>
              <button className="button button-outline button-small" onClick={onLogout} disabled={busy}>
                <Icon name="logout" size={16} /> {busy ? 'Signing out…' : 'Sign out'}
              </button>
            </div>
          ) : (
            <div className="nav-actions">
              <button className="button button-quiet" onClick={() => { onAuth('login'); setMenuOpen(false) }}>Log in</button>
              <button className="button button-primary button-small" onClick={() => { onAuth('register'); setMenuOpen(false) }}>
                Create free account <Icon name="arrow" size={16} />
              </button>
            </div>
          )}
        </nav>
      </div>
    </header>
  )
}

function Hero({ onAuth, user }) {
  return (
    <section className="hero-section" id="home">
      <div className="hero-wrap">
        <div className="hero-copy">
          <div className="eyebrow"><span className="eyebrow-dot" /> YOUR NURSING EXAM, REIMAGINED</div>
          <h1>Your next chapter<br />starts with <span>one question.</span></h1>
          <p className="hero-description">
            Build confidence for your nursing entrance exam with focused practice,
            realistic model tests, and progress you can actually see.
          </p>
          <div className="hero-actions">
            <button className="button button-primary button-large" onClick={() => onAuth(user ? 'dashboard' : 'register')}>
              {user ? 'Go to my dashboard' : 'Start learning for free'} <Icon name="arrow" />
            </button>
            <a className="text-link" href="#how-it-works"><span className="play-icon">▶</span> See how it works</a>
          </div>
          <div className="social-proof">
            <div className="avatar-stack" aria-hidden="true"><span>R</span><span>S</span><span>M</span><span>A</span></div>
            <div><strong>A calmer way to prepare</strong><span>Made for future nurses, by design.</span></div>
          </div>
        </div>

        <div className="hero-art" aria-label="Illustration of a practice exam and study progress">
          <div className="art-sun" />
          <div className="art-spark spark-one">✳</div><div className="art-spark spark-two">✦</div>
          <div className="hero-back-card">
            <span className="back-card-dot" /><span className="back-card-line line-wide" /><span className="back-card-line" />
            <div className="back-card-chart"><i /><i /><i /><i /><i /><i /><i /></div>
          </div>
          <div className="exam-card">
            <div className="exam-card-top"><span className="mini-brand"><Icon name="heart" size={16} /></span><span>DAILY PRACTICE</span><span className="card-menu">···</span></div>
            <div className="exam-progress"><span>Question 08 <b>of 20</b></span><span>08:42</span></div>
            <div className="progress-line"><i /></div>
            <h3>Which vitamin is essential for blood clotting?</h3>
            <div className="answer-option"><span>A</span> Vitamin A</div>
            <div className="answer-option selected"><span>B</span> Vitamin K <Icon name="check" size={17} /></div>
            <div className="answer-option"><span>C</span> Vitamin C</div>
            <div className="answer-option"><span>D</span> Vitamin D</div>
            <div className="exam-card-bottom"><span>Keep going, you’ve got this!</span><span className="round-arrow"><Icon name="arrow" size={16} /></span></div>
          </div>
          <div className="floating-note"><span className="note-icon"><Icon name="star" size={16} /></span><span><b>Little by little</b><small>progress adds up</small></span></div>
          <div className="hero-art-caption">A little practice today goes a long way.</div>
        </div>
      </div>
      <div className="hero-bottom"><span>MADE FOR THE PEOPLE WHO CARE FOR PEOPLE</span><span className="hero-bottom-line" /><span>PREPARE WITH PURPOSE <Icon name="arrow" size={14} /></span></div>
    </section>
  )
}

const subjects = [
  { number: '01', name: 'Nursing', detail: 'Core concepts & clinical basics', symbol: '✚', tone: 'mint' },
  { number: '02', name: 'English', detail: 'Grammar, vocabulary & more', symbol: 'Aa', tone: 'peach' },
  { number: '03', name: 'General Knowledge', detail: 'Stay curious, stay current', symbol: '◎', tone: 'lavender' },
  { number: '04', name: 'ICT', detail: 'Digital skills made simple', symbol: '⌘', tone: 'yellow' },
]

function SubjectSection({ onAuth }) {
  return (
    <section className="subjects-section section-pad" id="subjects">
      <div className="section-heading">
        <div><div className="eyebrow">YOUR STUDY SPACE</div><h2>One goal. All the<br /><span>right subjects.</span></h2></div>
        <p>Everything you need to feel ready, organized by subject and built around how you learn.</p>
      </div>
      <div className="subject-grid">
        {subjects.map((subject) => (
          <article className="subject-card" key={subject.number}>
            <div className={`subject-symbol ${subject.tone}`}>{subject.symbol}</div>
            <span className="subject-number">{subject.number}</span>
            <h3>{subject.name}</h3><p>{subject.detail}</p>
            <button className="subject-link" onClick={() => onAuth('register')} aria-label={`Explore ${subject.name}`}>
              Explore <Icon name="arrow" size={17} />
            </button>
          </article>
        ))}
      </div>
      <div className="subjects-note"><Icon name="spark" size={18} /> Your next study session is only a few minutes away.</div>
    </section>
  )
}

function HowItWorks() {
  const steps = [
    { number: '01', icon: 'users', title: 'Make it yours', text: 'Create your free account and set up a study space that feels like yours.' },
    { number: '02', icon: 'book', title: 'Practice with purpose', text: 'Explore nursing, English, GK, ICT and focused model tests.' },
    { number: '03', icon: 'spark', title: 'See your progress', text: 'Learn from every answer and keep building confidence, one day at a time.' },
  ]

  return (
    <section className="how-section section-pad" id="how-it-works">
      <div className="section-heading centered-heading">
        <div className="eyebrow">A SIMPLE WAY FORWARD</div>
        <h2>Small steps. <span>Big confidence.</span></h2>
        <p>Good preparation doesn’t have to feel overwhelming. Start wherever you are.</p>
      </div>
      <div className="steps-grid">
        {steps.map((step) => (
          <article className="step-card" key={step.number}>
            <span className="step-number">{step.number}</span>
            <span className="step-icon"><Icon name={step.icon} size={22} /></span>
            <h3>{step.title}</h3><p>{step.text}</p>
          </article>
        ))}
      </div>
    </section>
  )
}

function WhySection({ onAuth }) {
  return (
    <section className="why-section" id="why-us">
      <div className="why-inner">
        <div className="why-copy">
          <div className="eyebrow eyebrow-light">MADE WITH CARE</div>
          <h2>Less overwhelm.<br /><span>More “I’ve got this.”</span></h2>
          <p>A thoughtful place to practice, learn from your mistakes, and notice how far you’ve come. No pressure to be perfect—just room to grow.</p>
          <ul className="why-list">
            <li><span><Icon name="check" size={15} /></span> Practice at your own pace</li>
            <li><span><Icon name="check" size={15} /></span> Review answers and explanations</li>
            <li><span><Icon name="check" size={15} /></span> Keep your progress in one place</li>
          </ul>
          <button className="button button-light button-large" onClick={() => onAuth('register')}>Find your rhythm <Icon name="arrow" /></button>
        </div>
        <div className="why-visual">
          <div className="why-circle circle-back" /><div className="why-circle circle-front" />
          <div className="quote-card"><span className="quote-mark">“</span><p>Show up for yourself today. Your future self will thank you.</p><div className="quote-line" /><span className="quote-caption">YOUR DAILY REMINDER</span></div>
          <span className="why-star star-a">✳</span><span className="why-star star-b">✦</span>
          <div className="why-sticker"><Icon name="heart" size={19} /><span>Care starts<br />with you.</span></div>
        </div>
      </div>
    </section>
  )
}

function Footer({ onAuth }) {
  return (
    <footer className="site-footer">
      <div className="footer-top"><Brand light /><p>A little practice today. A lot more confidence tomorrow.</p><button className="button button-primary button-small" onClick={() => onAuth('register')}>Get started <Icon name="arrow" size={16} /></button></div>
      <div className="footer-bottom"><span>© {COPYRIGHT_YEAR} NurseExam247. Made with care.</span><span>For every future nurse <Icon name="heart" size={14} /></span></div>
    </footer>
  )
}

function AuthPanel({ mode, onModeChange, onSuccess, onClose }) {
  const [showPassword, setShowPassword] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [email, setEmail] = useState('')

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setNotice('')
    setBusy(true)
    const form = new FormData(event.currentTarget)
    const isRegister = mode === 'register'
    const email = form.get('email').trim()
    const isForgot = mode === 'forgot'
    const isReset = mode === 'reset'
    const payload = isRegister
      ? { name: form.get('name').trim(), email, password: form.get('password'), password_confirmation: form.get('password_confirmation') }
      : isReset
        ? { email, otp: form.get('otp'), password: form.get('password'), password_confirmation: form.get('password_confirmation') }
        : isForgot ? { email } : { email, password: form.get('password') }
    const endpoint = isRegister ? '/auth/register'
      : isReset ? '/auth/reset-password'
        : isForgot ? '/auth/forgot-password' : '/auth/login'

    try {
      const data = await apiRequest(endpoint, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      if (isForgot) {
        setNotice('If an account exists for this email, a 6-digit reset code will be sent. Check your inbox.')
        onModeChange('reset')
      } else if (isReset) {
        onModeChange('login')
        setNotice('Password updated. You can now log in with your new password.')
      } else {
        onSuccess(data)
      }
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }

  const isRegister = mode === 'register'
  const isForgot = mode === 'forgot'
  const isReset = mode === 'reset'
  const isLogin = mode === 'login'

  return (
    <div className="auth-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget) onClose()
    }}>
      <section className="auth-card" role="dialog" aria-modal="true" aria-labelledby="auth-title">
        <button className="auth-close" onClick={onClose} aria-label="Close sign in form"><Icon name="close" /></button>
        <div className="auth-card-brand"><Brand /></div>
        <div className="auth-icon"><Icon name={isRegister || isForgot ? 'spark' : 'book'} size={23} /></div>
        <div className="auth-eyebrow">{isRegister ? 'A FRESH START' : isForgot || isReset ? 'ACCOUNT RECOVERY' : 'WELCOME BACK'}</div>
        <h2 id="auth-title">{isRegister ? 'Your study journey starts here.' : isForgot ? 'Let’s get you back in.' : isReset ? 'Enter your reset code.' : 'Good to have you back.'}</h2>
        <p className="auth-intro">{isRegister ? 'Create your free account and take the first small step.' : isForgot ? 'We’ll send a one-time code to the email address on your account.' : isReset ? 'Enter the 6-digit code from your email and choose a new password.' : 'Pick up where you left off and keep your momentum.'}</p>
        {notice && <div className="form-notice" role="status">{notice}</div>}
        {error && <div className="form-error" role="alert">{error}</div>}
        <form onSubmit={handleSubmit} className="auth-form">
          {isRegister && <label>Your name<input name="name" type="text" autoComplete="name" placeholder="e.g. Ayesha Rahman" required maxLength="255" /></label>}
          <label>Email address<input name="email" type="email" autoComplete="email" placeholder="you@example.com" required maxLength="255" value={email} onChange={(event) => setEmail(event.target.value)} /></label>
          {isLogin && <label className="password-label"><span>Password</span><button type="button" className="forgot-link" onClick={() => { setError(''); setNotice(''); onModeChange('forgot') }}>Forgot password?</button><div className="password-wrap"><input name="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder="Enter your password" required minLength="8" /><button type="button" className="show-password" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)}><Icon name="eye" size={18} /></button></div></label>}
          {isForgot && <div className="role-note"><Icon name="shield" size={17} /><span>For account privacy, we’ll show the same confirmation whether or not the email is registered.</span></div>}
          {isReset && <label>6-digit reset code<input name="otp" type="text" inputMode="numeric" autoComplete="one-time-code" placeholder="Enter the code from your email" required minLength="6" maxLength="6" pattern="[0-9]{6}" /></label>}
          {(isRegister || isReset) && <label>{isReset ? 'New password' : 'Password'}<div className="password-wrap"><input name="password" type={showPassword ? 'text' : 'password'} autoComplete="new-password" placeholder="At least 8 characters" required minLength="8" /><button type="button" className="show-password" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)}><Icon name="eye" size={18} /></button></div></label>}
          {(isRegister || isReset) && <label>Confirm password<input name="password_confirmation" type={showPassword ? 'text' : 'password'} autoComplete="new-password" placeholder="Type your password again" required minLength="8" /></label>}
          {isRegister && <div className="role-note"><Icon name="shield" size={17} /><span>New accounts are created as <b>Student</b>. Admin and editor roles are assigned by the site team.</span></div>}
          <button className="button button-primary auth-submit" type="submit" disabled={busy}>{busy ? 'Please wait…' : isRegister ? 'Create my free account' : isForgot ? 'Send reset code' : isReset ? 'Update my password' : 'Log in to my account'} {!busy && <Icon name="arrow" size={17} />}</button>
        </form>
        <div className="auth-switch">{isForgot || isReset ? <><button onClick={() => { setError(''); setNotice(''); onModeChange('login') }}>← Back to log in</button></> : <>{isRegister ? 'Already have an account?' : 'New to NurseExam247?'} <button onClick={() => { setError(''); setNotice(''); onModeChange(isRegister ? 'login' : 'register') }}>{isRegister ? 'Log in' : 'Create a free account'}</button></>}</div>
        <div className="auth-safe"><Icon name="shield" size={15} /> Your account details stay private and secure.</div>
      </section>
    </div>
  )
}

const adminMenu = [
  { heading: 'OVERVIEW', items: [{ id: 'dashboard', label: 'Dashboard', icon: 'dashboard' }] },
  { heading: 'PEOPLE & CONTENT', items: [
    { id: 'users', label: 'Users', icon: 'users', soon: true },
    { id: 'question-bank', label: 'Question Bank', icon: 'question', soon: true },
    { id: 'model-tests', label: 'Model Tests', icon: 'book' },
    { id: 'subjects', label: 'Subjects', icon: 'book', soon: true },
  ] },
  { heading: 'EXAMS & LEARNING', items: [
    { id: 'exams', label: 'Exams & Attempts', icon: 'clock', soon: true },
    { id: 'results', label: 'Results & Analytics', icon: 'chart', soon: true },
    { id: 'leaderboard', label: 'Leaderboard', icon: 'trophy', soon: true },
    { id: 'gamification', label: 'Gamification', icon: 'spark', soon: true },
    { id: 'challenges', label: 'Challenges', icon: 'star', soon: true },
  ] },
  { heading: 'COMMERCE', items: [
    { id: 'coupons', label: 'Coupons', icon: 'tag', soon: true },
    { id: 'referrals', label: 'Referrals', icon: 'users', soon: true },
    { id: 'wallet', label: 'Wallet', icon: 'wallet', soon: true },
    { id: 'payments', label: 'Payments', icon: 'payment', soon: true },
    { id: 'subscriptions', label: 'Subscriptions', icon: 'star', soon: true },
  ] },
  { heading: 'ENGAGEMENT & SYSTEM', items: [
    { id: 'notifications', label: 'Notifications', icon: 'bell', soon: true },
    { id: 'advertising', label: 'Advertisement', icon: 'megaphone', soon: true },
    { id: 'support', label: 'Support', icon: 'heart', soon: true },
    { id: 'security', label: 'Admin & Security', icon: 'shield', soon: true },
    { id: 'settings', label: 'Settings', icon: 'settings', soon: true },
  ] },
]

function AdminDashboard({ user, token, onLogout, busy, sessionError, navigateTo }) {
  const [metrics, setMetrics] = useState(null)
  const [activity, setActivity] = useState([])
  const [subjectList, setSubjectList] = useState([])
  const [tests, setTests] = useState([])
  const [activePage, setActivePage] = useState('dashboard')
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [createOpen, setCreateOpen] = useState(false)
  const [createBusy, setCreateBusy] = useState(false)
  const [createError, setCreateError] = useState('')
  const [notice, setNotice] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const adminName = user.name.trim().split(/\s+/)[0]

  useEffect(() => {
    let active = true
    setLoading(true)
    setLoadError('')
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
      setReloadKey((value) => value + 1)
    } catch (requestError) {
      setCreateError(requestError.message)
    } finally {
      setCreateBusy(false)
    }
  }

  const number = (value) => value === null || value === undefined ? '—' : Number(value).toLocaleString()
  const metricCards = [
    { label: 'Total students', value: metrics?.students, icon: 'users', tone: 'mint', note: `${number(metrics?.new_students_today)} joined today` },
    { label: 'Active students', value: metrics?.active_students, icon: 'heart', tone: 'lavender', note: 'Active student accounts' },
    { label: 'Questions', value: metrics?.questions, icon: 'question', tone: 'peach', note: 'In the question bank' },
    { label: 'Published tests', value: metrics?.published_tests, icon: 'book', tone: 'yellow', note: `${number(metrics?.draft_tests)} drafts` },
    { label: 'Exams today', value: metrics?.attempts_today, icon: 'clock', tone: 'lavender', note: `${number(metrics?.finished_attempts)} completed all time` },
    { label: 'Average score', value: metrics?.average_score == null ? null : `${Number(metrics.average_score).toFixed(1)}%`, icon: 'chart', tone: 'mint', note: 'Finished attempts' },
    { label: 'Completion rate', value: metrics?.completion_rate == null ? null : `${Number(metrics.completion_rate).toFixed(1)}%`, icon: 'check', tone: 'peach', note: 'Submitted or expired attempts' },
    { label: 'Revenue', value: null, icon: 'wallet', tone: 'yellow', note: 'Payments module not connected' },
  ]
  const maxAttempts = Math.max(1, ...activity.map((day) => day.attempts))
  const selectedItem = adminMenu.flatMap((group) => group.items).find((item) => item.id === activePage)

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
                <button
                  className={`admin-nav-item${activePage === item.id ? ' admin-nav-active' : ''}${item.soon ? ' admin-nav-soon' : ''}`}
                  key={item.id}
                  onClick={() => selectPage(item.id)}
                  title={item.soon ? 'This management module is not implemented yet.' : item.label}
                >
                  <Icon name={item.icon} size={17} /><span>{item.label}</span>
                  {item.soon ? <small>SOON</small> : item.id === 'model-tests' && metrics ? <small>{number(metrics.tests)}</small> : null}
                </button>
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
                <button onClick={() => { setActivePage('question-bank'); setSidebarOpen(false) }}><Icon name="question" size={16} /> Add question <small>SOON</small></button>
                <button onClick={() => { setCreateError(''); setCreateOpen(true) }}><Icon name="book" size={16} /> Create model test</button>
                <button onClick={() => { setActivePage('subjects'); setSidebarOpen(false) }}><Icon name="book" size={16} /> Add subject <small>SOON</small></button>
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
                  <div className="catalogue-row"><span className="catalogue-marker mint-marker"><Icon name="book" size={16} /></span><span><b>Active subjects</b><small>Ready for study</small></span><strong>{loading ? '…' : number(subjectList.length)}</strong></div>
                  <div className="catalogue-row"><span className="catalogue-marker peach-marker"><Icon name="check" size={16} /></span><span><b>Published model tests</b><small>Visible to students</small></span><strong>{loading ? '…' : number(metrics?.published_tests)}</strong></div>
                  <div className="catalogue-row"><span className="catalogue-marker yellow-marker"><Icon name="question" size={16} /></span><span><b>Question bank</b><small>Questions available</small></span><strong>{loading ? '…' : number(metrics?.questions)}</strong></div>
                </section>
              </div>
              <section className="admin-panel admin-recent-panel" id="admin-tests">
                <div className="admin-panel-heading"><div><span className="eyebrow">PUBLIC CATALOGUE</span><h2>Published model tests</h2></div><button className="admin-text-button" onClick={() => selectPage('model-tests')}>View all <Icon name="arrow" size={15} /></button></div>
                {loading ? <div className="dashboard-loading">Loading published tests…</div> : tests.length ? <div className="admin-test-table"><div className="admin-table-row admin-table-head"><span>TEST</span><span>SUBJECT</span><span>QUESTIONS</span><span>STATUS</span></div>{tests.slice(0, 5).map((test) => <div className="admin-table-row" key={test.id}><span><b>{test.title}</b><small>{test.code}</small></span><span>{test.subject?.name || '—'}</span><span>{number(test.question_count)}</span><span><i className="published-dot" /> Published</span></div>)}</div> : <p className="empty-state">No published tests yet. Create a draft model test to begin building the catalogue.</p>}
              </section>
              <p className="admin-data-note"><Icon name="shield" size={15} /> Revenue, premium subscriptions, referrals and top-student rankings are not tracked until their modules are implemented.</p>
            </>
          ) : activePage === 'model-tests' ? (
            <section className="admin-module-page"><div className="admin-page-heading"><div><span className="eyebrow">LEARNING CONTENT</span><h1>Model tests</h1><p>Create and publish practice exams for students.</p></div><button className="button button-primary" onClick={() => { setCreateError(''); setCreateOpen(true) }}><Icon name="arrow" size={17} /> Create model test</button></div><div className="admin-module-stats"><span><b>{number(metrics?.tests)}</b> total tests</span><span><b>{number(metrics?.published_tests)}</b> published</span><span><b>{number(metrics?.draft_tests)}</b> drafts</span></div><div className="admin-panel admin-recent-panel"><div className="admin-panel-heading"><div><span className="eyebrow">PUBLIC CATALOGUE</span><h2>Published tests</h2></div></div>{loading ? <div className="dashboard-loading">Loading tests…</div> : tests.length ? <div className="admin-test-table"><div className="admin-table-row admin-table-head"><span>TEST</span><span>SUBJECT</span><span>QUESTIONS</span><span>STATUS</span></div>{tests.map((test) => <div className="admin-table-row" key={test.id}><span><b>{test.title}</b><small>{test.code}</small></span><span>{test.subject?.name || '—'}</span><span>{number(test.question_count)}</span><span><i className="published-dot" /> Published</span></div>)}</div> : <p className="empty-state">No published tests yet. Draft tests can be created, but assigning question sets requires the question-management API.</p>}</div><p className="admin-data-note"><Icon name="info" size={15} /> Draft listing and question assignment are not yet available in the admin interface.</p></section>
          ) : (
            <section className="admin-coming-page"><span className="admin-coming-icon"><Icon name={selectedItem?.icon || 'settings'} size={27} /></span><span className="eyebrow">PLATFORM MODULE</span><h1>{selectedItem?.label || 'Management'}</h1><p>This section is part of the platform management plan, but its management API and interface are not implemented yet.</p><div className="admin-coming-status"><Icon name="clock" size={16} /> Coming soon — no data or actions are available here yet.</div><button className="button button-outline" onClick={() => selectPage('dashboard')}>Back to dashboard <Icon name="arrow" size={15} /></button></section>
          )}
        </div>
      </section>
      {createOpen && <div className="auth-backdrop admin-create-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !createBusy) setCreateOpen(false) }}><section className="auth-card admin-create-card" role="dialog" aria-modal="true" aria-labelledby="create-test-title"><button className="auth-close" onClick={() => setCreateOpen(false)} aria-label="Close create test form"><Icon name="close" /></button><div className="auth-eyebrow">MODEL TEST BUILDER</div><h2 id="create-test-title">Create a test draft</h2><p className="auth-intro">Set the exam basics first. Add questions and publish it from the content workflow.</p>{createError && <div className="form-error" role="alert">{createError}</div>}<form className="auth-form admin-test-form" onSubmit={createTest}><label>Test title<input name="title" required maxLength="255" placeholder="e.g. Nursing Practice Set 01" /></label><label>Subject<select name="subject_id" required defaultValue=""><option value="" disabled>Select a subject</option>{subjectList.map((subject) => <option value={subject.id} key={subject.id}>{subject.name}</option>)}</select></label><div className="admin-form-row"><label>Duration (minutes)<input name="duration_minutes" type="number" min="1" max="600" defaultValue="30" required /></label><label>Question count<input name="question_count" type="number" min="1" max="500" defaultValue="20" required /></label></div><div className="admin-form-row"><label>Total marks<input name="total_marks" type="number" min="0.01" step="0.01" defaultValue="20" required /></label><label>Passing score<input name="passing_score" type="number" min="0" step="0.01" defaultValue="10" required /></label></div><div className="admin-form-row"><label>Wrong answer penalty<input name="negative_marking" type="number" min="0" step="0.01" defaultValue="0.25" /></label><label className="admin-checkbox-label"><input name="is_negative_marking_enabled" type="checkbox" defaultChecked /> Enable negative marking</label></div><label>Description (optional)<input name="description" maxLength="10000" placeholder="Short description for this test" /></label><label className="admin-checkbox-label"><input name="is_premium" type="checkbox" /> Premium test</label><button className="button button-primary auth-submit" disabled={createBusy || subjectList.length === 0}>{createBusy ? 'Creating draft…' : 'Create test draft'} {!createBusy && <Icon name="arrow" size={17} />}</button>{subjectList.length === 0 && <span className="admin-data-note">An active subject is required before creating a test.</span>}</form></section></div>}
    </main>
  )
}

function Dashboard({ user, token, onLogout, busy, sessionError, isAdminRoute = false, onNavigate }) {
  const [tests, setTests] = useState([])
  const [subjectList, setSubjectList] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const isStaff = ['admin', 'editor'].includes(user.role)

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest('/subjects?per_page=8', { token }),
      apiRequest('/tests?per_page=6', { token }),
    ]).then(([subjectData, testData]) => {
      if (!active) return
      setSubjectList(subjectData.data || [])
      setTests(testData.data || [])
    }).catch((requestError) => {
      if (active) setLoadError(requestError.message)
    }).finally(() => {
      if (active) setLoading(false)
    })

    return () => { active = false }
  }, [token])

  const greeting = user.name.trim().split(/\s+/)[0]

  return (
    <main className="dashboard-page">
      <div className="dashboard-shell">
        <div className="dashboard-welcome">
          <div>
            <span className="dash-kicker"><span className="eyebrow-dot" /> {isAdminRoute ? 'ADMINISTRATION' : 'YOUR STUDY SPACE'}</span>
            <h1>{isAdminRoute ? <>Welcome, {greeting}. <span>Admin dashboard</span></> : <>Hi, {greeting}. <span>You’re right on time.</span></>}</h1>
            <p>{isAdminRoute ? 'Your staff access is active. Review the learning catalogue and available platform data below.' : 'Every question you practice is a step toward the nurse you’re becoming.'}</p>
          </div>
          <div className="profile-chip"><span className="profile-avatar">{greeting.charAt(0).toUpperCase()}</span><span><b>{user.name}</b><small>{user.role}</small></span></div>
        </div>
        <div className="dashboard-role-banner">
          <span className="role-banner-icon"><Icon name={isStaff ? 'shield' : 'heart'} /></span>
          <span><b>{isStaff ? `${user.role === 'admin' ? 'Administrator' : 'Editor'} account` : 'Your student account is ready'}</b><small>{isStaff ? 'You’re signed in with your assigned staff role.' : 'Pick a subject below and make today count, at your own pace.'}</small></span>
          <span className="role-badge">{user.role}</span>
        </div>
        {loadError && <div className="form-error dashboard-error" role="alert">{loadError}</div>}
        <section className="dashboard-section">
          <div className="dashboard-section-heading"><div><span className="eyebrow">{isAdminRoute ? 'LEARNING CATALOGUE' : 'START WHERE YOU ARE'}</span><h2>{isAdminRoute ? 'Subjects' : 'Your subjects'}</h2></div><span className="dashboard-count">{subjectList.length} available</span></div>
          {loading ? <div className="dashboard-loading">Getting your study space ready…</div> : subjectList.length ? (
            <div className="dashboard-subjects">
              {subjectList.map((subject, index) => <article className="dashboard-subject" key={subject.id}><span className={`subject-symbol ${['mint', 'peach', 'lavender', 'yellow'][index % 4]}`}>{['✚', 'Aa', '◎', '⌘'][index % 4]}</span><div><b>{subject.name}</b><small>{subject.code}</small></div><Icon name="arrow" size={18} /></article>)}
            </div>
          ) : !loading && !loadError ? <p className="empty-state">Your subjects will appear here as soon as they’re added.</p> : null}
        </section>
        <section className="dashboard-section tests-dashboard-section">
          <div className="dashboard-section-heading"><div><span className="eyebrow">{isAdminRoute ? 'PUBLISHED CONTENT' : 'PRACTICE AT YOUR PACE'}</span><h2>Model tests</h2></div><span className="dashboard-count">{tests.length} available</span></div>
          {loading ? <div className="dashboard-loading">Finding your next practice test…</div> : tests.length ? (
            <div className="test-grid">
              {tests.map((test) => <article className="test-card" key={test.id}><span className="test-card-icon"><Icon name="book" /></span><h3>{test.title}</h3><p>{test.subject?.name || 'Practice test'}</p><div className="test-meta"><span><Icon name="clock" size={15} /> {test.duration_minutes} min</span><span><Icon name="check" size={15} /> {test.question_count} questions</span></div></article>)}
            </div>
          ) : !loading && !loadError ? <p className="empty-state">New model tests are on the way. Check back soon.</p> : null}
        </section>
        {isAdminRoute && <div className="staff-note"><Icon name="shield" size={17} /> Only published tests are included here. Draft authoring and publishing are available through the protected admin API.</div>}
        {isStaff && !isAdminRoute && <button className="button button-outline admin-dashboard-link" onClick={() => onNavigate('/admin/dashboard')}>Open admin dashboard <Icon name="arrow" size={16} /></button>}
        <button className="button button-outline dashboard-logout" onClick={onLogout} disabled={busy}><Icon name="logout" size={17} /> {busy ? 'Signing out…' : 'Sign out'}</button>
        {sessionError && <p className="dashboard-api-error" role="alert">{sessionError}</p>}
      </div>
    </main>
  )
}

function App() {
  const [authMode, setAuthMode] = useState(() => (
    window.location.pathname === '/admin/dashboard' && !window.localStorage.getItem(TOKEN_KEY) ? 'login' : null
  ))
  const [session, setSession] = useState(null)
  const [sessionBusy, setSessionBusy] = useState(false)
  const [sessionError, setSessionError] = useState('')
  const [pathname, setPathname] = useState(() => window.location.pathname)
  const [authChecked, setAuthChecked] = useState(() => !window.localStorage.getItem(TOKEN_KEY))
  const isStaff = ['admin', 'editor'].includes(session?.user?.role)

  function navigateTo(path) {
    if (window.location.pathname !== path) window.history.pushState({}, '', path)
    setPathname(path)
  }

  useEffect(() => {
    const handlePopState = () => setPathname(window.location.pathname)
    window.addEventListener('popstate', handlePopState)

    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  useEffect(() => {
    const token = window.localStorage.getItem(TOKEN_KEY)
    if (!token) return

    apiRequest('/auth/me', { token })
      .then((data) => setSession({ token, user: data.user }))
      .catch((requestError) => {
        if (requestError.status === 401) {
          window.localStorage.removeItem(TOKEN_KEY)
          setSessionError('Your session expired. Log in again to continue.')
          return
        }
        setSessionError(`Could not restore your session: ${requestError.message}`)
      })
      .finally(() => setAuthChecked(true))
  }, [])

  function handleAuthSuccess(data) {
    window.localStorage.setItem(TOKEN_KEY, data.token)
    setSession({ token: data.token, user: data.user })
    setAuthMode(null)
    setSessionError('')
    navigateTo(['admin', 'editor'].includes(data.user.role) ? '/admin/dashboard' : '/')
  }

  async function handleLogout() {
    if (!session) return
    setSessionBusy(true)
    setSessionError('')
    try {
      await apiRequest('/auth/logout', { method: 'POST', token: session.token })
      window.localStorage.removeItem(TOKEN_KEY)
      setSession(null)
      navigateTo('/')
    } catch (requestError) {
      setSessionError(requestError.message)
    } finally {
      setSessionBusy(false)
    }
  }

  return (
    <>
      <Header onAuth={setAuthMode} user={session?.user} onLogout={handleLogout} busy={sessionBusy} onNavigate={navigateTo} isStaff={isStaff} />
      {!session && sessionError && <div className="session-banner" role="status">{sessionError}</div>}
      {session && authChecked
        ? pathname === '/admin/dashboard' && !isStaff
          ? <main className="route-denied"><div className="route-denied-card"><span className="auth-icon"><Icon name="shield" /></span><h1>Staff access required</h1><p>The admin dashboard is only available to users with an admin or editor role.</p><button className="button button-primary" onClick={() => navigateTo('/')}>Return to the home page <Icon name="arrow" size={16} /></button></div></main>
          : pathname === '/admin/dashboard'
            ? <AdminDashboard user={session.user} token={session.token} onLogout={handleLogout} busy={sessionBusy} sessionError={sessionError} navigateTo={navigateTo} />
            : <Dashboard user={session.user} token={session.token} onLogout={handleLogout} busy={sessionBusy} sessionError={sessionError} onNavigate={navigateTo} />
        : !session && authChecked && pathname === '/admin/dashboard'
          ? <main className="route-denied"><div className="route-denied-card"><span className="auth-icon"><Icon name="shield" /></span><h1>Admin dashboard</h1><p>Sign in with an assigned admin or editor account to continue.</p><button className="button button-primary" onClick={() => setAuthMode('login')}>Staff log in <Icon name="arrow" size={16} /></button><button className="route-home-link" onClick={() => navigateTo('/')}>Back to home</button></div></main>
        : !authChecked && pathname === '/admin/dashboard'
          ? <main className="route-denied"><div className="dashboard-loading">Checking your staff session…</div></main>
        : <><main><Hero onAuth={setAuthMode} /><SubjectSection onAuth={setAuthMode} /><HowItWorks /><WhySection onAuth={setAuthMode} /></main><Footer onAuth={setAuthMode} /></>}
      {authMode && <AuthPanel mode={authMode} onModeChange={setAuthMode} onSuccess={handleAuthSuccess} onClose={() => setAuthMode(null)} />}
    </>
  )
}

export default App
