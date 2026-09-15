export type WalkInDraft = {
  name: string
  phone: string
  guest_count: number
  category: string
  group_name: string
  notes: string
}

export function walkInPayload(draft: WalkInDraft, normalizedPhone: string) {
  return {
    name: draft.name.trim(),
    phone: normalizedPhone || null,
    guest_count: draft.guest_count,
    category: draft.category || null,
    group_name: draft.group_name || null,
    notes: draft.notes || null,
  }
}

export function likelyMatchQuery(name: string) {
  return name.trim()
}
