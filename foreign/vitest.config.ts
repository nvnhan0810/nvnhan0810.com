import { mergeConfig } from 'vite'
import vite from './vite.config.ts'
export default mergeConfig(vite, {
  test: { environment: 'jsdom', clearMocks: true },
})
