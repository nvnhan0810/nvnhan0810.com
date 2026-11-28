import type { Platform } from '@/domain/banking/bank-app'

/** Platform of the current browser, used to pick bank app deeplinks. */
export function currentPlatform(
  nav: Pick<Navigator, 'userAgent' | 'maxTouchPoints'> = navigator,
): Platform {
  const ua = nav.userAgent
  if (/android/i.test(ua)) return 'android'
  // iPadOS 13+ reports a desktop Safari UA, but has touch points.
  if (
    /iphone|ipad|ipod/i.test(ua) ||
    (/macintosh/i.test(ua) && nav.maxTouchPoints > 1)
  )
    return 'ios'
  return 'other'
}

/** Phones/tablets can hand off to an installed bank app. */
export function canOpenBankApp(): boolean {
  return currentPlatform() !== 'other'
}

/** Leave the PWA for an external app link (bank app via universal link). */
export function openExternal(url: string): void {
  window.location.assign(url)
}

/** Best-effort copy; must run inside a user gesture on iOS. */
export async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value)
    return true
  } catch {
    return false
  }
}

export function hasCamera(): boolean {
  return (
    typeof navigator !== 'undefined' &&
    !!navigator.mediaDevices?.getUserMedia &&
    window.isSecureContext
  )
}
