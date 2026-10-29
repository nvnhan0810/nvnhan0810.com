// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { render } from '../../entry-server'
import { escapeHtml, serializeState } from '../ssr-html'
import { injectHtml, resolveLocale } from '../../../server/render-html'

describe('SSR', () => {
  it('renders the portfolio and localized metadata without a browser', async () => {
    const result = await render('/?lang=vi', 'vi')
    expect(result.status).toBe(200)
    if (result.status !== 200) throw new Error('Unexpected redirect')
    expect(result.html).toContain('Nguyễn Văn Nhàn')
    expect(result.html).toContain('Lập trình viên')
    expect(result.html).toContain('id="skills"')
    expect(result.html).not.toContain('id="blog"')
    expect(result.html).not.toContain('Loading portfolio')
    expect(result.title).toContain('Lập trình viên')
    expect(result.description).toContain('Lập trình viên')
    expect(result.state).toContain('"locale":"vi"')
  })

  it('keeps concurrent requests in different locales isolated', async () => {
    const [english, vietnamese] = await Promise.all([render('/', 'en'), render('/', 'vi')])
    if (english.status !== 200 || vietnamese.status !== 200) throw new Error('Unexpected redirect')
    expect(english.html).toContain('Contact me')
    expect(english.html).not.toContain('Liên hệ với tôi')
    expect(vietnamese.html).not.toContain('Contact me')
    expect(english.locale).toBe('en')
    expect(vietnamese.locale).toBe('vi')
  })

  it('returns a real HTTP redirect instruction for unknown routes', async () => {
    expect(await render('/missing', 'en')).toEqual({ status: 302, location: '/' })
  })

  it('escapes metadata and script-closing content without altering JSON values', () => {
    const data = { content: '</script><script>alert("test")</script>&\u2028\u2029' }
    const json = serializeState(data)
    expect(json).not.toContain('<')
    expect(json).not.toContain('&')
    expect(JSON.parse(json)).toEqual(data)
    expect(escapeHtml('<title a="x"> & \'')).toBe('&lt;title a=&quot;x&quot;&gt; &amp; &#39;')
  })

  it('inserts literal dollar sequences and uses validated locale preferences', () => {
    const template =
      '<html lang="<!--app-lang-->"><title><!--app-title--></title><!--app-description--><!--app-html--><!--app-state--></html>'
    expect(
      injectHtml(template, {
        status: 200,
        locale: 'en',
        title: '$&',
        description: '$`',
        html: "$'",
        state: '$&',
      }),
    ).toBe('<html lang="en"><title>$&</title>$`$\'$&</html>')
    expect(resolveLocale(new URL('http://localhost/?lang=vi'), 'portfolio-locale=en')).toBe('vi')
    expect(resolveLocale(new URL('http://localhost/'), 'other=x; portfolio-locale=vi')).toBe('vi')
    expect(
      resolveLocale(new URL('http://localhost/?lang=invalid'), 'portfolio-locale=invalid'),
    ).toBe('en')
  })
})
