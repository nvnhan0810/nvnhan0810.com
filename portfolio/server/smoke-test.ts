import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../', import.meta.url))

for (const production of [true, false]) {
  const child = spawn(
    process.execPath,
    ['server/index.ts', ...(production ? ['--production'] : [])],
    {
      cwd: root,
      env: { ...process.env, PORT: '0', HOST: '127.0.0.1' },
      stdio: ['ignore', 'pipe', 'pipe'],
    },
  )
  let output = ''
  child.stderr.on('data', (chunk: Buffer) => {
    output += chunk.toString()
  })
  try {
    const origin = await new Promise<string>((resolve, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error(`SSR startup timed out: ${output}`))
      }, 20000)
      child.once('error', (error) => {
        clearTimeout(timeout)
        reject(error)
      })
      child.once('exit', () => {
        clearTimeout(timeout)
        reject(new Error(`SSR exited: ${output}`))
      })
      child.stdout.on('data', (chunk: Buffer) => {
        output += chunk.toString()
        const address = /http:\/\/127\.0\.0\.1:\d+/.exec(output)?.[0]
        if (address) {
          clearTimeout(timeout)
          resolve(address)
        }
      })
    })
    const request = (path: string, options: RequestInit = {}) =>
      fetch(`${origin}${path}`, { ...options, redirect: 'manual' })
    const health = await request('/healthz')
    assert.equal(health.status, 200)
    assert.equal(await health.text(), 'ok')
    const home = await request('/')
    const html = await home.text()
    assert.equal(home.status, 200)
    assert.match(home.headers.get('content-type') ?? '', /text\/html/)
    assert.match(html, /<h1[^>]*>Nguyen Van Nhan<\/h1>/)
    assert.match(html, /id="skills"/)
    assert.doesNotMatch(html, /<!--app-|Loading portfolio|id="blog"/)
    const state = /<script id="portfolio-state" type="application\/json">([\s\S]*?)<\/script>/.exec(
      html,
    )?.[1]
    assert.ok(state)
    const parsed = JSON.parse(state) as { locale: string }
    assert.equal(parsed.locale, 'en')

    const vietnamese = await request('/?lang=vi')
    const viHtml = await vietnamese.text()
    assert.equal(vietnamese.headers.get('content-language'), 'vi')
    assert.match(viHtml, /<html lang="vi"/)
    assert.match(viHtml, /<title>[^<]*Lập trình viên<\/title>/)
    const cookie = await request('/', { headers: { Cookie: 'portfolio-locale=vi' } })
    assert.equal(cookie.headers.get('content-language'), 'vi')
    const english = await request('/?lang=en', { headers: { Cookie: 'portfolio-locale=vi' } })
    assert.equal(english.headers.get('content-language'), 'en')
    const head = await request('/', { method: 'HEAD' })
    assert.equal(head.status, 200)
    assert.equal(await head.text(), '')
    const redirect = await request('/missing')
    assert.equal(redirect.status, 302)
    assert.equal(redirect.headers.get('location'), '/')
    assert.equal((await request('/assets/missing.js')).status, 404)
    assert.equal((await request('/%ZZ')).status, 400)
    assert.equal((await request('/', { method: 'POST' })).status, 405)
    const icon = await request('/images/favicon-32x32.png')
    assert.equal(icon.status, 200)
    assert.match(icon.headers.get('content-type') ?? '', /image\/png/)
    if (production) {
      const cssPath = /href="([^\"]+\.css)"/.exec(html)?.[1]
      assert.ok(cssPath)
      const css = await request(cssPath)
      assert.equal(css.status, 200)
      assert.match(css.headers.get('content-type') ?? '', /text\/css/)
      assert.match(css.headers.get('cache-control') ?? '', /immutable/)
    }
    console.log(
      `PASS: ${production ? 'production' : 'development'} HTTP SSR, locales, assets, HEAD, redirects, error statuses`,
    )
  } finally {
    if (child.exitCode === null && child.signalCode === null) {
      child.kill('SIGTERM')
      await once(child, 'exit')
    }
  }
}
