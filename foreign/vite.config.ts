import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@/Layouts': fileURLToPath(
        new URL('./src/legacy/presentation/Layouts', import.meta.url),
      ),
      '@/Components': fileURLToPath(
        new URL('./src/legacy/presentation/Components', import.meta.url),
      ),
      '@': fileURLToPath(new URL('./src/legacy', import.meta.url)),
    },
  },
})
