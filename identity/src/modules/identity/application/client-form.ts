import type { ClientInput } from '../domain/models'
export interface ClientDraft {
  name: string
  domain: string
  client_type: 'public' | 'confidential'
  redirects: string
  scopes: string
  enabled: boolean
}
export function clientInput(draft: ClientDraft): ClientInput {
  const redirects = draft.redirects
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
  const scopes = draft.scopes.split(/\s+/).filter(Boolean)
  if (!draft.name.trim() || draft.name.trim().length > 255)
    throw new Error('Tên client phải có từ 1 đến 255 ký tự.')
  if (!redirects.length || redirects.length > 20)
    throw new Error('Cần từ 1 đến 20 callback URI, mỗi URI một dòng.')
  if (new Set(redirects).size !== redirects.length)
    throw new Error('Callback URI không được trùng nhau.')
  for (const value of redirects) {
    let url: URL
    try {
      url = new URL(value)
    } catch {
      throw new Error(`Callback không hợp lệ: ${value}`)
    }
    const web = url.protocol === 'https:'
    const loopback = url.protocol === 'http:' && ['127.0.0.1', '[::1]'].includes(url.hostname)
    const native = url.protocol.includes('.') && !['http:', 'https:'].includes(url.protocol)
    if (
      (!web && !loopback && !native) ||
      url.username ||
      url.password ||
      url.hash ||
      /[\s\\*]/.test(value)
    )
      throw new Error(
        'Callback phải dùng HTTPS, HTTP IP loopback hoặc reverse-domain scheme; không chứa credentials, fragment hay wildcard.',
      )
  }
  if (
    !scopes.length ||
    scopes.length > 100 ||
    scopes.some((scope) => scope.length > 128 || !/^[\x21\x23-\x5B\x5D-\x7E]+$/.test(scope))
  )
    throw new Error('Scopes chưa hợp lệ. Tách các scope bằng khoảng trắng hoặc xuống dòng.')
  if (new Set(scopes).size !== scopes.length) throw new Error('Scopes không được trùng nhau.')
  return {
    name: draft.name.trim(),
    domain: draft.domain.trim() || null,
    client_type: draft.client_type,
    redirect_uris: redirects,
    scopes,
    enabled: draft.enabled,
  }
}
