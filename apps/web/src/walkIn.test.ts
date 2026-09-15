import { describe, expect, it } from 'vitest'
import { likelyMatchQuery, walkInPayload } from './walkIn'

describe('walk-in receiver helpers', () => {
  it('trims the required name and preserves optional fields without inventing values', () => {
    expect(walkInPayload({ name: '  Tamu Baru ', phone: '0812', guest_count: 3, category: '', group_name: 'Keluarga', notes: '' }, '62812')).toEqual({
      name: 'Tamu Baru', phone: '62812', guest_count: 3, category: null, group_name: 'Keluarga', notes: null,
    })
  })

  it('uses the name as the likely-existing-match search term', () => {
    expect(likelyMatchQuery('  Siti Aminah  ')).toBe('Siti Aminah')
  })
})
