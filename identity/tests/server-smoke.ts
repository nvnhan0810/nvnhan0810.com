import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { fileURLToPath } from 'node:url'
const root = fileURLToPath(new URL('../', import.meta.url))
const child = spawn(process.execPath, ['server/index.ts'], {
  cwd: root,
  env: { ...process.env, HOST: '127.0.0.1', PORT: '0' },
  stdio: ['ignore', 'pipe', 'pipe'],
})
let diagnostics = ''
child.stderr.on('data', (data) => {
  diagnostics += String(data)
})
try {
  const base = await new Promise<string>((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('Server readiness timeout')), 15000)
    child.on('error', reject)
    child.on('exit', (code) => {
      clearTimeout(timeout)
      reject(new Error(`Server exited ${code}: ${diagnostics}`))
    })
    let output = ''
    child.stdout.on('data', (data) => {
      output += String(data)
      const match = output.match(/http:\/\/127\.0\.0\.1:\d+/)
      if (match) {
        clearTimeout(timeout)
        resolve(match[0])
      }
    })
  })
  for (const path of ['/', '/clients', '/users', '/auth/callback?code=test&state=test']) {
    const response = await fetch(base + path)
    assert.equal(response.status, 200)
    assert.match(await response.text(), /<div id="app"><\/div>/)
    assert.equal(response.headers.get('cache-control'), 'no-store')
    assert.equal(response.headers.get('referrer-policy'), 'no-referrer')
  }
  const html = await (await fetch(base)).text()
  const asset = html.match(/src="(\/assets\/[^\"]+\.js)"/)?.[1]
  assert.ok(asset)
  const bundled = await fetch(base + asset)
  assert.equal(bundled.status, 200)
  assert.match(bundled.headers.get('cache-control') || '', /immutable/)
  assert.equal(await (await fetch(base + '/healthz')).text(), 'ok')
  assert.equal((await fetch(base + '/.env')).status, 404)
  assert.equal((await fetch(base + '/assets/missing.js')).status, 404)
  assert.equal((await fetch(base + '/%2e%2e%2fpackage.json')).status, 403)
  assert.equal((await fetch(base, { method: 'POST' })).status, 405)
  console.log('PASS: SPA routes, callback, assets, health, cache headers and filesystem isolation')
} finally {
  if (child.exitCode === null) {
    child.kill('SIGTERM')
    await once(child, 'close')
  }
}
