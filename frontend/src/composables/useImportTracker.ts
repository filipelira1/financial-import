import { computed, ref } from 'vue'
import { api } from '@/api/endpoints'
import { ApiError } from '@/api/http'
import { describeError } from '@/api/errors'
import { usePolling } from './usePolling'
import type { Import } from '@/types/api'

/**
 * Uploads a file and follows its import until the queue has finished it.
 */
export function useImportTracker(onFinished: (finished: Import) => void, onUpdate?: (item: Import) => void) {
  const current = ref<Import | null>(null)
  const uploading = ref(false)
  const uploadError = ref<string | null>(null)
  const duplicateOf = ref<number | null>(null)

  const polling = usePolling(() => api.import(current.value!.id), {
    isDone: (result) => result.is_finished,
    onResult: (result) => {
      current.value = result
      onUpdate?.(result)
      if (result.is_finished) onFinished(result)
    },
  })

  async function upload(file: File): Promise<void> {
    uploading.value = true
    uploadError.value = null
    duplicateOf.value = null

    try {
      track(await api.uploadImport(file))
    } catch (caught) {
      if (caught instanceof ApiError) {
        uploadError.value = caught.fieldError('file') ?? caught.message
        if (caught.status === 409 && typeof caught.body.import_id === 'number') duplicateOf.value = caught.body.import_id
        if (caught.status === 429) uploadError.value = 'Too many uploads. Please wait a minute and try again.'
      } else {
        uploadError.value = describeError(caught)
      }
    } finally {
      uploading.value = false
    }
  }

  /** Follow an import that is already queued (also used after a page reload or a retry). */
  function track(item: Import): void {
    current.value = item
    if (item.is_finished) {
      polling.stop()
    } else {
      polling.start()
    }
  }

  /** Polling gave up after repeated failures while the import was still running. */
  const connectionLost = computed(() => !polling.running.value && polling.error.value !== null && current.value?.is_finished === false)

  function resume(): void {
    if (current.value && !current.value.is_finished) polling.start()
  }

  function reset(): void {
    polling.stop()
    current.value = null
    uploadError.value = null
    duplicateOf.value = null
  }

  return { current, uploading, uploadError, duplicateOf, polling: polling.running, connectionLost, upload, track, resume, reset }
}
