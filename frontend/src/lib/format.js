/** Small display formatters shared by the result, history and profile pages. */

export function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (!Number.isFinite(date.getTime())) return '—'
  return date.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (!Number.isFinite(date.getTime())) return '—'
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

/** "1h 04m 12s" / "4m 09s" / "42s" */
export function formatDuration(seconds) {
  const total = Math.max(0, Math.round(Number(seconds)))
  if (!Number.isFinite(total)) return '—'
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const secs = total % 60
  if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`
  if (minutes > 0) return `${minutes}m ${String(secs).padStart(2, '0')}s`
  return `${secs}s`
}

export function formatNumber(value, fallback = 0) {
  const number = Number(value)
  return Number.isFinite(number) ? number : fallback
}

export function formatPercentage(value) {
  const number = Number(value)
  if (!Number.isFinite(number)) return '0.0'
  return Math.min(100, Math.max(0, number)).toFixed(1)
}
