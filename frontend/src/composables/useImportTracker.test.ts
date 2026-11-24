import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import type { Import } from '@/types/api'

vi.mock('@/api/endpoints', () => ({ api: { uploadImport: vi.fn(), import: vi.fn() } }))

const { api } = await import('@/api/endpoints')
const { useImportTracker } = await import('./useImportTracker')

function anImport(overrides: Partial<Import> = {}): Import {
  return {
    id: 7, original_name: 'statement.csv', status: 'pending', is_finished: false, total_rows: null,
    processed_rows: 0, imported_rows: 0, failed_rows: 0, progress: 0, failure_reason: null,
    created_at: null, started_at: null, finished_at: null, ...overrides,
  }
}

const file = new File(['date,description,amount,type\n'], 'statement.csv')

describe('useImportTracker', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => {
    vi.useRealTimers()
    vi.resetAllMocks()
  })

  it('follows the upload until the import finishes, then reports it once', async () => {
    vi.mocked(api.uploadImport).mockResolvedValue(anImport())
    vi.mocked(api.import)
      .mockResolvedValueOnce(anImport({ status: 'processing', total_rows: 10, processed_rows: 5, progress: 50 }))
      .mockResolvedValueOnce(anImport({ status: 'completed', is_finished: true, progress: 100 }))
    const onFinished = vi.fn()
    const onUpdate = vi.fn()
    const tracker = useImportTracker(onFinished, onUpdate)

    await tracker.upload(file)
    await vi.advanceTimersByTimeAsync(500)
    expect(tracker.current.value?.progress).toBe(50)
    expect(onUpdate).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'processing', progress: 50 }))

    await vi.advanceTimersByTimeAsync(60_000)
    expect(tracker.current.value?.status).toBe('completed')
    expect(onFinished).toHaveBeenCalledOnce()
    expect(api.import).toHaveBeenCalledTimes(2)
  })

  it('points to the existing import when the file was already uploaded', async () => {
    vi.mocked(api.uploadImport).mockRejectedValue(
      new ApiError(409, 'This file was already uploaded (import #3, completed).', {}, { import_id: 3 }),
    )
    const tracker = useImportTracker(vi.fn())

    await tracker.upload(file)

    expect(tracker.uploadError.value).toContain('already uploaded')
    expect(tracker.duplicateOf.value).toBe(3)
  })

  it('explains validation, rate limit and network failures', async () => {
    const tracker = useImportTracker(vi.fn())

    vi.mocked(api.uploadImport).mockRejectedValueOnce(new ApiError(422, 'Invalid.', { file: ['The file is empty.'] }))
    await tracker.upload(file)
    expect(tracker.uploadError.value).toBe('The file is empty.')

    vi.mocked(api.uploadImport).mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.'))
    await tracker.upload(file)
    expect(tracker.uploadError.value).toContain('Too many uploads')

    vi.mocked(api.uploadImport).mockRejectedValueOnce(new TypeError('Failed to fetch'))
    await tracker.upload(file)
    expect(tracker.uploadError.value).toContain('Could not reach the server')
  })

  it('says the connection was lost instead of showing "processing" forever, and can resume', async () => {
    vi.mocked(api.uploadImport).mockResolvedValue(anImport())
    vi.mocked(api.import).mockRejectedValue(new TypeError('Failed to fetch'))
    const tracker = useImportTracker(vi.fn())

    await tracker.upload(file)
    await vi.advanceTimersByTimeAsync(60_000)
    expect(tracker.connectionLost.value).toBe(true)

    vi.mocked(api.import).mockResolvedValue(anImport({ status: 'completed', is_finished: true, progress: 100 }))
    tracker.resume()
    expect(tracker.connectionLost.value).toBe(false)
    await vi.advanceTimersByTimeAsync(500)
    expect(tracker.current.value?.status).toBe('completed')
  })
})
