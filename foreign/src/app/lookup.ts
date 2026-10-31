import { LookupWord } from '../modules/foreign/application/use-cases/lookup-word'
import { lookupWord } from '../modules/foreign/infrastructure/dictionary-api'
export const lookup = new LookupWord({ lookup: lookupWord })
