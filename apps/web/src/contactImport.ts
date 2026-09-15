export type ContactImportRow = {
  id: string
  selected: boolean
  name: string
  phone: string
  email: string
  category: string
  guest_count: number
  errors: string[]
  duplicate?: string
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function normalizeIndonesianPhone(value: string): string {
  let digits = value.replace(/\D/g, '')
  if (digits.startsWith('00')) digits = digits.slice(2)
  if (digits.startsWith('62')) return digits
  digits = digits.replace(/^0+/, '')
  return digits ? `62${digits}` : ''
}

export function validateContact(row: Pick<ContactImportRow, 'name' | 'phone' | 'email'>): string[] {
  const errors: string[] = []
  if (!row.name.trim()) errors.push('Nama wajib diisi')
  const phone = normalizeIndonesianPhone(row.phone)
  if (!phone && !row.email.trim()) errors.push('Nomor WhatsApp atau email wajib diisi')
  if (phone && !/^62[1-9][0-9]{7,13}$/.test(phone)) errors.push('Nomor Indonesia tidak valid')
  if (row.email.trim() && !EMAIL_RE.test(row.email.trim())) errors.push('Email tidak valid')
  return errors
}

function csvCells(line: string): string[] {
  const cells: string[] = []
  let value = ''
  let quoted = false
  for (let index = 0; index < line.length; index += 1) {
    const char = line[index]
    if (char === '"' && quoted && line[index + 1] === '"') { value += '"'; index += 1 }
    else if (char === '"') quoted = !quoted
    else if (char === ',' && !quoted) { cells.push(value.trim()); value = '' }
    else value += char
  }
  cells.push(value.trim())
  return cells
}

function unfoldVcard(text: string) {
  return text.replace(/\r?\n[ \t]/g, '')
}

function vcardValue(card: string, field: string): string {
  const line = card.split(/\r?\n/).find((item) => item.toUpperCase().split(':')[0].split(';')[0] === field)
  if (!line) return ''
  return line.slice(line.indexOf(':') + 1).replace(/\\n/gi, ' ').replace(/\\([,;\\])/g, '$1').trim()
}

function makeRow(data: Partial<ContactImportRow>, index: number): ContactImportRow {
  const row: ContactImportRow = {
    id: `contact-${index}-${Math.random().toString(36).slice(2)}`,
    selected: true,
    name: data.name?.trim() ?? '',
    phone: normalizeIndonesianPhone(data.phone ?? ''),
    email: data.email?.trim().toLowerCase() ?? '',
    category: data.category || 'Other',
    guest_count: Number(data.guest_count) || 1,
    errors: [],
  }
  row.errors = validateContact(row)
  return row
}

export function parseContactFile(text: string, filename: string): ContactImportRow[] {
  if (/\.vcf$/i.test(filename) || /BEGIN:VCARD/i.test(text)) {
    return unfoldVcard(text).split(/END:VCARD/i).filter((card) => /BEGIN:VCARD/i.test(card)).map((card, index) => makeRow({
      name: vcardValue(card, 'FN') || vcardValue(card, 'N').split(';').filter(Boolean).reverse().join(' '),
      phone: vcardValue(card, 'TEL'),
      email: vcardValue(card, 'EMAIL'),
    }, index))
  }

  const lines = text.replace(/^\uFEFF/, '').split(/\r?\n/).filter((line) => line.trim())
  if (!lines.length) return []
  const headers = csvCells(lines[0]).map((header) => header.toLowerCase().replace(/[\s-]+/g, '_'))
  const aliases: Record<string, string[]> = {
    name: ['name', 'nama', 'full_name', 'nama_lengkap'],
    phone: ['phone', 'tel', 'telephone', 'whatsapp', 'wa', 'nomor_hp', 'no_hp'],
    email: ['email', 'e_mail'], category: ['category', 'kategori'], guest_count: ['guest_count', 'jumlah_tamu'],
  }
  const at = (cells: string[], key: string) => { const index = headers.findIndex((header) => aliases[key].includes(header)); return index >= 0 ? cells[index] : '' }
  return lines.slice(1).map((line, index) => { const cells = csvCells(line); return makeRow({ name: at(cells, 'name'), phone: at(cells, 'phone'), email: at(cells, 'email'), category: at(cells, 'category'), guest_count: Number(at(cells, 'guest_count')) || 1 }, index) })
}

export function selectUpToCapacity(rows: ContactImportRow[], capacity: number): ContactImportRow[] {
  let selected = 0
  return rows.map((row) => {
    const canSelect = selected < Math.max(0, capacity)
    if (canSelect) selected += 1
    return { ...row, selected: canSelect }
  })
}

export function setRowSelectedWithinCapacity(rows: ContactImportRow[], id: string, selected: boolean, capacity: number): { rows: ContactImportRow[]; limited: boolean } {
  if (!selected) return { rows: rows.map((row) => row.id === id ? { ...row, selected: false } : row), limited: false }
  if (rows.filter((row) => row.selected).length >= Math.max(0, capacity)) return { rows, limited: true }
  return { rows: rows.map((row) => row.id === id ? { ...row, selected: true } : row), limited: false }
}

export function markDuplicates(rows: ContactImportRow[], existing: { phone?: string; email?: string }[]): ContactImportRow[] {
  const existingPhones = new Set(existing.map((item) => normalizeIndonesianPhone(item.phone ?? '')).filter(Boolean))
  const existingEmails = new Set(existing.map((item) => item.email?.trim().toLowerCase()).filter(Boolean))
  const phones = new Set<string>()
  const emails = new Set<string>()
  return rows.map((row) => {
    const phone = normalizeIndonesianPhone(row.phone)
    const email = row.email.trim().toLowerCase()
    let duplicate = ''
    if (phone && existingPhones.has(phone)) duplicate = 'Nomor sudah ada di guest list'
    else if (email && existingEmails.has(email)) duplicate = 'Email sudah ada di guest list'
    else if (phone && phones.has(phone)) duplicate = 'Nomor duplikat dalam file'
    else if (email && emails.has(email)) duplicate = 'Email duplikat dalam file'
    if (phone) phones.add(phone)
    if (email) emails.add(email)
    return { ...row, phone, email, duplicate: duplicate || undefined, errors: validateContact({ ...row, phone, email }) }
  })
}
