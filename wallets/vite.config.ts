import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwind from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'
import { serviceWorker } from './pwa/vite-plugin.ts'
export default defineConfig({
  plugins: [vue(), tailwind(), serviceWorker()],
  resolve: {
    alias: {
      '@/Layouts': fileURLToPath(
        new URL('./src/presentation/Layouts', import.meta.url),
      ),
      '@/Components': fileURLToPath(
        new URL('./src/presentation/Components', import.meta.url),
      ),
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
