import { t } from '@/i18n'
import { ApiError } from './http'

/** A sentence the user can act on, for any failed request. */
export function describeError(caught: unknown, fallback = t('errors.generic')): string {
  if (!(caught instanceof ApiError)) return t('errors.network')
  if (caught.status === 429) return t('errors.throttled')
  if (caught.status >= 500) return t('errors.server')
  return Object.values(caught.errors).flat()[0] ?? caught.message ?? fallback
}
