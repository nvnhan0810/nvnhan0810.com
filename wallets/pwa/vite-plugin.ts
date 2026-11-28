import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import type { Plugin } from 'vite'

const TEMPLATE = new URL('./service-worker.js', import.meta.url)
const STATIC_FILES = [
  '/manifest.webmanifest',
  '/icons/icon.svg',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
  '/icons/favicon-32.png',
]

/** Renders the service worker source for a list of emitted build files. */
export function renderServiceWorker(template: string, files: string[]): string {
  const precache = [
    ...STATIC_FILES,
    ...files
      .filter((file) => /^assets\/.+\.(js|css|woff2?|svg|png)$/.test(file))
      .sort()
      .map((file) => `/${file}`),
  ]
  const version = createHash('sha256')
    .update(precache.join('\n'))
    .digest('hex')
    .slice(0, 12)
  return template
    .replace('__SW_VERSION__', version)
    .replace('/* __SW_PRECACHE__ */ []', JSON.stringify(precache))
}

/** Emits dist/sw.js on build. The dev server never registers a worker. */
export function serviceWorker(): Plugin {
  return {
    name: 'mywallet-service-worker',
    apply: 'build',
    generateBundle(_options, bundle) {
      this.emitFile({
        type: 'asset',
        fileName: 'sw.js',
        source: renderServiceWorker(
          readFileSync(TEMPLATE, 'utf8'),
          Object.keys(bundle),
        ),
      })
    },
  }
}
