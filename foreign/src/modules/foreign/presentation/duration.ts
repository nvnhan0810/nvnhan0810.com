/** YouTube contentDetails.duration uses ISO 8601, e.g. PT12M34S. */
export function durationLabel(
  duration: string | null | undefined,
): string | null {
  if (!duration) return null
  const parts = /^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/.exec(
    duration,
  )
  if (!parts || !parts.slice(1).some((part) => part !== undefined)) return null
  const total =
    Number(parts[1] ?? 0) * 86400 +
    Number(parts[2] ?? 0) * 3600 +
    Number(parts[3] ?? 0) * 60 +
    Number(parts[4] ?? 0)
  if (!Number.isSafeInteger(total)) return null
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor(total / 60) % 60
  const seconds = String(total % 60).padStart(2, '0')
  return hours
    ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}`
    : `${minutes}:${seconds}`
}
