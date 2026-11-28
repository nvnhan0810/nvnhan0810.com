// QR decoding for camera frames and pictures. Uses the native BarcodeDetector
// (Chrome/Android) when present, otherwise jsQR (iOS Safari, Firefox).

interface NativeDetector {
  detect(source: CanvasImageSource): Promise<{ rawValue: string }[]>
}
type DetectorCtor = new (options: { formats: string[] }) => NativeDetector

let native: NativeDetector | null | undefined

async function nativeDetector(): Promise<NativeDetector | null> {
  if (native !== undefined) return native
  native = null
  const Ctor = (
    globalThis as {
      BarcodeDetector?: DetectorCtor & {
        getSupportedFormats?: () => Promise<string[]>
      }
    }
  ).BarcodeDetector
  try {
    if (Ctor && (await Ctor.getSupportedFormats?.())?.includes('qr_code'))
      native = new Ctor({ formats: ['qr_code'] })
  } catch {
    native = null
  }
  return native
}

let canvas: HTMLCanvasElement | undefined

/** Decode a QR from a video frame or image; `null` when none is visible. */
export async function decodeQr(
  source: HTMLVideoElement | HTMLImageElement | ImageBitmap,
): Promise<string | null> {
  const detector = await nativeDetector()
  if (detector) {
    try {
      const codes = await detector.detect(source)
      if (codes[0]?.rawValue) return codes[0].rawValue
      return null
    } catch {
      // Fall through to jsQR, e.g. when the frame isn't ready yet.
    }
  }
  const width =
    source instanceof HTMLVideoElement
      ? source.videoWidth
      : source instanceof HTMLImageElement
        ? source.naturalWidth
        : source.width
  const height =
    source instanceof HTMLVideoElement
      ? source.videoHeight
      : source instanceof HTMLImageElement
        ? source.naturalHeight
        : source.height
  if (!width || !height) return null
  // Downscale big pictures; jsQR is O(pixels).
  const scale = Math.min(1, 1024 / Math.max(width, height))
  canvas ??= document.createElement('canvas')
  canvas.width = Math.round(width * scale)
  canvas.height = Math.round(height * scale)
  const context = canvas.getContext('2d', { willReadFrequently: true })
  if (!context) return null
  context.drawImage(source, 0, 0, canvas.width, canvas.height)
  const image = context.getImageData(0, 0, canvas.width, canvas.height)
  const { default: jsQR } = await import('jsqr')
  return jsQR(image.data, image.width, image.height)?.data ?? null
}

export async function decodeQrFile(file: File): Promise<string | null> {
  const bitmap = await createImageBitmap(file)
  try {
    return await decodeQr(bitmap)
  } finally {
    bitmap.close()
  }
}
