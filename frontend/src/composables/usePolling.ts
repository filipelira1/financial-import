import { getCurrentScope, onScopeDispose, ref } from 'vue'

export interface PollingOptions<T> {
  /** Stop once this returns true. */
  isDone: (result: T) => boolean
  onResult?: (result: T) => void
  /** Wait before each request; the last value repeats. Grows so a long job costs few requests. */
  intervals?: number[]
  /** Give up after this many failed requests in a row. */
  maxConsecutiveErrors?: number
}

/**
 * "Smart" polling: backs off (0.5 s, 1 s, 2 s, then every 4 s), stops by itself when the
 * work is done, pauses while the tab is hidden and resumes at once when it comes back.
 */
export function usePolling<T>(task: () => Promise<T>, options: PollingOptions<T>) {
  const intervals = options.intervals ?? [500, 1000, 2000, 4000]
  const maxErrors = options.maxConsecutiveErrors ?? 5

  const running = ref(false)
  const error = ref<unknown>(null)

  let attempt = 0
  let errors = 0
  let timer: ReturnType<typeof setTimeout> | undefined

  function delay(): number {
    return intervals[Math.min(attempt, intervals.length - 1)] ?? 4000
  }

  function schedule(): void {
    clearTimeout(timer)
    if (!running.value || document.hidden) return
    timer = setTimeout(tick, delay())
  }

  async function tick(): Promise<void> {
    if (!running.value) return
    attempt++

    try {
      const result = await task()
      errors = 0
      error.value = null
      if (!running.value) return
      options.onResult?.(result)
      if (options.isDone(result)) {
        stop()
        return
      }
    } catch (caught) {
      error.value = caught
      if (++errors >= maxErrors) {
        stop()
        return
      }
    }

    schedule()
  }

  function onVisibilityChange(): void {
    if (!running.value) return
    if (document.hidden) {
      clearTimeout(timer)
    } else {
      void tick() // catch up right away
    }
  }

  function start(): void {
    stop()
    running.value = true
    attempt = 0
    errors = 0
    document.addEventListener('visibilitychange', onVisibilityChange)
    schedule()
  }

  function stop(): void {
    running.value = false
    clearTimeout(timer)
    document.removeEventListener('visibilitychange', onVisibilityChange)
  }

  if (getCurrentScope()) onScopeDispose(stop)

  return { start, stop, running, error }
}
