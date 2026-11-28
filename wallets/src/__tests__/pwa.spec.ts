import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderServiceWorker } from '../../pwa/vite-plugin'
import {
  dismissInstall,
  initPwaInstall,
  isIosSafari,
  promptInstall,
  pwaInstall,
} from '../application/pwa-install'
import manifest from '../../public/manifest.webmanifest?raw'
import template from '../../pwa/service-worker.js?raw'

describe('service worker build', () => {
  it('precaches built assets and versions by content', () => {
    const a = renderServiceWorker(template, [
      'index.html',
      'assets/index-abc.js',
      'assets/index-abc.css',
      'holiday-template.csv',
    ])
    expect(a).not.toContain('__SW_VERSION__')
    expect(a).toContain('"/assets/index-abc.js"')
    expect(a).toContain('"/assets/index-abc.css"')
    expect(a).toContain('"/manifest.webmanifest"')
    expect(a).not.toContain('holiday-template.csv"')
    const b = renderServiceWorker(template, ['assets/index-def.js'])
    const version = (src: string) => /const VERSION = '(\w+)'/.exec(src)?.[1]
    expect(version(a)).toMatch(/^[0-9a-f]{12}$/)
    expect(version(a)).not.toBe(version(b))
  })

  it('only intercepts same-origin GET requests', () => {
    expect(template).toContain("request.method !== 'GET'")
    expect(template).toContain('url.origin !== self.location.origin')
  })
})

describe('web manifest', () => {
  it('is installable', () => {
    const m = JSON.parse(manifest)
    expect(m.display).toBe('standalone')
    expect(m.start_url).toBe('/')
    const sizes = m.icons.map((i: { sizes: string }) => i.sizes)
    expect(sizes).toContain('192x192')
    expect(sizes).toContain('512x512')
    expect(
      m.icons.some((i: { purpose: string }) => i.purpose === 'maskable'),
    ).toBe(true)
  })
})

describe('install prompt', () => {
  afterEach(() => {
    localStorage.clear()
    pwaInstall.mode = 'unsupported'
    pwaInstall.dismissed = false
  })

  it('detects iOS Safari only', () => {
    const safari =
      'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'
    const chromeIos = safari.replace('Version/18.0', 'CriOS/130.0')
    const ipad =
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15'
    expect(isIosSafari(safari)).toBe(true)
    expect(isIosSafari(chromeIos)).toBe(false)
    expect(isIosSafari(ipad, 5)).toBe(true)
    expect(isIosSafari(ipad, 0)).toBe(false)
  })

  it('uses the deferred browser prompt', async () => {
    initPwaInstall()
    const prompt = vi.fn().mockResolvedValue(undefined)
    const event = Object.assign(new Event('beforeinstallprompt'), {
      prompt,
      userChoice: Promise.resolve({ outcome: 'accepted' as const }),
    })
    window.dispatchEvent(event)
    expect(pwaInstall.mode).toBe('prompt')
    expect(await promptInstall()).toBe(true)
    expect(prompt).toHaveBeenCalledOnce()
    expect(pwaInstall.mode).toBe('installed')
    expect(await promptInstall()).toBe(false)
  })

  it('remembers a dismissal', () => {
    dismissInstall()
    pwaInstall.dismissed = false
    initPwaInstall()
    expect(pwaInstall.dismissed).toBe(true)
  })
})
