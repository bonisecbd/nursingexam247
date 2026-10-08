import { useEffect, useState } from 'react'
import '../styles/auth.css'

import { useAuth } from '../lib/auth'
import { Link, useLocation, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { AuthShell } from '../components/Layout'

export default function Register() {
  const { register, user } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [showPassword, setShowPassword] = useState(false)
  const [referralCode, setReferralCode] = useState(() => location.params.get('ref') || '')

  useEffect(() => {
    if (user) navigate('/dashboard', { replace: true })
  }, [user, navigate])

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setFieldErrors({})
    const form = new FormData(event.currentTarget)
    const password = String(form.get('password') || '')
    const confirmation = String(form.get('password_confirmation') || '')

    if (password !== confirmation) {
      setFieldErrors({ password_confirmation: ['The password confirmation does not match.'] })
      return
    }

    const payload = {
      name: String(form.get('name') || '').trim(),
      email: String(form.get('email') || '').trim(),
      password,
      password_confirmation: confirmation,
    }
    const referral = String(form.get('referral_code') || '').trim()
    if (referral) payload.referral_code = referral

    setBusy(true)
    try {
      await register(payload)
      navigate('/dashboard', { replace: true })
    } catch (requestError) {
      setError(requestError.message)
      setFieldErrors(requestError.fieldErrors || {})
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell
      eyebrow="Get started"
      title="Create your free account"
      subtitle="Join and start practising real nursing model tests in minutes."
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="link-button">
            Log in
          </Link>
        </>
      }
    >
      {error && (
        <div className="alert alert-error" role="alert">
          <Icon name="alert" size={16} /> <span>{error}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="register-name">Full name</label>
          <input
            id="register-name"
            className="form-control"
            type="text"
            name="name"
            autoComplete="name"
            placeholder="e.g. Ayesha Rahman"
            required
            maxLength={255}
          />
          {fieldErrors.name && <span className="field-error">{fieldErrors.name[0]}</span>}
        </div>

        <div className="form-group">
          <label htmlFor="register-email">Email address</label>
          <input
            id="register-email"
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

        <div className="form-row">
          <div className="form-group">
            <label htmlFor="register-password">Password</label>
            <span className="input-wrap">
              <input
                id="register-password"
                className="form-control"
                type={showPassword ? 'text' : 'password'}
                name="password"
                autoComplete="new-password"
                placeholder="At least 8 characters"
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

          <div className="form-group">
            <label htmlFor="register-password-confirmation">Confirm password</label>
            <input
              id="register-password-confirmation"
              className="form-control"
              type={showPassword ? 'text' : 'password'}
              name="password_confirmation"
              autoComplete="new-password"
              placeholder="Type it again"
              required
              minLength={8}
            />
            {fieldErrors.password_confirmation && (
              <span className="field-error">{fieldErrors.password_confirmation[0]}</span>
            )}
          </div>
        </div>

        <div className="form-group">
          <label htmlFor="register-referral">Referral code (optional)</label>
          <input
            id="register-referral"
            className="form-control"
            type="text"
            name="referral_code"
            autoComplete="off"
            maxLength={24}
            placeholder="A friend’s code"
            value={referralCode}
            onChange={(event) => setReferralCode(event.target.value.toUpperCase())}
          />
        </div>

        <div className="field-note">
          <Icon name="shield" size={16} />
          <span>New accounts are created as <strong>Student</strong>. Admin and editor roles are assigned by the site team.</span>
        </div>

        <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={busy} style={{ marginTop: 16 }}>
          {busy ? 'Creating your account…' : 'Create my free account'} {!busy && <Icon name="arrow" size={17} />}
        </button>
      </form>

      <p className="auth-prompt">
        Already practising?{' '}
        <Link to="/login" className="link-button">
          Log in instead
        </Link>
      </p>
    </AuthShell>
  )
}
