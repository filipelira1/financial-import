import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, configureHttp, http } from './http'

function respond(status: number, body: unknown = {}) {
  return vi.fn(async () => new Response(status === 204 ? null : JSON.stringify(body), { status }))
}

describe('http', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('sends the bearer token and asks for JSON', async () => {
    const fetchMock = respond(200, { data: 1 })
    vi.stubGlobal('fetch', fetchMock)
    configureHttp({ token: () => 'abc', unauthorized: () => {} })

    await http('/auth/me', { query: { page: 2, type: undefined } })

    expect(fetchMock).toHaveBeenCalledWith('/api/v1/auth/me?page=2', {
      method: 'GET',
      headers: { Accept: 'application/json', 'Accept-Language': 'pt-BR', Authorization: 'Bearer abc' },
      body: undefined,
    })
  })

  it('turns a validation response into an ApiError with field messages', async () => {
    vi.stubGlobal('fetch', respond(422, { message: 'Invalid.', errors: { file: ['Missing required column(s): amount.'] } }))
    configureHttp({ token: () => 'abc', unauthorized: () => {} })

    const error = await http('/imports', { method: 'POST' }).catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).fieldError('file')).toBe('Missing required column(s): amount.')
  })

  it('ends the session when the token is rejected', async () => {
    vi.stubGlobal('fetch', respond(401, { message: 'Unauthenticated.' }))
    const unauthorized = vi.fn()
    configureHttp({ token: () => 'expired', unauthorized })

    await expect(http('/dashboard/summary')).rejects.toBeInstanceOf(ApiError)
    expect(unauthorized).toHaveBeenCalledOnce()
  })

  it('does not treat a failed login as an expired session', async () => {
    vi.stubGlobal('fetch', respond(401))
    const unauthorized = vi.fn()
    configureHttp({ token: () => null, unauthorized })

    await expect(http('/auth/login', { method: 'POST' })).rejects.toBeInstanceOf(ApiError)
    expect(unauthorized).not.toHaveBeenCalled()
  })
})
