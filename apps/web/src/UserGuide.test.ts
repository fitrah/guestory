import { describe, expect, it } from 'vitest'
import { sections } from './UserGuide'

describe('contact import user guide', () => {
  const text = sections.filter((section) => ['contact-export', 'contact-import'].includes(section.id)).flatMap((section) => [section.title, section.summary, ...section.steps, ...(section.tips ?? [])]).join(' ')

  it('covers iPhone, iCloud, Android, and Google Contacts exports', () => {
    expect(text).toContain('iPhone/iCloud')
    expect(text).toContain('Android')
    expect(text).toContain('Google Contacts')
    expect(text).toContain('Export vCard')
  })

  it('documents formats, preview workflow, quota, duplicates, and non-delivery boundaries', () => {
    for (const expected of ['.vcf', '.csv', 'phone/whatsapp', 'Tinjau preview', 'slot tersisa', 'per guest record', 'Duplikat', 'dinormalisasi', 'tidak membuat atau mengirim invitation', 'WhatsApp', 'QR']) {
      expect(text).toContain(expected)
    }
  })
})

describe('guest photo user guide', () => {
  const text = sections.filter((section) => ['builder-photos', 'receiver-walk-in'].includes(section.id)).flatMap((section) => [section.title, section.summary, ...section.steps, ...(section.tips ?? [])]).join(' ')

  it('documents same-event check-in and separate camera/gallery choices', () => {
    for (const expected of ['setelah check-in', 'event yang sama', 'Ambil Foto', 'Pilih dari Galeri', 'Walk-in']) expect(text).toContain(expected)
  })
})
