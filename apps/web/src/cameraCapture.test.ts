import { afterEach, describe, expect, it, vi } from 'vitest'
import { cameraErrorMessage, captureVideoFrame, requestCamera, stopCameraStream } from './cameraCapture'

afterEach(() => vi.restoreAllMocks())

describe('browser camera capture', () => {
  it('requests an environment-facing camera without audio', async () => {
    const stream = {} as MediaStream
    const getUserMedia = vi.fn().mockResolvedValue(stream)
    vi.stubGlobal('window', { isSecureContext: true })
    vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })

    await expect(requestCamera()).resolves.toBe(stream)
    expect(getUserMedia).toHaveBeenCalledWith({ video: { facingMode: { ideal: 'environment' } }, audio: false })
  })

  it('provides Indonesian permission and in-use error messages', () => {
    expect(cameraErrorMessage({ name: 'NotAllowedError' })).toContain('Izin kamera ditolak')
    expect(cameraErrorMessage({ name: 'NotReadableError' })).toContain('dipakai aplikasi lain')
  })

  it('stops every stream track', () => {
    const tracks = [{ stop: vi.fn() }, { stop: vi.fn() }]
    stopCameraStream({ getTracks: () => tracks } as unknown as MediaStream)
    tracks.forEach((track) => expect(track.stop).toHaveBeenCalledOnce())
  })

  it('draws the video frame and hands off a JPEG File', async () => {
    const drawImage = vi.fn()
    const canvas = {
      width: 0,
      height: 0,
      getContext: () => ({ drawImage }),
      toBlob: (callback: BlobCallback) => callback(new Blob(['jpeg'], { type: 'image/jpeg' })),
    } as unknown as HTMLCanvasElement

    const file = await captureVideoFrame({ videoWidth: 1280, videoHeight: 720 }, () => canvas, () => 1234)
    expect(drawImage).toHaveBeenCalledWith(expect.anything(), 0, 0, 1280, 720)
    expect(file).toBeInstanceOf(File)
    expect(file.name).toBe('guestory-camera-1234.jpg')
    expect(file.type).toBe('image/jpeg')
  })
})
