import type { ClipboardGateway } from '../application/use-cases/copy-prompt'
export class BrowserClipboard implements ClipboardGateway {
  async write(text: string) {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
      return
    }
    const area = document.createElement('textarea')
    area.value = text
    area.setAttribute('readonly', '')
    area.style.cssText = 'position:fixed;left:-9999px'
    document.body.appendChild(area)
    const focus = document.activeElement
    try {
      area.select()
      if (!document.execCommand('copy'))
        throw new Error('Clipboard unavailable')
    } finally {
      area.remove()
      if (focus instanceof HTMLElement) focus.focus()
    }
  }
}
