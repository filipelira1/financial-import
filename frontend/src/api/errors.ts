import { ApiError } from './http'

/** A sentence the user can act on, for any failed request. */
export function describeError(caught: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (!(caught instanceof ApiError)) return 'Could not reach the server. Check your connection and try again.'
  if (caught.status === 429) return 'Too many requests. Please wait a minute and try again.'
  if (caught.status >= 500) return 'The server had a problem. Please try again in a moment.'
  return Object.values(caught.errors).flat()[0] ?? caught.message ?? fallback
}
