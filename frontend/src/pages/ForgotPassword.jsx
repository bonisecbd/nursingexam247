import { useState } from 'react'
import '../styles/auth.css'

import { api } from '../lib/api'
import { Link, useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { AuthShell } from '../components/Layout'

export default function ForgotPassword() {
  const navigate = useNavigate()
  const [step, setStep] = useState(1)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [notice, setNotice] = useState('')
  const [done, setDone] = useState(false)
  const [email, setEmail] = useState('')
  const [showPassword, setShowPassword] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setFieldErrors({})
    const form = new FormData(event.currentTarget)
    setBusy(true)

    try {
      if (step === 1) {
        const address = String(form.get('email') || '').trim()
        await api.forgotPassword({ email: address })
        setEmail(address)
        setStep(2)
        setNotice('If an account exists for that email, a 6-digit reset code has been sent. The code is valid for 10 minutes.')
      } else {
        const password = String(form.get('password') || '')
        const confirmation = String(form.get('password_confirmation') || '')
        if (password !== confirmation) {
          setFieldErrors({ password_confirmation: ['The password confirmation does not match.'] })
          return
        }
        await api.resetPassword({
          email,
          otp: String(form.get('otp') || '').trim(),
          password,
          password_confirmation: confirmation,
        })
        setDone(true)
      }
    } catch (requestError) {
      setError(requestError.message)
      setFieldErrors(requestError.fieldErrors || {})
    } finally {
      setBusy(false)
    }
  }

  if (done) {
    return (
      <AuthShell eyebrow="Account recovery" title="Password updated" subtitle="Your password has been changed and all existing sessions were signed out.">
        <div className="alert alert-success" role="status">
          <Icon name="check" size={16} /> <span>You can now log in with your new password.</span>
        </div>
        <button className="btn btn-primary btn-block btn-lg" type="button" onClick={() => navigate('/login')}>
          Go to log in <Icon name="arrow" size={17} />
        </button>
      </AuthShell>
    )
  }

  return (
    <AuthShell
      eyebrow="Account recovery"
      title={step === 1 ? 'Reset your password' : 'Enter your reset code'}
      subtitle={
        step === 1
          ? 'We will email you a 6-digit code to confirm it is you.'
          : 'Enter the code from your email and choose a new password.'
      }
      footer={
        <>
          Remembered it?{' '}
          <Link to="/login" className="link-button">
            Back to log in
          </Link>
        </>
      }
    >
      <div className="auth-steps" aria-hidden="true">
        <span className={`auth-step${step === 1 ? ' is-current' : ' is-done'}`}>
          <i>{step > 1 ? '✓' : '1'}</i> Email
        </span>
        <span className="auth-step-sep" />
        <span className={`auth-step${step === 2 ? ' is-current' : ''}`}>
          <i>2</i> Code &amp; new password
        </span>
      </div>

      {notice && step === 2 && (
        <div className="alert alert-success" role="status">
          <Icon name="check" size={16} /> <span>{notice}</span>
        </div>
      )}
      {error && (
        <div className="alert alert-error" role="alert" style={{ marginTop: notice && step === 2 ? 12 : 0 }}>
          <Icon name="alert" size={16} /> <span>{error}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} noValidate style={{ marginTop: 18 }}>
        <div className="form-group">
          <label htmlFor="forgot-email">Email address</label>
          <input
            id="forgot-email"
            className="form-control"
            type="email"
            name="email"
            autoComplete="email"
            placeholder="you@example.com"
            required
            maxLength={255}
            readOnly={step === 2}
            value={step === 2 ? email : undefined}
            onChange={step === 1 ? (event) => setEmail(event.target.value) : undefined}
          />
          {fieldErrors.email && <span className="field-error">{fieldErrors.email[0]}</span>}
        </div>

        {step === 2 && (
          <>
            <div className="form-group">
              <label htmlFor="forgot-otp">6-digit reset code</label>
              <input
                id="forgot-otp"
                className="form-control"
                type="text"
                name="otp"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="123456"
                required
                minLength={6}
                maxLength={6}
                pattern="[0-9]{6}"
              />
              {fieldErrors.otp && <span className="field-error">{fieldErrors.otp[0]}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="forgot-password">New password</label>
              <span className="input-wrap">
                <input
                  id="forgot-password"
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
              <span className="field-hint">Use uppercase, lowercase, a number and a symbol.</span>
              {fieldErrors.password && <span className="field-error">{fieldErrors.password[0]}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="forgot-password-confirmation">Confirm new password</label>
              <input
                id="forgot-password-confirmation"
                className="form-control"
                type={showPassword ? 'text' : 'password'}
                name="password_confirmation"
                autoComplete="new-password"
                placeholder="Repeat the new password"
                required
                minLength={8}
              />
              {fieldErrors.password_confirmation && (
                <span className="field-error">{fieldErrors.password_confirmation[0]}</span>
              )}
            </div>
          </>
        )}

        <div className="field-note">
          <Icon name="shield" size={16} />
          <span>For your privacy we show the same confirmation whether or not the email is registered.</span>
        </div>

        <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={busy} style={{ marginTop: 16 }}>
          {busy ? 'Please wait…' : step === 1 ? 'Send reset code' : 'Update my password'} {!busy && <Icon name="arrow" size={17} />}
        </button>
      </form>

      {step === 2 && (
        <p className="auth-prompt">
          <button
            type="button"
            className="link-button"
            onClick={() => {
              setStep(1)
              setError('')
              setNotice('')
              setFieldErrors({})
            }}
          >
            Use a different email
          </button>
        </p>
      )}
    </AuthShell>
  )
}
