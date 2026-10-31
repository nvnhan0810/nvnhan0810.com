import { CopyPrompt } from '../modules/foreign/application/use-cases/copy-prompt'
import { BrowserClipboard } from '../modules/foreign/infrastructure/clipboard'
export const copyPrompt = new CopyPrompt(new BrowserClipboard())
