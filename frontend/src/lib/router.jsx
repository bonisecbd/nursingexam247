/**
 * Tiny history-based router (no third-party dependency).
 *
 * - RouterProvider keeps the current pathname/search in React state.
 * - navigate(to, { replace, preventScroll }) pushes or replaces history entries.
 * - Link renders a real <a> so middle-click / keyboard shortcuts keep working.
 */
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

const RouterContext = createContext(null)

function readLocation() {
  return {
    pathname: window.location.pathname || '/',
    search: window.location.search || '',
  }
}

export function RouterProvider({ children }) {
  const [location, setLocation] = useState(readLocation)

  useEffect(() => {
    const handlePopState = () => setLocation(readLocation())
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  const navigate = useCallback((to, options = {}) => {
    const target = typeof to === 'string' ? to : `${to.pathname || '/'}${to.search || ''}`
    const current = `${window.location.pathname}${window.location.search}`
    if (target === current) return
    if (options.replace) window.history.replaceState({}, '', target)
    else window.history.pushState({}, '', target)
    setLocation(readLocation())
    if (!options.preventScroll) window.scrollTo({ top: 0, left: 0 })
  }, [])

  const value = useMemo(
    () => ({
      pathname: location.pathname,
      search: location.search,
      params: new URLSearchParams(location.search),
      navigate,
    }),
    [location.pathname, location.search, navigate],
  )

  return <RouterContext.Provider value={value}>{children}</RouterContext.Provider>
}

export function useRouter() {
  const context = useContext(RouterContext)
  if (!context) throw new Error('useRouter must be used inside <RouterProvider>')
  return context
}

export function useNavigate() {
  return useRouter().navigate
}

export function useLocation() {
  const { pathname, search, params } = useRouter()
  return { pathname, search, params }
}

export function Link({ to, children, onClick, ...rest }) {
  const navigate = useNavigate()

  function handleClick(event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return
    }
    event.preventDefault()
    if (typeof onClick === 'function') onClick(event)
    navigate(to)
  }

  return (
    <a href={to} onClick={handleClick} {...rest}>
      {children}
    </a>
  )
}
