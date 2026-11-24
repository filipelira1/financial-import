import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { usePolling } from './usePolling'

describe('usePolling', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('backs off between requests: 0.5 s, 1 s, 2 s, then every 4 s', async () => {
    const calls: number[] = []
    const task = vi.fn(async () => {
      calls.push(Date.now())
      return { done: false }
    })
    const polling = usePolling(task, { isDone: (result) => result.done })

    const start = Date.now()
    polling.start()
    await vi.advanceTimersByTimeAsync(500 + 1000 + 2000 + 4000 + 4000)

    expect(calls.map((time) => time - start)).toEqual([500, 1500, 3500, 7500, 11500])
    polling.stop()
  })

  it('stops by itself once the work is done', async () => {
    let call = 0
    const task = vi.fn(async () => ({ done: ++call === 3 }))
    const onResult = vi.fn()
    const polling = usePolling(task, { isDone: (result) => result.done, onResult })

    polling.start()
    await vi.advanceTimersByTimeAsync(60_000)

    expect(task).toHaveBeenCalledTimes(3)
    expect(onResult).toHaveBeenCalledTimes(3)
    expect(polling.running.value).toBe(false)
  })

  it('gives up after repeated failures', async () => {
    const task = vi.fn(async () => {
      throw new Error('offline')
    })
    const polling = usePolling(task, { isDone: () => false, maxConsecutiveErrors: 3 })

    polling.start()
    await vi.advanceTimersByTimeAsync(60_000)

    expect(task).toHaveBeenCalledTimes(3)
    expect(polling.running.value).toBe(false)
    expect(polling.error.value).toBeInstanceOf(Error)
  })

  it('pauses while the tab is hidden and catches up when it is visible again', async () => {
    const task = vi.fn(async () => ({ done: false }))
    const polling = usePolling(task, { isDone: (result) => result.done })
    const hidden = vi.spyOn(document, 'hidden', 'get')

    polling.start()
    await vi.advanceTimersByTimeAsync(500)
    expect(task).toHaveBeenCalledTimes(1)

    hidden.mockReturnValue(true)
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(30_000)
    expect(task).toHaveBeenCalledTimes(1)

    hidden.mockReturnValue(false)
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(0)
    expect(task).toHaveBeenCalledTimes(2)

    polling.stop()
    hidden.mockRestore()
  })
})
