import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, configureHttp, download, fileNameFrom, http } from './http'

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

  it('reads the file name the server gives to a download', () => {
    expect(fileNameFrom('attachment; filename=transactions-2026-11-24-101500.csv')).toBe('transactions-2026-11-24-101500.csv')
    expect(fileNameFrom('attachment; filename="import 3.csv"')).toBe('import 3.csv')
    expect(fileNameFrom("attachment; filename*=UTF-8''relat%C3%B3rio.csv")).toBe('relatório.csv')
    expect(fileNameFrom(null)).toBeNull()
  })

  it('downloads with the token and reports an API error like any other call', async () => {
    const fetchMock = respond(404, { message: 'Not found.' })
    vi.stubGlobal('fetch', fetchMock)
    configureHttp({ token: () => 'abc', unauthorized: () => {} })

    await expect(download('/imports/9/failures/export')).rejects.toMatchObject({ status: 404, message: 'Not found.' })
    expect((fetchMock.mock.calls[0] as unknown[])[1]).toMatchObject({ headers: { Authorization: 'Bearer abc' } })
  })
})

