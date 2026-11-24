/**
 * Thin fetch wrapper for the API: JSON in and out, Bearer token, typed errors.
 * The API lives on the same origin (nginx), so there is no CORS to configure.
 */

import { locale } from '@/i18n'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors: Record<string, string[]> = {},
    public readonly body: Record<string, unknown> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** First validation message for a field, if any. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

interface HttpOptions {
  method?: 'GET' | 'POST' | 'DELETE'
  body?: unknown
  query?: Record<string, string | number | undefined>
}

let tokenProvider: () => string | null = () => null
let onUnauthorized: () => void = () => {}

export function configureHttp(options: { token: () => string | null; unauthorized: () => void }): void {
  tokenProvider = options.token
  onUnauthorized = options.unauthorized
}

export async function http<T>(path: string, options: HttpOptions = {}): Promise<T> {
  // The API answers validation and error messages in the chosen language.
  const headers: Record<string, string> = { Accept: 'application/json', 'Accept-Language': locale.value }
  const token = tokenProvider()
  if (token) headers.Authorization = `Bearer ${token}`

  let body: BodyInit | undefined
  if (options.body instanceof FormData) {
    body = options.body // the browser sets the multipart boundary
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }

  const response = await fetch(`/api/v1${path}${toQueryString(options.query)}`, {
    method: options.method ?? 'GET',
    headers,
    body,
  })

  if (response.status === 204) return undefined as T

  const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>

  if (!response.ok) {
    if (response.status === 401 && token) onUnauthorized()
    throw new ApiError(
      response.status,
      typeof payload.message === 'string' ? payload.message : `Request failed (${response.status})`,
      (payload.errors as Record<string, string[]> | undefined) ?? {},
      payload,
    )
  }

  return payload as T
}

function toQueryString(query?: Record<string, string | number | undefined>): string {
  if (!query) return ''
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== '') params.set(key, String(value))
  }
  const text = params.toString()
  return text ? `?${text}` : ''
}
