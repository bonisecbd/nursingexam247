/**
 * Option payloads arrive either as a JSON string or as an array depending on
 * the endpoint; normalise both and never let a malformed value break rendering.
 */
export function parseOptions(value) {
  let options = value
  if (typeof options === 'string') {
    try {
      options = JSON.parse(options)
    } catch {
      return []
    }
  }
  return Array.isArray(options) ? options : []
}
