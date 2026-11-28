import { reactive } from 'vue'

/** Chrome/Edge/Samsung Internet event, not yet in the DOM typings. */
interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const DISMISS_KEY = 'pwa-install-dismissed-at'
/** After "Để sau", the banner comes back after this long. */
const DISMISS_FOR_MS = 14 * 24 * 60 * 60 * 1000

export type InstallMode = 'prompt' | 'ios' | 'installed' | 'unsupported'

export const pwaInstall = reactive({
  mode: 'unsupported' as InstallMode,
  dismissed: false,
})

let deferred: BeforeInstallPromptEvent | null = null

export function isIosSafari(ua: string, maxTouchPoints = 0): boolean {
  const ios =
    /iPad|iPhone|iPod/.test(ua) ||
    // iPadOS reports itself as macOS.
    (/Macintosh/.test(ua) && maxTouchPoints > 1)
  // Other iOS browsers (CriOS, FxiOS, EdgiOS…) cannot add to the home screen.
  return ios && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua)
}

export function isStandalone(): boolean {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches === true ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true
  )
}

function readDismissed(now = Date.now()): boolean {
  try {
    const at = Number(localStorage.getItem(DISMISS_KEY))
    return Number.isFinite(at) && at > 0 && now - at < DISMISS_FOR_MS
  } catch {
    return false
  }
}

export function initPwaInstall(): void {
  pwaInstall.dismissed = readDismissed()
  if (isStandalone()) {
    pwaInstall.mode = 'installed'
    return
  }
  if (isIosSafari(navigator.userAgent, navigator.maxTouchPoints))
    pwaInstall.mode = 'ios'
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferred = event as BeforeInstallPromptEvent
    pwaInstall.mode = 'prompt'
  })
  window.addEventListener('appinstalled', () => {
    deferred = null
    pwaInstall.mode = 'installed'
  })
}

/** Shows the browser's install dialog. Resolves true when accepted. */
export async function promptInstall(): Promise<boolean> {
  if (!deferred) return false
  const event = deferred
  deferred = null
  await event.prompt()
  const { outcome } = await event.userChoice
  if (outcome === 'accepted') pwaInstall.mode = 'installed'
  else pwaInstall.mode = 'unsupported'
  return outcome === 'accepted'
}

export function dismissInstall(): void {
  pwaInstall.dismissed = true
  try {
    localStorage.setItem(DISMISS_KEY, String(Date.now()))
  } catch {
    // Private mode: dismissal lasts for this session only.
  }
}
