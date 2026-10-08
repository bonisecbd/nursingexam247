/**
 * Session state for the whole app: token + user in localStorage, restored on
 * boot through GET /api/auth/me.
 */
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { api, clearSession, getToken, readStoredUser, saveSession, setUnauthorizedHandler } from './api'
import { useNavigate } from './router'

const AuthContext = createContext(null)

const PUBLIC_PATHS = ['/', '/login', '/register', '/forgot-password']

function isPublicPath(pathname) {
  return PUBLIC_PATHS.some((path) => pathname === path)
}

export function AuthProvider({ children }) {
  const navigate = useNavigate()
  const [session, setSession] = useState(() => ({ token: getToken(), user: readStoredUser() }))
  const [ready, setReady] = useState(false)
  const [sessionError, setSessionError] = useState('')
  const navigateRef = useRef(navigate)

  useEffect(() => {
    navigateRef.current = navigate
  }, [navigate])

  /* A 401 from a protected endpoint clears the session and returns to /login. */
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setSession({ token: '', user: null })
      setSessionError('Your session expired. Please log in again.')
      const { pathname } = window.location
      if (!isPublicPath(pathname)) navigateRef.current('/login', { replace: true })
    })
    return () => setUnauthorizedHandler(null)
  }, [])

  /* Restore the session once on boot. */
  useEffect(() => {
    let active = true
    const token = getToken()
    if (!token) {
      setReady(true)
      return undefined
    }

    api
      .me()
      .then((payload) => {
        if (!active) return
        const user = payload.user || payload
        saveSession(token, user)
        setSession({ token, user })
      })
      .catch((error) => {
        if (!active) return
        if (error.status === 401) {
          clearSession()
          setSession({ token: '', user: null })
        } else if (error.status !== 0) {
          setSessionError('We could not refresh your profile. Some details may be out of date.')
        } else {
          setSessionError('You appear to be offline. Reconnect to load your account.')
        }
      })
      .finally(() => {
        if (active) setReady(true)
      })

    return () => {
      active = false
    }
  }, [])

  const persist = useCallback((token, user) => {
    saveSession(token, user)
    setSession({ token, user })
    setSessionError('')
  }, [])

  const login = useCallback(
    async (email, password) => {
      const payload = await api.login({ email, password })
      persist(payload.token, payload.user)
      return payload.user
    },
    [persist],
  )

  const register = useCallback(
    async (values) => {
      const payload = await api.register(values)
      persist(payload.token, payload.user)
      return payload.user
    },
    [persist],
  )

  const logout = useCallback(async () => {
    try {
      await api.logout()
    } catch {
      /* the token is discarded either way */
    }
    clearSession()
    setSession({ token: '', user: null })
    setSessionError('')
    navigate('/login', { replace: true })
  }, [navigate])

  const updateUser = useCallback(
    (user) => {
      saveSession(session.token, user)
      setSession((current) => ({ ...current, user }))
    },
    [session.token],
  )

  const value = useMemo(
    () => ({
      token: session.token,
      user: session.user,
      ready,
      sessionError,
      login,
      register,
      logout,
      updateUser,
      clearSessionError: () => setSessionError(''),
    }),
    [session.token, session.user, ready, sessionError, login, register, logout, updateUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
