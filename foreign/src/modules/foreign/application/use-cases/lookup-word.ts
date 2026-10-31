import type { DictionaryGateway } from '../ports/dictionary'
export function normalizeLookupWord(input: string): string {
  const word = input.trim().toLowerCase()
  if (!word || word.length > 100 || !/^[a-z '\-]+$/.test(word))
    throw new Error('Enter an English word or phrase (up to 100 characters).')
  return word
}
export class LookupWord {
  constructor(private readonly gateway: DictionaryGateway) {}
  execute(input: string, signal: AbortSignal) {
    return this.gateway.lookup(normalizeLookupWord(input), signal)
  }
}
