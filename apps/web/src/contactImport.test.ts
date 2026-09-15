import { describe, expect, it } from 'vitest'
import { markDuplicates, normalizeIndonesianPhone, parseContactFile, selectUpToCapacity, setRowSelectedWithinCapacity, validateContact } from './contactImport'

describe('contact import', () => {
  it('parses VCF contacts and normalizes Indonesian phones', () => {
    const rows = parseContactFile('BEGIN:VCARD\nVERSION:3.0\nFN:Budi Santoso\nTEL;TYPE=CELL:0812-3456-7890\nEMAIL:BUDI@EXAMPLE.COM\nEND:VCARD', 'contacts.vcf')
    expect(rows[0]).toMatchObject({ name: 'Budi Santoso', phone: '6281234567890', email: 'budi@example.com' })
  })

  it('parses quoted CSV with Indonesian headers', () => {
    const rows = parseContactFile('nama,whatsapp,email\n"Siti, Aminah",+6281312345678,siti@example.com', 'contacts.csv')
    expect(rows[0]).toMatchObject({ name: 'Siti, Aminah', phone: '6281312345678' })
  })

  it('detects duplicates against guest list and within preview', () => {
    const rows = markDuplicates([
      ...parseContactFile('name,phone\nExisting,081234567890\nRepeat,+6281312345678\nRepeat 2,0813-1234-5678', 'contacts.csv'),
    ], [{ phone: '6281234567890' }])
    expect(rows[0].duplicate).toContain('guest list')
    expect(rows[2].duplicate).toContain('file')
  })

  it('selects only up to package capacity and safely releases slots', () => {
    const rows = parseContactFile('name,phone\nOne,081234567801\nTwo,081234567802\nThree,081234567803', 'contacts.csv')
    const limited = selectUpToCapacity(rows, 2)
    expect(limited.map((row) => row.selected)).toEqual([true, true, false])
    expect(setRowSelectedWithinCapacity(limited, limited[2].id, true, 2).limited).toBe(true)
    const released = setRowSelectedWithinCapacity(limited, limited[0].id, false, 2).rows
    const reselection = setRowSelectedWithinCapacity(released, released[2].id, true, 2)
    expect(reselection.limited).toBe(false)
    expect(reselection.rows.map((row) => row.selected)).toEqual([false, true, true])
  })

  it('validates required names, contact values, email, and phone length', () => {
    expect(validateContact({ name: '', phone: '', email: '' })).toHaveLength(2)
    expect(validateContact({ name: 'A', phone: '081', email: 'bad' })).toHaveLength(2)
    expect(normalizeIndonesianPhone('0062 812-3456-7890')).toBe('6281234567890')
  })
})
