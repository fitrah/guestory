import { useCallback, useEffect, useState } from 'react'

const SELECTED_EVENT_KEY = 'guestory_selected_event_id'
const SELECTED_EVENT_EVENT = 'guestory:selected-event'

export function readSelectedEventId() {
  const value = Number(window.localStorage.getItem(SELECTED_EVENT_KEY))
  return Number.isSafeInteger(value) && value > 0 ? value : null
}

export function useSelectedEventId() {
  const [selectedEventId, setState] = useState<number | null>(readSelectedEventId)
  const setSelectedEventId = useCallback((value: number | null | ((current: number | null) => number | null)) => {
    setState((current) => {
      const next = typeof value === 'function' ? value(current) : value
      if (next) window.localStorage.setItem(SELECTED_EVENT_KEY, String(next))
      else window.localStorage.removeItem(SELECTED_EVENT_KEY)
      window.dispatchEvent(new CustomEvent(SELECTED_EVENT_EVENT, { detail: next }))
      return next
    })
  }, [])
  useEffect(() => {
    const sync = (event: Event) => setState((event as CustomEvent<number | null>).detail ?? readSelectedEventId())
    const storage = () => setState(readSelectedEventId())
    window.addEventListener(SELECTED_EVENT_EVENT, sync)
    window.addEventListener('storage', storage)
    return () => { window.removeEventListener(SELECTED_EVENT_EVENT, sync); window.removeEventListener('storage', storage) }
  }, [])
  return [selectedEventId, setSelectedEventId] as const
}
