import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import { extname, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
const root = resolve(fileURLToPath(new URL('../dist/', import.meta.url)))
const port = Number(process.env.PORT || 8080)
const host = process.env.HOST || '0.0.0.0'
const types: Record<string, string> = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
}
const server = createServer(async (request, response) => {
  response.setHeader('X-Content-Type-Options', 'nosniff')
  response.setHeader('Referrer-Policy', 'no-referrer')
  response.setHeader('X-Frame-Options', 'DENY')
  response.setHeader('Cache-Control', 'no-store')
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    response.writeHead(405, { Allow: 'GET, HEAD' }).end()
    return
  }
  let path: string
  try {
    path = decodeURIComponent(
      new URL(request.url || '/', 'http://localhost').pathname,
    )
  } catch {
    response.writeHead(400).end()
    return
  }
  if (path === '/healthz') {
    response
      .writeHead(200, { 'Content-Type': 'text/plain' })
      .end(request.method === 'HEAD' ? undefined : 'ok')
    return
  }
  const target = resolve(root, `.${path}`)
  if (target !== root && !target.startsWith(root + sep)) {
    response.writeHead(403).end()
    return
  }
  if (path.split('/').some((segment) => segment.startsWith('.'))) {
    response.writeHead(404).end()
    return
  }
  try {
    let content: Buffer
    let extension = extname(target)
    try {
      content = await readFile(
        target === root ? resolve(root, 'index.html') : target,
      )
      if (target === root) extension = '.html'
    } catch {
      if (extension) {
        response.writeHead(404).end()
        return
      }
      content = await readFile(resolve(root, 'index.html'))
      extension = '.html'
    }
    response.setHeader(
      'Content-Type',
      types[extension] || 'application/octet-stream',
    )
    if (path.startsWith('/assets/') && extension !== '.html')
      response.setHeader('Cache-Control', 'public, max-age=31536000, immutable')
    response.writeHead(200).end(request.method === 'HEAD' ? undefined : content)
  } catch {
    response.writeHead(503).end('Application build unavailable')
  }
})
server.listen(port, host, () =>
  console.log(
    `Foreign listening on http://${host}:${(server.address() as import('node:net').AddressInfo).port}`,
  ),
)
function shutdown() {
  server.close(() => process.exit(0))
  setTimeout(() => process.exit(1), 10000).unref()
}
process.on('SIGTERM', shutdown)
process.on('SIGINT', shutdown)
