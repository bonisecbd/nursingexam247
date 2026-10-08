import { useEffect, useState } from 'react'
import '../styles/auth.css'

import { useAuth } from '../lib/auth'
import { Link, useLocation, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { AuthShell } from '../components/Layout'

export default function Login() {
  const { login, user, sessionError, clearSessionError } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [showPassword, setShowPassword] = useState(false)

  const nextParam = location.params.get('next')
  const nextPath = nextParam && nextParam.startsWith('/') && !nextParam.startsWith('//') ? nextParam : null

  useEffect(() => {
    if (user) navigate(nextPath || '/dashboard', { replace: true })
  }, [user, navigate, nextPath])

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setFieldErrors({})
    clearSessionError()
    const form = new FormData(event.currentTarget)
    setBusy(true)
    try {
      await login(String(form.get('email') || '').trim(), String(form.get('password') || ''))
      navigate(nextPath || '/dashboard', { replace: true })
    } catch (requestError) {
      setError(requestError.message)
      setFieldErrors(requestError.fieldErrors || {})
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell
      eyebrow="Welcome back"
      title="Log in to your account"
      subtitle="Pick up where you left off and keep your preparation moving."
      footer={
        <>
          New to NurseExam247?{' '}
          <Link to="/register" className="link-button">
            Create a free account
          </Link>
        </>
      }
    >
      {(error || sessionError) && (
        <div className="alert alert-error" role="alert">
          <Icon name="alert" size={16} /> <span>{error || sessionError}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="login-email">Email address</label>
          <input
            id="login-email"
            className="form-control"
            type="email"
            name="email"
            autoComplete="email"
            placeholder="you@example.com"
            required
            maxLength={255}
          />
          {fieldErrors.email && <span className="field-error">{fieldErrors.email[0]}</span>}
        </div>

        <div className="form-group">
          <div className="label-row">
            <label htmlFor="login-password">Password</label>
            <Link to="/forgot-password" className="forgot-link">
              Forgot password?
            </Link>
          </div>
          <span className="input-wrap">
            <input
              id="login-password"
              className="form-control"
              type={showPassword ? 'text' : 'password'}
              name="password"
              autoComplete="current-password"
              placeholder="Enter your password"
              required
              minLength={8}
            />
            <button
              type="button"
              className="password-toggle"
              onClick={() => setShowPassword((value) => !value)}
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              aria-pressed={showPassword}
            >
              <Icon name="eye" size={18} />
            </button>
          </span>
          {fieldErrors.password && <span className="field-error">{fieldErrors.password[0]}</span>}
        </div>

        <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={busy}>
          {busy ? 'Logging in…' : 'Log in'} {!busy && <Icon name="arrow" size={17} />}
        </button>
      </form>

      <div className="auth-divider">OR</div>

      <p className="auth-prompt">
        No account yet?{' '}
        <Link to="/register" className="link-button">
          Register now
        </Link>
      </p>

      <p className="auth-demo">
        <Icon name="info" size={15} />
        <span>
          Local demo login: <strong>test@example.com</strong> / <strong>password</strong>
        </span>
      </p>
    </AuthShell>
  )
}
