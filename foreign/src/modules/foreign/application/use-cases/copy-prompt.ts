import type { DictionaryEntry } from '../../domain/dictionary'
import {
  buildMeaningsAiPrompt,
  promptMeanings,
} from '../../domain/meanings-prompt'
export interface ClipboardGateway {
  write(text: string): Promise<void>
}
export class CopyPrompt {
  constructor(private readonly clipboard: ClipboardGateway) {}
  execute(entry: DictionaryEntry) {
    return this.clipboard.write(
      buildMeaningsAiPrompt(entry.word, promptMeanings(entry), {
        ...entry,
        audio_url: entry.phonetics.find((p) => p.audio)?.audio ?? '',
      }),
    )
  }
}
