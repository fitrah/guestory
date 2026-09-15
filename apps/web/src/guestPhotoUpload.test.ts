import { describe, expect, it } from 'vitest'
import { guestPhotoInputSemantics } from './InvitationBuilder'

describe('guest photo upload input semantics', () => {
  it('uses mobile-friendly rear-camera capture semantics', () => {
    expect(guestPhotoInputSemantics.camera).toEqual({ accept: 'image/*', capture: 'environment' })
  })

  it('keeps gallery selection separate and does not force camera capture', () => {
    expect(guestPhotoInputSemantics.gallery).toEqual({ accept: 'image/*' })
    expect(guestPhotoInputSemantics.gallery).not.toHaveProperty('capture')
  })
})
