// MyWallet service worker. Built into dist/sw.js by pwa/vite-plugin.ts, which
// replaces the two placeholders below. Not used by `npm run dev`.
//
// - Navigations: network first, falling back to the cached app shell offline.
// - Hashed build assets and icons: cache first (precached on install).
// - Everything else (API, Identity, other origins, non-GET): not intercepted,
//   so authenticated data is never stored by the worker.

const VERSION = '__SW_VERSION__'
const PRECACHE = /* __SW_PRECACHE__ */ []
const CACHE_PREFIX = 'mywallet-'
const CACHE = `${CACHE_PREFIX}${VERSION}`
const SHELL = '/index.html'

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE)
      .then((cache) => cache.addAll([SHELL, ...PRECACHE]))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  )
})

function isStaticAsset(url) {
  return (
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/icons/') ||
    url.pathname === '/manifest.webmanifest'
  )
}

async function networkFirstShell(request) {
  try {
    const response = await fetch(request)
    const type = response.headers.get('content-type') || ''
    if (response.ok && type.includes('text/html')) {
      const cache = await caches.open(CACHE)
      await cache.put(SHELL, response.clone())
    }
    return response
  } catch (error) {
    const cached = await caches.match(SHELL)
    if (cached) return cached
    throw error
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request)
  if (cached) return cached
  const response = await fetch(request)
  if (response.ok) {
    const cache = await caches.open(CACHE)
    await cache.put(request, response.clone())
  }
  return response
}

self.addEventListener('fetch', (event) => {
  const { request } = event
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return
  if (request.mode === 'navigate') {
    event.respondWith(networkFirstShell(request))
  } else if (isStaticAsset(url)) {
    event.respondWith(cacheFirst(request))
  }
})
