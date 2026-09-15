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
