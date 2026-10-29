import { createServer, type IncomingMessage, type ServerResponse } from 'node:http'
import { readFile, realpath, stat } from 'node:fs/promises'
import { extname, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import type { Renderer } from './types.ts'
import { resolveLocale, injectHtml } from './render-html.ts'

const root = fileURLToPath(new URL('../', import.meta.url))
const clientDirectory = resolve(root, 'dist/client')
const production = process.argv.includes('--production')
if (production) process.env.NODE_ENV = 'production'
const host = process.env.HOST ?? '127.0.0.1'
const port = Number(process.env.PORT ?? (production ? '3000' : '5173'))
if (!Number.isInteger(port) || port < 0 || port > 65535) throw new Error('Invalid PORT')

const server = createServer()
const vite = production
  ? null
  : await (
      await import('vite')
    ).createServer({
      root,
      appType: 'custom',
      server: { middlewareMode: true, hmr: { server } },
    })

function isRenderer(value: unknown): value is Renderer {
  return (
    typeof value === 'object' &&
    value !== null &&
    'render' in value &&
    typeof value.render === 'function'
  )
}

let productionRenderer: Renderer | null = null
let productionTemplate = ''
if (production) {
  const loaded: unknown = await import(
    new URL('../dist/server/entry-server.js', import.meta.url).href
  )
  if (!isRenderer(loaded)) throw new Error('Invalid SSR server build')
  productionRenderer = loaded
  productionTemplate = await readFile(resolve(clientDirectory, 'index.html'), 'utf8')
}

const mimeTypes: Record<string, string> = {
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
  '.pdf': 'application/pdf',
}

function send(res: ServerResponse, status: number, body: string): void {
  res.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8' })
  res.end(body)
}

async function serveAsset(
  pathname: string,
  req: IncomingMessage,
  res: ServerResponse,
): Promise<boolean> {
  const file = resolve(clientDirectory, `.${pathname}`)
  if (!file.startsWith(`${clientDirectory}${sep}`) || pathname.endsWith('.html')) return false
  try {
    const actual = await realpath(file)
    if (!actual.startsWith(`${clientDirectory}${sep}`) || !(await stat(actual)).isFile())
      return false
    const body = await readFile(actual)
    res.writeHead(200, {
      'Content-Type': mimeTypes[extname(actual)] ?? 'application/octet-stream',
      'Content-Length': body.length,
      'Cache-Control': pathname.startsWith('/assets/')
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=3600',
    })
    res.end(req.method === 'HEAD' ? undefined : body)
    return true
  } catch (error) {
    if (
      error instanceof Error &&
      'code' in error &&
      (error.code === 'ENOENT' || error.code === 'ENOTDIR')
    )
      return false
    throw error
  }
}

async function handle(req: IncomingMessage, res: ServerResponse): Promise<void> {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    res.setHeader('Allow', 'GET, HEAD')
    send(res, 405, 'Method not allowed')
    return
  }
  let url: URL
  let pathname: string
  try {
    url = new URL(req.url ?? '/', 'http://localhost')
    pathname = decodeURIComponent(url.pathname)
    if (pathname.includes('\0') || pathname.includes('\\')) throw new Error('Invalid path')
  } catch {
    send(res, 400, 'Invalid URL')
    return
  }
  res.setHeader('X-Content-Type-Options', 'nosniff')
  if (pathname === '/healthz') {
    res.setHeader('Cache-Control', 'no-store')
    send(res, 200, 'ok')
    return
  }
  if (production && (await serveAsset(pathname, req, res))) return
  if (pathname === '/index.html') {
    res.writeHead(302, { Location: `/${url.search}` })
    res.end()
    return
  }
  if (pathname.startsWith('/assets/') || extname(pathname)) {
    send(res, 404, 'Not found')
    return
  }

  const locale = resolveLocale(url, req.headers.cookie)
  let renderer = productionRenderer
  let template = productionTemplate
  if (vite) {
    template = await vite.transformIndexHtml(
      req.url ?? '/',
      await readFile(resolve(root, 'index.html'), 'utf8'),
    )
    const loaded: unknown = await vite.ssrLoadModule('/src/entry-server.ts')
    if (!isRenderer(loaded)) throw new Error('Invalid SSR entry')
    renderer = loaded
  }
  if (!renderer) throw new Error('SSR renderer is unavailable')
  const result = await renderer.render(`${url.pathname}${url.search}`, locale)
  res.setHeader('Cache-Control', 'private, no-store')
  res.setHeader('Vary', 'Cookie')
  if (result.status === 302) {
    res.writeHead(302, { Location: result.location })
    res.end()
    return
  }
  const html = injectHtml(template, result)
  res.writeHead(200, {
    'Content-Type': 'text/html; charset=utf-8',
    'Content-Length': Buffer.byteLength(html),
    'Content-Language': locale,
  })
  res.end(req.method === 'HEAD' ? undefined : html)
}

function onError(error: unknown, res: ServerResponse): void {
  if (error instanceof Error) vite?.ssrFixStacktrace(error)
  console.error(error)
  if (res.headersSent) {
    res.destroy()
    return
  }
  send(res, 500, 'Unable to render portfolio')
}

server.on('request', (req, res) => {
  const next = (error?: unknown): void => {
    if (error) {
      onError(error, res)
      return
    }
    void handle(req, res).catch((failure: unknown) => {
      onError(failure, res)
    })
  }
  if (vite) vite.middlewares(req, res, next)
  else next()
})

server.listen(port, host, () => {
  const address = server.address()
  if (address && typeof address === 'object')
    console.log(
      `Portfolio SSR (${production ? 'production' : 'development'}): http://${host}:${String(address.port)}`,
    )
})

async function shutdown(): Promise<void> {
  await vite?.close()
  server.close(() => {
    process.exit(0)
  })
}
process.once('SIGINT', () => {
  void shutdown()
})
process.once('SIGTERM', () => {
  void shutdown()
})
