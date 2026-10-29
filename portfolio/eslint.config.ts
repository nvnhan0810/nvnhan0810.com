import { globalIgnores } from 'eslint/config'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import pluginVue from 'eslint-plugin-vue'
import pluginVitest from '@vitest/eslint-plugin'
import skipFormatting from 'eslint-config-prettier/flat'

const outerLayers = ['**/infrastructure/**', '**/presentation/**', '@/app/**', '**/app/**']
const frameworks = ['vue', 'vue/*', 'vue-router', 'reka-ui', 'lucide-vue-next']

export default defineConfigWithVueTs(
  { files: ['**/*.{vue,ts,mts,tsx}'] },
  globalIgnores(['**/dist/**', '**/coverage/**', '**/node_modules/**', '.sites-runtime/**']),
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.strictTypeChecked,
  {
    files: ['**/*.{vue,ts,mts,tsx}'],
    languageOptions: { parserOptions: { projectService: true } },
    rules: { 'vue/multi-word-component-names': ['error', { ignores: ['App'] }] },
  },
  {
    files: ['src/modules/*/domain/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            ...outerLayers,
            ...frameworks,
            '**/application/**',
            '@/shared/**',
            '**/shared/**',
          ],
        },
      ],
    },
  },
  {
    files: ['src/modules/*/application/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: [...outerLayers, ...frameworks, '@/shared/**', '**/shared/**'] },
      ],
    },
  },
  {
    files: ['src/modules/*/presentation/**/*.{ts,vue}'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: ['**/infrastructure/**', '@/app/**', '**/app/**'] },
      ],
    },
  },
  {
    files: ['src/modules/*/infrastructure/**/*.ts'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: ['**/presentation/**', '@/app/**', '**/app/**', ...frameworks] },
      ],
    },
  },
  {
    files: ['src/shared/**/*.{ts,vue}'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: ['@/modules/**', '**/modules/**', '@/app/**', '**/app/**'] },
      ],
    },
  },
  {
    files: ['src/shared/components/ui/**/*.vue'],
    rules: { 'vue/multi-word-component-names': 'off', 'vue/require-default-prop': 'off' },
  },
  { ...pluginVitest.configs.recommended, files: ['src/**/__tests__/**/*.ts'] },
  skipFormatting,
)
