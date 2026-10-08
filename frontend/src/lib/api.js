/**
 * Single API client for the NurseExam247 Laravel backend.
 *
 * - Bearer token is read from localStorage on every request.
 * - Errors are normalised into `ApiError` (message, status, body, field errors).
 * - A 401 on a protected route clears the session and hands control to the
 *   registered unauthorized handler (the auth provider redirects to /login).
 */

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/+$/, '')

export const TOKEN_KEY = 'nurseexam247_token'
export const USER_KEY = 'nurseexam247_user'

const AUTH_PATHS = ['/auth/login', '/auth/register', '/auth/forgot-password', '/auth/reset-password']

const STATUS_MESSAGES = {
  0: 'We could not reach the server. Check your connection and try again.',
  400: 'The request was invalid. Please review your input and try again.',
  401: 'Your session has expired. Please log in again.',
  403: 'You do not have access to this resource.',
  404: 'We could not find what you were looking for.',
  409: 'This conflicts with something already in progress. Refresh the page and try again.',
  410: 'This attempt has expired and can no longer be edited.',
  422: 'Please check the form and try again.',
  429: 'Too many requests. Please wait a moment and try again.',
  500: 'Something went wrong on our side. Please try again.',
  503: 'The service is temporarily unavailable. Please try again shortly.',
}

export class ApiError extends Error {
  constructor(message, { status = 0, data = null, errors = null, cause = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.data = data
    this.errors = errors || null
    if (cause) this.cause = cause
  }

  /** Validation messages keyed by field, when the API returned a 422 body. */
  get fieldErrors() {
    return this.errors && typeof this.errors === 'object' ? this.errors : {}
  }
}

let unauthorizedHandler = null

/** Registered by the auth provider so a 401 can clear state and redirect. */
export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler
}

export function getToken() {
  try {
    return window.localStorage.getItem(TOKEN_KEY) || ''
  } catch {
    return ''
  }
}

export function readStoredUser() {
  try {
    const raw = window.localStorage.getItem(USER_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export function saveSession(token, user) {
  try {
    window.localStorage.setItem(TOKEN_KEY, token)
    if (user) window.localStorage.setItem(USER_KEY, JSON.stringify(user))
  } catch {
    /* storage unavailable (private mode) — session stays in memory */
  }
}

export function clearSession() {
  try {
    window.localStorage.removeItem(TOKEN_KEY)
    window.localStorage.removeItem(USER_KEY)
  } catch {
    /* ignore */
  }
}

function firstValidationMessage(errors) {
  if (!errors || typeof errors !== 'object') return null
  const first = Object.values(errors)[0]
  if (Array.isArray(first)) return first[0] || null
  return typeof first === 'string' ? first : null
}

/** Builds `?a=1&b=2` from a plain object, skipping empty values. */
export function qs(params = {}) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    search.set(key, String(value))
  })
  const text = search.toString()
  return text ? `?${text}` : ''
}

export async function apiRequest(path, options = {}) {
  const { method = 'GET', body, headers: extraHeaders, token, signal, timeout } = options
  const authToken = token !== undefined ? token : getToken()
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData

  const headers = {
    Accept: 'application/json',
    ...(body !== undefined && body !== null && !isFormData ? { 'Content-Type': 'application/json' } : {}),
    ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
    ...extraHeaders,
  }

  const controller = typeof AbortController !== 'undefined' ? new AbortController() : null
  const timer = controller && timeout ? setTimeout(() => controller.abort(), timeout) : null
  if (controller && signal) {
    if (signal.aborted) controller.abort()
    else signal.addEventListener('abort', () => controller.abort(), { once: true })
  }

  let response
  try {
    response = await fetch(`${API_BASE}${path}`, {
      method,
      headers,
      signal: controller ? controller.signal : signal,
      body: body === undefined || body === null ? undefined : isFormData ? body : JSON.stringify(body),
    })
  } catch (cause) {
    if (timer) clearTimeout(timer)
    if (cause && cause.name === 'AbortError') throw cause
    throw new ApiError(STATUS_MESSAGES[0], { status: 0, cause })
  }
  if (timer) clearTimeout(timer)

  const text = await response.text().catch(() => '')
  let data = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = null
    }
  }

  if (!response.ok) {
    const message =
      firstValidationMessage(data && data.errors) ||
      (data && data.message) ||
      STATUS_MESSAGES[response.status] ||
      `Request failed (${response.status})`
    const error = new ApiError(message, {
      status: response.status,
      data,
      errors: data && data.errors ? data.errors : null,
    })
    if (response.status === 401) {
      clearSession()
      const fromAuthEndpoint = AUTH_PATHS.some((authPath) => path.startsWith(authPath))
      if (authToken && !fromAuthEndpoint && typeof unauthorizedHandler === 'function') {
        unauthorizedHandler(error)
      }
    }
    throw error
  }

  return data === null ? {} : data
}

/**
 * Endpoint map. Pages use these instead of hand-writing paths so the whole app
 * shares one contract.
 */
export const api = {
  /* auth */
  register: (payload) => apiRequest('/auth/register', { method: 'POST', body: payload }),
  login: (payload) => apiRequest('/auth/login', { method: 'POST', body: payload }),
  logout: () => apiRequest('/auth/logout', { method: 'POST' }),
  me: () => apiRequest('/auth/me'),
  forgotPassword: (payload) => apiRequest('/auth/forgot-password', { method: 'POST', body: payload }),
  resetPassword: (payload) => apiRequest('/auth/reset-password', { method: 'POST', body: payload }),

  /* profile */
  profile: () => apiRequest('/profile'),
  updateProfile: (payload) => apiRequest('/profile', { method: 'PATCH', body: payload }),
  uploadPhoto: (file) => {
    const payload = new FormData()
    payload.append('photo', file)
    return apiRequest('/profile/photo', { method: 'POST', body: payload })
  },

  /* catalogue */
  subjects: (params = {}) => apiRequest(`/subjects${qs({ per_page: 100, ...params })}`),
  subject: (id) => apiRequest(`/subjects/${id}`),
  tests: (params = {}) => apiRequest(`/tests${qs({ status: 'published', per_page: 15, ...params })}`),
  test: (id) => apiRequest(`/tests/${id}`),
  unlockStatus: (id) => apiRequest(`/tests/${id}/unlock-status`),

  /* attempts */
  startAttempt: (testId) => apiRequest('/attempts', { method: 'POST', body: { test_id: testId } }),
  resumeAttempt: (attemptId) => apiRequest(`/attempts/${attemptId}`),
  attemptQuestion: (attemptId, questionItemId) => apiRequest(`/attempts/${attemptId}/questions/${questionItemId}`),
  saveAnswer: (attemptId, questionItemId, selectedOption) =>
    apiRequest(`/attempts/${attemptId}/answers/${questionItemId}`, {
      method: 'PUT',
      body: { selected_option: selectedOption },
    }),
  submitAttempt: (attemptId) => apiRequest(`/attempts/${attemptId}/submit`, { method: 'POST' }),
  attemptHistory: (params = {}) => apiRequest(`/attempts${qs({ per_page: 15, ...params })}`),

  /* results */
  result: (attemptId) => apiRequest(`/results/${attemptId}`),
  resultSummary: (attemptId) => apiRequest(`/results/${attemptId}/summary`),
  solutions: (attemptId, params = {}) => apiRequest(`/results/${attemptId}/solutions${qs({ per_page: 15, ...params })}`),

  /* analytics (documented but not routed by every backend build) */
  analyticsOverview: () => apiRequest('/analytics/overview'),
}
