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

function baseHeaders(): Record<string, string> {
  // The API answers validation and error messages in the chosen language.
  const headers: Record<string, string> = { Accept: 'application/json', 'Accept-Language': locale.value }
  const token = tokenProvider()
  if (token) headers.Authorization = `Bearer ${token}`
  return headers
}

export async function http<T>(path: string, options: HttpOptions = {}): Promise<T> {
  const headers = baseHeaders()
  const token = headers.Authorization

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

  if (!response.ok) throw toApiError(response, payload, Boolean(token))

  return payload as T
}

/**
 * Download a file from the API and hand it to the browser as a save.
 * The API needs the Bearer token, so a plain link cannot do it: the file is fetched, then saved
 * from memory under the name the server gave (Content-Disposition).
 */
export async function download(path: string, query?: Record<string, string | number | undefined>, fallbackName = 'download'): Promise<void> {
  const headers = baseHeaders()
  const response = await fetch(`/api/v1${path}${toQueryString(query)}`, { headers })

  if (!response.ok) {
    const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>
    throw toApiError(response, payload, Boolean(headers.Authorization))
  }

  const name = fileNameFrom(response.headers.get('Content-Disposition')) ?? fallbackName
  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = name
  document.body.append(link)
  link.click()
  link.remove()
  // Give the browser a moment to start the save before the memory is released.
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

/** The file name from a Content-Disposition header (plain or RFC 5987 form). */
export function fileNameFrom(header: string | null): string | null {
  if (!header) return null
  const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header)
  if (encoded) return decodeURIComponent(encoded[1]!)
  const plain = /filename="?([^";]+)"?/i.exec(header)
  return plain ? plain[1]!.trim() : null
}

function toApiError(response: Response, payload: Record<string, unknown>, hadToken: boolean): ApiError {
  if (response.status === 401 && hadToken) onUnauthorized()
  return new ApiError(
    response.status,
    typeof payload.message === 'string' ? payload.message : `Request failed (${response.status})`,
    (payload.errors as Record<string, string[]> | undefined) ?? {},
    payload,
  )
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
