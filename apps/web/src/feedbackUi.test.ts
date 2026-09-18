import { describe, expect, it } from 'vitest'
import { attendanceLabel, categoryCounts, guestCategoriesForEvent, hourlyCheckIns, paginate, weddingGuestCategories } from './feedbackUi'

describe('Testing (2) UI helpers', () => {
  it('uses the six wedding groups for wedding and engagement events', () => {
    expect(guestCategoriesForEvent('Wedding')).toEqual([...weddingGuestCategories])
    expect(guestCategoriesForEvent('Engagement')).toEqual([...weddingGuestCategories])
    expect(guestCategoriesForEvent('Birthday')).toContain('Family')
  })

  it('renders human attendance labels', () => {
    expect(attendanceLabel('CHECKED_IN')).toBe('Checked In')
    expect(attendanceLabel('NOT_CHECKED_IN')).toBe('Not Checked In')
  })

  it('paginates safely and clamps the current page', () => {
    expect(paginate([1, 2, 3, 4, 5], 2, 2)).toEqual({ items: [3, 4], current: 2, pages: 3, total: 5 })
    expect(paginate([1], 99, 10).current).toBe(1)
  })

  it('aggregates overview chart data by half hour and category', () => {
    expect(hourlyCheckIns([{ checked_in_at: '2026-09-18T18:10:00' }, { checked_in_at: '2026-09-18T18:42:00' }])).toEqual([{ label: '18:00', value: 1 }, { label: '18:30', value: 1 }])
    expect(categoryCounts([{ category: 'VIP', guest_count: 2 }, { category: 'VIP', guest_count: 1 }, { category: 'Family' }])).toEqual([{ label: 'VIP', value: 3 }, { label: 'Family', value: 1 }])
  })
})
