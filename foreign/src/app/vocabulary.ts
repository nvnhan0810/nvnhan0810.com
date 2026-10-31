import { HttpVocabularyGateway } from '../modules/foreign/infrastructure/vocabulary-api'
import {
  ListMyWords,
  SaveWord,
} from '../modules/foreign/application/use-cases/vocabulary'
const gateway = new HttpVocabularyGateway()
export const saveWord = new SaveWord(gateway)
export const listMyWords = new ListMyWords(gateway)
