export const CAMERA_CONSTRAINTS: MediaStreamConstraints = {
  video: { facingMode: { ideal: 'environment' } },
  audio: false,
}

export function cameraSupportMessage(): string | null {
  if (typeof window === 'undefined' || typeof navigator === 'undefined') return 'Kamera browser tidak tersedia di perangkat ini.'
  if (!window.isSecureContext) return 'Kamera browser hanya dapat digunakan melalui koneksi HTTPS yang aman.'
  if (!navigator.mediaDevices?.getUserMedia) return 'Browser ini belum mendukung kamera langsung.'
  return null
}

export function cameraErrorMessage(error: unknown): string {
  const name = error instanceof DOMException ? error.name : (error as { name?: string } | null)?.name
  if (name === 'NotAllowedError' || name === 'SecurityError') return 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser, lalu coba lagi.'
  if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'Kamera tidak ditemukan pada perangkat ini.'
  if (name === 'NotReadableError' || name === 'TrackStartError') return 'Kamera sedang dipakai aplikasi lain atau tidak dapat dibuka.'
  if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') return 'Kamera yang dipilih tidak tersedia. Coba kamera lain.'
  if (name === 'AbortError') return 'Pembukaan kamera dibatalkan oleh perangkat. Silakan coba lagi.'
  return 'Kamera tidak dapat dibuka. Silakan coba lagi atau pilih foto dari galeri.'
}

export async function requestCamera(deviceId?: string): Promise<MediaStream> {
  const unsupported = cameraSupportMessage()
  if (unsupported) throw Object.assign(new Error(unsupported), { name: 'CameraUnsupportedError' })
  return navigator.mediaDevices.getUserMedia({
    video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } },
    audio: false,
  })
}

export function stopCameraStream(stream?: MediaStream | null): void {
  stream?.getTracks().forEach((track) => track.stop())
}

export async function captureVideoFrame(
  video: Pick<HTMLVideoElement, 'videoWidth' | 'videoHeight'>,
  createCanvas: () => HTMLCanvasElement = () => document.createElement('canvas'),
  now = Date.now,
): Promise<File> {
  if (!video.videoWidth || !video.videoHeight) throw new Error('Frame kamera belum siap. Tunggu sebentar lalu coba lagi.')
  const canvas = createCanvas()
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const context = canvas.getContext('2d')
  if (!context) throw new Error('Foto tidak dapat diproses oleh browser ini.')
  context.drawImage(video as HTMLVideoElement, 0, 0, canvas.width, canvas.height)
  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9))
  if (!blob) throw new Error('Foto tidak dapat dibuat. Silakan coba lagi.')
  return new File([blob], `guestory-camera-${now()}.jpg`, { type: 'image/jpeg', lastModified: now() })
}
