/** Shared presentational building blocks: states, badges, modal, pagination. */
import { useEffect, useRef } from 'react'
import Icon from './Icon'

export function Loading({ label = 'Loading…' }) {
  return (
    <div className="state-box" role="status" aria-live="polite">
      <span className="spinner" aria-hidden="true" />
      <p>{label}</p>
    </div>
  )
}

export function ErrorState({ message, onRetry, retryLabel = 'Try again', tone = 'danger' }) {
  return (
    <div className={`state-box state-box-${tone}`} role="alert">
      <span className="state-icon" aria-hidden="true">
        <Icon name="alert" size={22} />
      </span>
      <p>{message || 'Something went wrong while loading this page.'}</p>
      {onRetry && (
        <button type="button" className="btn btn-outline btn-sm" onClick={onRetry}>
          <Icon name="refresh" size={15} /> {retryLabel}
        </button>
      )}
    </div>
  )
}

export function EmptyState({ title, hint, action, icon = 'inbox' }) {
  return (
    <div className="state-box state-box-empty">
      <span className="state-icon" aria-hidden="true">
        <Icon name={icon} size={22} />
      </span>
      <h3>{title}</h3>
      {hint && <p>{hint}</p>}
      {action}
    </div>
  )
}

export function Badge({ tone = 'neutral', icon, children }) {
  return (
    <span className={`badge badge-${tone}`}>
      {icon && <Icon name={icon} size={12} />}
      {children}
    </span>
  )
}

export function Avatar({ name = '', avatarUrl, size = 'md' }) {
  const initial = (name || '?').trim().charAt(0).toUpperCase() || '?'
  if (avatarUrl) {
    return <img className={`avatar avatar-${size}`} src={avatarUrl} alt="" />
  }
  return (
    <span className={`avatar avatar-${size}`} aria-hidden="true">
      {initial}
    </span>
  )
}

/**
 * Accessible confirm dialog: Escape closes, backdrop click closes, focus moves
 * into the dialog when it opens.
 */
export function Modal({ title, description, children, footer, onClose, tone = 'default' }) {
  const dialogRef = useRef(null)
  const closeRef = useRef(null)

  useEffect(() => {
    closeRef.current?.focus()
    function handleKeyDown(event) {
      if (event.key === 'Escape' && typeof onClose === 'function') onClose()
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && typeof onClose === 'function') onClose()
      }}
    >
      <section
        className={`modal modal-${tone}`}
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        ref={dialogRef}
      >
        <header className="modal-head">
          <h2 id="modal-title">{title}</h2>
          <button type="button" className="icon-button" onClick={onClose} aria-label="Close dialog" ref={closeRef}>
            <Icon name="close" size={18} />
          </button>
        </header>
        {description && <p className="modal-description">{description}</p>}
        <div className="modal-body">{children}</div>
        {footer && <footer className="modal-foot">{footer}</footer>}
      </section>
    </div>
  )
}

export function Pagination({ page, lastPage, total, from, to, onPageChange, label = 'items' }) {
  if (!lastPage || lastPage <= 1) return null
  return (
    <nav className="pagination" aria-label="Pagination">
      <p className="pagination-summary">
        {typeof from === 'number' && typeof to === 'number' ? `Showing ${from}–${to} of ${total}` : `Page ${page} of ${lastPage}`}
        {` ${label}`}
      </p>
      <div className="pagination-actions">
        <button
          type="button"
          className="btn btn-outline btn-sm"
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
        >
          <Icon name="chevronLeft" size={15} /> Previous
        </button>
        <span className="pagination-page" aria-hidden="true">
          {page} / {lastPage}
        </span>
        <button
          type="button"
          className="btn btn-outline btn-sm"
          onClick={() => onPageChange(page + 1)}
          disabled={page >= lastPage}
        >
          Next <Icon name="chevronRight" size={15} />
        </button>
      </div>
    </nav>
  )
}
