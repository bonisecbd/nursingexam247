import { useEffect, useRef, useState } from 'react'

import { api, ApiError } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useNavigate } from '../lib/router'
import Icon from '../components/Icon'
import { Avatar, Badge } from '../components/ui'

const MAX_PHOTO_BYTES = 2 * 1024 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']

const GENDERS = [
  { value: '', label: 'Select' },
  { value: 'female', label: 'Female' },
  { value: 'male', label: 'Male' },
  { value: 'other', label: 'Other' },
  { value: 'prefer_not_to_say', label: 'Prefer not to say' },
]

function FieldError({ message }) {
  if (!message) return null
  return (
    <p className="field-error" role="alert">
      {message}
    </p>
  )
}

/** Laravel returns `{ field: ["msg"] }`; tolerate a plain string too. */
function fieldMessage(errors, field) {
  const value = errors[field]
  if (Array.isArray(value)) return value[0] || ''
  return typeof value === 'string' ? value : ''
}

export default function Profile() {
  const { user, updateUser, logout, sessionError, clearSessionError } = useAuth()
  const navigate = useNavigate()
  const fileRef = useRef(null)

  const [form, setForm] = useState({
    name: '',
    phone: '',
    date_of_birth: '',
    gender: '',
    address: '',
    leaderboard_opt_in: false,
  })
  const [errors, setErrors] = useState({})
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [photoError, setPhotoError] = useState('')
  const [loggingOut, setLoggingOut] = useState(false)

  useEffect(() => {
    if (!user) return
    setForm({
      name: user.name || '',
      phone: user.phone || '',
      date_of_birth: user.date_of_birth || '',
      gender: user.gender || '',
      address: user.address || '',
      leaderboard_opt_in: Boolean(user.leaderboard_opt_in),
    })
  }, [user])

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }))
    setErrors((current) => ({ ...current, [field]: undefined }))
    setNotice('')
    if (sessionError) clearSessionError()
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (saving) return
    setNotice('')
    setErrors({})
    setSaving(true)
    try {
      const payload = await api.updateProfile({
        name: form.name.trim(),
        phone: form.phone.trim() || null,
        date_of_birth: form.date_of_birth || null,
        gender: form.gender || null,
        address: form.address.trim() || null,
        leaderboard_opt_in: form.leaderboard_opt_in,
      })
      updateUser(payload.user || { ...user, ...form })
      setNotice(payload.message || 'Profile updated successfully.')
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.fieldErrors)
      } else {
        setErrors({ general: error.message })
      }
    } finally {
      setSaving(false)
    }
  }

  async function handlePhoto(event) {
    const file = event.target.files && event.target.files[0]
    event.target.value = ''
    if (!file) return
    setPhotoError('')
    setNotice('')

    if (!ACCEPTED_TYPES.includes(file.type)) {
      setPhotoError('Please choose a JPEG, PNG or WebP image.')
      return
    }
    if (file.size > MAX_PHOTO_BYTES) {
      setPhotoError('That image is larger than 2 MB. Please pick a smaller file.')
      return
    }

    setUploading(true)
    try {
      const payload = await api.uploadPhoto(file)
      updateUser(payload.user || user)
      setNotice(payload.message || 'Profile photo updated.')
    } catch (error) {
      setPhotoError(error.message)
    } finally {
      setUploading(false)
    }
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

  return (
    <main className="page">
      <div className="container">
        <div className="page-head">
          <div>
            <span className="kicker">Your account</span>
            <h1>Profile &amp; settings</h1>
            <p>Keep your details up to date so results, certificates and leaderboards stay accurate.</p>
          </div>
        </div>

        {(notice || sessionError) && (
          <div className="alert alert-success" role="status">
            <Icon name="check" size={16} /> <span>{notice || sessionError}</span>
          </div>
        )}
        {errors.general && (
          <div className="alert alert-error" role="alert">
            <Icon name="alert" size={16} /> <span>{errors.general}</span>
          </div>
        )}

        <div className="account-grid">
          <aside className="account-card">
            <Avatar name={user?.name} avatarUrl={user?.avatar_url} size="lg" />
            <h2>{user?.name}</h2>
            <p className="account-email">{user?.email}</p>

            <div className="account-badges">
              <Badge tone="primary" icon="shield">
                {user?.role || 'student'}
              </Badge>
              <Badge tone="success" icon="check">
                Verified email
              </Badge>
            </div>

            <div className="photo-control">
              <input
                id="photo-input"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                ref={fileRef}
                onChange={handlePhoto}
                aria-describedby="photo-hint"
              />
              <label className="btn btn-outline btn-sm" htmlFor="photo-input">
                <Icon name="camera" size={15} /> {uploading ? 'Uploading…' : 'Change photo'}
              </label>
              <p className="photo-hint" id="photo-hint">
                JPEG, PNG or WebP — up to 2 MB.
              </p>
              {photoError && <FieldError message={photoError} />}
            </div>
          </aside>

          <div>
            <form className="card account-form" onSubmit={handleSubmit} noValidate>
              <div className="card-head">
                <h2>Personal information</h2>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="profile-name">Full name</label>
                  <input
                    id="profile-name"
                    className="form-control"
                    type="text"
                    value={form.name}
                    onChange={(event) => updateField('name', event.target.value)}
                    autoComplete="name"
                    required
                  />
                  <FieldError message={fieldMessage(errors, 'name')} />
                </div>
                <div className="form-group">
                  <label htmlFor="profile-phone">Phone</label>
                  <input
                    id="profile-phone"
                    className="form-control"
                    type="tel"
                    value={form.phone}
                    onChange={(event) => updateField('phone', event.target.value)}
                    placeholder="+8801XXXXXXXXX"
                    autoComplete="tel"
                  />
                  <FieldError message={fieldMessage(errors, 'phone')} />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="profile-dob">Date of birth</label>
                  <input
                    id="profile-dob"
                    className="form-control"
                    type="date"
                    value={form.date_of_birth}
                    onChange={(event) => updateField('date_of_birth', event.target.value)}
                  />
                  <FieldError message={fieldMessage(errors, 'date_of_birth')} />
                </div>
                <div className="form-group">
                  <label htmlFor="profile-gender">Gender</label>
                  <select
                    id="profile-gender"
                    className="form-control"
                    value={form.gender}
                    onChange={(event) => updateField('gender', event.target.value)}
                  >
                    {GENDERS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  <FieldError message={fieldMessage(errors, 'gender')} />
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="profile-address">Address</label>
                <textarea
                  id="profile-address"
                  className="form-control"
                  rows={3}
                  value={form.address}
                  onChange={(event) => updateField('address', event.target.value)}
                  placeholder="District, division, Bangladesh"
                />
                <FieldError message={fieldMessage(errors, 'address')} />
              </div>

              <div className="form-group">
                <label className="label-row" htmlFor="profile-leaderboard">
                  <input
                    id="profile-leaderboard"
                    type="checkbox"
                    checked={form.leaderboard_opt_in}
                    onChange={(event) => updateField('leaderboard_opt_in', event.target.checked)}
                  />
                  <span>Show me on the public leaderboard</span>
                </label>
                <p className="field-hint">Your name and score appear on the leaderboard when this is on.</p>
              </div>

              <div className="account-actions">
                <button type="submit" className="btn btn-primary" disabled={saving}>
                  <Icon name="check" size={16} /> {saving ? 'Saving…' : 'Save changes'}
                </button>
                <button
                  type="button"
                  className="btn btn-ghost"
                  onClick={() => {
                    setForm({
                      name: user?.name || '',
                      phone: user?.phone || '',
                      date_of_birth: user?.date_of_birth || '',
                      gender: user?.gender || '',
                      address: user?.address || '',
                      leaderboard_opt_in: Boolean(user?.leaderboard_opt_in),
                    })
                    setErrors({})
                    setNotice('')
                  }}
                  disabled={saving}
                >
                  Reset
                </button>
              </div>
            </form>

            <section className="card account-form-card">
              <div className="card-pad">
                <div className="card-head">
                  <h2>Session</h2>
                </div>
                <div className="danger-zone">
                  <p>
                    Signed in as <strong>{user?.email}</strong>. Signing out clears this device&apos;s session token.
                  </p>
                  <button type="button" className="btn btn-danger btn-sm" onClick={handleLogout} disabled={loggingOut}>
                    <Icon name="logout" size={15} /> {loggingOut ? 'Signing out…' : 'Sign out'}
                  </button>
                </div>
                <div className="danger-zone">
                  <p>Finished a test? Your scores and written solutions live in the attempt history.</p>
                  <button type="button" className="btn btn-outline btn-sm" onClick={() => navigate('/history')}>
                    <Icon name="clock" size={15} /> Open history
                  </button>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </main>
  )
}
