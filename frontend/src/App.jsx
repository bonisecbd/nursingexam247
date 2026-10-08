import { useEffect, useState } from 'react'
import './App.css'

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'nurseexam247_token'
const COPYRIGHT_YEAR = new Date().getFullYear()

async function apiRequest(path, { token, ...options } = {}) {
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
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
    search: <><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 5 5" /></>,
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
    { id: 'leaderboard', label: 'Leaderboard', icon: 'trophy', soon: true, children: ['Daily', 'Weekly', 'Monthly', 'All Time'] },
    { id: 'gamification', label: 'Gamification', icon: 'spark', adminOnly: true, children: [{ id: 'gamification-rules', label: 'Reward rules', page: 'gamification' }] },
    { id: 'challenges', label: 'Challenges', icon: 'star', soon: true, children: ['Daily Challenge', 'Weekly Challenge', 'Competition'] },
  ] },
  { heading: 'COMMERCE', items: [
    { id: 'coupons', label: 'Coupons', icon: 'tag', soon: true, children: ['All Coupons', 'Create Coupon', 'Active', 'Expired'] },
    { id: 'referrals', label: 'Referrals', icon: 'users', soon: true, children: ['Referral Users', 'Referral Statistics', 'Rewards', 'Pending Rewards'] },
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
              <p className="admin-data-note"><Icon name="shield" size={15} /> Revenue, premium subscriptions, referrals and top-student rankings are not tracked until their modules are implemented.</p>
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

function Dashboard({ user, token, onLogout, busy, sessionError, isAdminRoute = false, onNavigate, onUserUpdated }) {
  const [tests, setTests] = useState([])
  const [subjectList, setSubjectList] = useState([])
  const [profile, setProfile] = useState(user)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [profileOpen, setProfileOpen] = useState(false)
  const [profileBusy, setProfileBusy] = useState(false)
  const [photoBusy, setPhotoBusy] = useState(false)
  const [profileError, setProfileError] = useState('')
  const [profileNotice, setProfileNotice] = useState('')
  const [today] = useState(() => new Date().toISOString().slice(0, 10))
  const isStaff = ['admin', 'editor'].includes(user.role)

  useEffect(() => {
    let active = true
    Promise.all([
      apiRequest('/subjects?per_page=8', { token }),
      apiRequest('/tests?per_page=6', { token }),
      apiRequest('/profile', { token }),
    ]).then(([subjectData, testData, profileData]) => {
      if (!active) return
      setSubjectList(subjectData.data || [])
      setTests(testData.data || [])
      setProfile(profileData.user)
    }).catch((requestError) => {
      if (active) setLoadError(requestError.message)
    }).finally(() => {
      if (active) setLoading(false)
    })

    return () => { active = false }
  }, [token])

  const greeting = profile.name.trim().split(/\s+/)[0]

  async function updateProfile(event) {
    event.preventDefault()
    setProfileError('')
    setProfileNotice('')
    setProfileBusy(true)
    const fields = new FormData(event.currentTarget)
    const payload = {
      name: fields.get('name').trim(),
      phone: fields.get('phone').trim() || null,
      date_of_birth: fields.get('date_of_birth') || null,
      gender: fields.get('gender') || null,
      address: fields.get('address').trim() || null,
      leaderboard_opt_in: fields.get('leaderboard_opt_in') === 'on',
    }

    try {
      const result = await apiRequest('/profile', {
        method: 'PATCH',
        token,
        body: JSON.stringify(payload),
      })
      setProfile(result.user)
      onUserUpdated(result.user)
      setProfileNotice(result.message)
    } catch (requestError) {
      setProfileError(requestError.message)
    } finally {
      setProfileBusy(false)
    }
  }

  async function updateProfilePhoto(event) {
    const photo = event.currentTarget.files?.[0]
    event.currentTarget.value = ''
    if (!photo) return

    setProfileError('')
    setProfileNotice('')
    setPhotoBusy(true)
    const payload = new FormData()
    payload.append('photo', photo)
    try {
      const result = await apiRequest('/profile/photo', {
        method: 'POST',
        token,
        body: payload,
      })
      setProfile(result.user)
      onUserUpdated(result.user)
      setProfileNotice(result.message)
    } catch (requestError) {
      setProfileError(requestError.message)
    } finally {
      setPhotoBusy(false)
    }
  }

  return (
    <main className="dashboard-page">
      <div className="dashboard-shell">
        <div className="dashboard-welcome">
          <div>
            <span className="dash-kicker"><span className="eyebrow-dot" /> {isAdminRoute ? 'ADMINISTRATION' : 'YOUR STUDY SPACE'}</span>
            <h1>{isAdminRoute ? <>Welcome, {greeting}. <span>Admin dashboard</span></> : <>Hi, {greeting}. <span>You’re right on time.</span></>}</h1>
            <p>{isAdminRoute ? 'Your staff access is active. Review the learning catalogue and available platform data below.' : 'Every question you practice is a step toward the nurse you’re becoming.'}</p>
          </div>
          <div className="dashboard-profile-actions"><div className="profile-chip">{profile.avatar_url ? <img className="profile-avatar profile-photo-avatar" src={profile.avatar_url} alt="" /> : <span className="profile-avatar">{greeting.charAt(0).toUpperCase()}</span>}<span><b>{profile.name}</b><small>{user.role}</small></span></div><button className="button button-outline dashboard-edit-profile" onClick={() => { setProfileOpen(true); setProfileError(''); setProfileNotice('') }}><Icon name="settings" size={15} /> Edit profile</button></div>
        </div>
        <div className="dashboard-role-banner">
          <span className="role-banner-icon"><Icon name={isStaff ? 'shield' : 'heart'} /></span>
          <span><b>{isStaff ? `${user.role === 'admin' ? 'Administrator' : 'Editor'} account` : 'Your student account is ready'}</b><small>{isStaff ? 'You’re signed in with your assigned staff role.' : 'Pick a subject below and make today count, at your own pace.'}</small></span>
          <span className="role-badge">{user.role}</span>
        </div>
        {!isStaff && <GamificationPanel token={token} />}
        {!isStaff && <LeaderboardPanel token={token} />}
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
      {profileOpen && <div className="auth-backdrop profile-editor-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !profileBusy && !photoBusy) setProfileOpen(false) }}>
        <section className="auth-card profile-editor-card" role="dialog" aria-modal="true" aria-labelledby="profile-editor-title">
          <button className="auth-close" onClick={() => setProfileOpen(false)} aria-label="Close profile editor" disabled={profileBusy || photoBusy}><Icon name="close" /></button>
          <div className="auth-eyebrow">ACCOUNT SETTINGS</div>
          <h2 id="profile-editor-title">Your profile</h2>
          <p className="auth-intro">Keep your personal information up to date.</p>
          {profileError && <div className="form-error" role="alert">{profileError}</div>}
          {profileNotice && <div className="profile-success" role="status"><Icon name="check" size={16} /> {profileNotice}</div>}
          <div className="profile-photo-control">
            {profile.avatar_url ? <img className="profile-avatar profile-photo-avatar" src={profile.avatar_url} alt="" /> : <span className="profile-avatar">{greeting.charAt(0).toUpperCase()}</span>}
            <div><b>Profile photo</b><small>JPEG, PNG, or WebP · up to 2 MB</small><label className="button button-outline profile-photo-button">{photoBusy ? 'Uploading…' : 'Choose photo'}<input type="file" accept="image/jpeg,image/png,image/webp" onChange={updateProfilePhoto} disabled={photoBusy || profileBusy} /></label></div>
          </div>
          <form className="auth-form profile-editor-form" onSubmit={updateProfile}>
            <label>Full name<input name="name" required maxLength="255" defaultValue={profile.name} autoComplete="name" /></label>
            <label>Email address<input type="email" value={profile.email} readOnly autoComplete="email" /></label>
            <div className="admin-form-row">
              <label>Phone number<input name="phone" type="tel" maxLength="30" defaultValue={profile.phone || ''} autoComplete="tel" /></label>
              <label>Date of birth<input name="date_of_birth" type="date" max={today} defaultValue={profile.date_of_birth || ''} /></label>
            </div>
            <label>Gender<select name="gender" defaultValue={profile.gender || ''}><option value="">Prefer not to specify</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option><option value="prefer_not_to_say">Prefer not to say</option></select></label>
            <label>Address<textarea name="address" maxLength="1000" defaultValue={profile.address || ''} rows="3" /></label>
            <label className="profile-leaderboard-opt-in"><input name="leaderboard_opt_in" type="checkbox" defaultChecked={profile.leaderboard_opt_in} /> <span><b>Show me on the public leaderboard</b><small>If enabled, your name and profile photo can appear with your score and test count. You can opt out any time.</small></span></label>
            <button className="button button-primary auth-submit" type="submit" disabled={profileBusy || photoBusy}>{profileBusy ? 'Saving profile…' : 'Save profile'} {!profileBusy && <Icon name="arrow" size={17} />}</button>
          </form>
        </section>
      </div>}
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
            : <Dashboard user={session.user} token={session.token} onLogout={handleLogout} busy={sessionBusy} sessionError={sessionError} onNavigate={navigateTo} onUserUpdated={(updatedUser) => setSession((current) => current ? { ...current, user: updatedUser } : current)} />
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
