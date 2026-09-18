export const weddingGuestCategories = [
  'Keluarga Pengantin Pria',
  'Keluarga Pengantin Wanita',
  'Tamu Pengantin Pria',
  'Tamu Pengantin Wanita',
  'Tamu Orang Tua Pria',
  'Tamu Orang Tua Wanita',
] as const

export const defaultGuestCategories = ['Family', 'Friend', 'Colleague', 'VIP', 'Other'] as const

export function guestCategoriesForEvent(type?: string) {
  return ['wedding', 'engagement', 'enggagement'].includes((type ?? '').toLowerCase())
    ? [...weddingGuestCategories]
    : [...defaultGuestCategories]
}

export function attendanceLabel(status?: string) {
  return status === 'CHECKED_IN' ? 'Checked In' : status === 'NOT_CHECKED_IN' ? 'Not Checked In' : status || '-'
}

export function paginate<T>(items: T[], page: number, perPage = 10) {
  const pages = Math.max(1, Math.ceil(items.length / perPage))
  const current = Math.min(Math.max(1, page), pages)
  return { items: items.slice((current - 1) * perPage, current * perPage), current, pages, total: items.length }
}

export function hourlyCheckIns(checkIns: { checked_in_at?: string }[]) {
  const counts = new Map<string, number>()
  for (const item of checkIns) {
    if (!item.checked_in_at) continue
    const date = new Date(item.checked_in_at)
    if (Number.isNaN(date.valueOf())) continue
    const minutes = date.getMinutes() < 30 ? '00' : '30'
    const label = `${String(date.getHours()).padStart(2, '0')}:${minutes}`
    counts.set(label, (counts.get(label) ?? 0) + 1)
  }
  return [...counts].sort(([a], [b]) => a.localeCompare(b)).map(([label, value]) => ({ label, value }))
}

export function categoryCounts(guests: { category?: string; guest_count?: number }[]) {
  const counts = new Map<string, number>()
  for (const guest of guests) {
    const category = guest.category || 'Other'
    counts.set(category, (counts.get(category) ?? 0) + (guest.guest_count ?? 1))
  }
  return [...counts].sort((a, b) => b[1] - a[1]).map(([label, value]) => ({ label, value }))
}
