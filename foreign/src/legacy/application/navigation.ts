// Vue Router and JSON transport for the preserved source pages.
import { defineComponent, h, reactive, watchEffect } from 'vue'
import { RouterLink } from 'vue-router'
import { router as vueRouter } from '../../app/router'
import { withLoading } from '../../shared/loading'
import { session } from '../../app/services'
import { logout } from '../../app/auth'
import { foreignRequest } from '../../modules/foreign/infrastructure/foreign-api'

export interface PageProps {
  flash?: { success?: string; error?: string }
  errors?: Record<string, string>
  isFlcApp?: boolean
  flcTheme?: string | null
  [key: string]: unknown
}
export const page = reactive<{
  url: string
  props: PageProps
  revision: number
}>({ url: '/', props: {}, revision: 0 })
export function usePage() {
  return page
}
export const Head = defineComponent({
  props: { title: String },
  setup(props) {
    watchEffect(() => {
      document.title = `${props.title || 'FLC'} · FLC`
    })
    return () => null
  },
})
export const Link = defineComponent({
  inheritAttrs: false,
  props: { href: { type: String, required: true } },
  setup(props, { attrs, slots }) {
    return () => h(RouterLink, { ...attrs, to: props.href }, slots)
  },
})
interface Options {
  background?: boolean
  onSuccess?: () => void
  onError?: () => void
  onFinish?: () => void
  onCancel?: () => void
  preserveScroll?: boolean
  preserveState?: boolean
  only?: string[]
}
export async function loadPage(path: string, background = false) {
  const result = await foreignRequest<PageProps>(
    `/pages?path=${encodeURIComponent(path)}`,
    {},
    { background },
  )
  if (vueRouter.currentRoute.value.fullPath !== path) return
  if (typeof result.redirect === 'string') {
    await vueRouter.replace(result.redirect)
    return
  }
  page.url = path
  page.props = { ...result, isFlcApp: navigator.userAgent.includes('FLCApp/') }
}
async function submit(
  path: string,
  data: unknown,
  method: string,
  options: Options = {},
) {
  const origin = vueRouter.currentRoute.value.fullPath
  try {
    if (path === '/logout') {
      await logout()
      await vueRouter.replace('/login')
      return
    }
    const result = await foreignRequest<{
      redirect?: string
      props?: PageProps
    }>('/actions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        path,
        data,
        method,
        round_id: page.props.roundId ?? null,
      }),
    })
    if (origin !== vueRouter.currentRoute.value.fullPath) return
    if (
      result.redirect &&
      result.redirect !== vueRouter.currentRoute.value.fullPath
    )
      await vueRouter.push(result.redirect)
    else if (result.props) {
      page.props = {
        ...result.props,
        isFlcApp: navigator.userAgent.includes('FLCApp/'),
      }
      page.revision++
    } else await loadPage(vueRouter.currentRoute.value.fullPath)
    options.onSuccess?.()
  } catch (error) {
    if (origin !== vueRouter.currentRoute.value.fullPath) return
    page.props.errors = {
      request:
        error instanceof Error
          ? error.message
          : 'Request failed. Please retry.',
    }
    options.onError?.()
  } finally {
    options.onFinish?.()
  }
}
export const router = {
  visit: (path: string) => vueRouter.push(path),
  put: (path: string, data: unknown, options?: Options) =>
    submit(path, data, 'PUT', options),
  reload: async (options: Options = {}) => {
    try {
      await loadPage(
        vueRouter.currentRoute.value.fullPath,
        options.background ?? false,
      )
    } finally {
      options.onFinish?.()
    }
  },
}
export function useForm<T extends Record<string, unknown>>(defaults: T) {
  const keys = Object.keys(defaults)
  let transform: (data: T) => unknown = (data) => data
  const form = reactive({
    ...structuredClone(defaults),
    processing: false,
    errors: {} as Record<string, string>,
  }) as unknown as T & {
    processing: boolean
    errors: Record<string, string>
    reset: () => void
    clearErrors: () => void
    transform: (fn: (data: T) => unknown) => typeof form
    post: (path: string, options?: Options) => Promise<void>
    delete: (path: string, options?: Options) => Promise<void>
  }
  const send = async (path: string, method: string, options: Options = {}) => {
    if (form.processing) return
    form.processing = true
    const data = Object.fromEntries(keys.map((key) => [key, form[key]])) as T
    await submit(path, transform(data), method, {
      ...options,
      onFinish: () => {
        form.processing = false
        options.onFinish?.()
      },
    })
  }
  form.reset = () => {
    Object.assign(form, structuredClone(defaults))
  }
  form.clearErrors = () => {
    form.errors = {}
    page.props.errors = {}
  }
  form.transform = (fn) => {
    transform = fn
    return form
  }
  form.post = (path, options) => send(path, 'POST', options)
  form.delete = (path, options) => send(path, 'DELETE', options)
  return form
}
export async function apiFetch(path: string, init: RequestInit = {}) {
  return withLoading(async () => {
    const token = await session.accessToken()
    const response = await fetch(`${session.settings.baseUrl}/foreign${path}`, {
      ...init,
      signal: init.signal ?? AbortSignal.timeout(15000),
      headers: { ...init.headers, Authorization: `Bearer ${token}` },
    })
    await response.clone().arrayBuffer()
    return response
  })
}
