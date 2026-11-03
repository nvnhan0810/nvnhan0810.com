import { defineComponent, h, reactive, watchEffect } from 'vue'
import { RouterLink } from 'vue-router'
import { vueRouter } from '../app/router'
import { action, request, WalletsError } from '../infrastructure/wallets-api'
import { session } from '../app/services'
import { preparePageComponent } from '../app/page-components'
import { ApiError } from '../infrastructure/transport'
import type { SharedPageProps } from '../types/inertia'
import { importHolidays } from '../infrastructure/holidays'
export interface PagePayload {
  component: string
  props: Record<string, unknown>
  redirect?: string
  flash?: Record<string, string>
}
export const page = reactive({
  url: '/',
  component: '',
  props: {
    auth: { user: null },
    flash: {},
    errors: {},
    debtGoal: null,
  } as SharedPageProps & Record<string, unknown>,
  revision: 0,
  loading: false,
  error: '',
})
export function usePage<T = SharedPageProps>() {
  return page as typeof page & { props: T }
}
export const Head = defineComponent({
  props: { title: String },
  setup(props) {
    watchEffect(() => {
      document.title = `${props.title || 'MyWallet'} · MyWallet`
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
export interface Options {
  onSuccess?: () => void
  onError?: (errors: Record<string, string>) => void
  onFinish?: () => void
  preserveScroll?: boolean
  preserveState?: boolean
  replace?: boolean
  forceFormData?: boolean
  only?: string[]
}
let requestVersion = 0
export async function loadPage(path: string) {
  const version = ++requestVersion
  page.loading = true
  page.error = ''
  try {
    const result = await request<PagePayload>(
      `/pages?path=${encodeURIComponent(path)}`,
    )
    await preparePageComponent(result.component)
    if (
      version !== requestVersion ||
      vueRouter.currentRoute.value.fullPath !== path
    )
      return
    page.url = path
    page.component = result.component
    Object.assign(page, {
      props: {
        auth: { user: session.state.user },
        flash: {},
        errors: {},
        debtGoal: null,
        ...result.props,
      },
    })
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      await vueRouter.replace('/login')
      return
    }
    if (version === requestVersion)
      page.error =
        error instanceof Error ? error.message : 'Không tải được trang.'
  } finally {
    if (version === requestVersion) page.loading = false
  }
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
      try {
        await session.logout()
      } finally {
        await vueRouter.replace('/login')
      }
      return
    }
    if (path === '/holidays/import')
      data = {
        rows: await importHolidays((data as { file: File | null }).file),
      }
    const result = await action<PagePayload>(path, method, data)
    if (origin !== vueRouter.currentRoute.value.fullPath) return
    if (result.component) {
      await preparePageComponent(result.component)
      if (origin !== vueRouter.currentRoute.value.fullPath) return
      page.component = result.component
      page.props = { ...page.props, ...result.props, errors: {} }
      page.revision++
    } else {
      if (result.redirect && result.redirect !== origin)
        await vueRouter.push(result.redirect)
      await loadPage(vueRouter.currentRoute.value.fullPath)
      page.props.flash = result.flash ?? {}
    }
    options.onSuccess?.()
  } catch (error) {
    const errors =
      error instanceof WalletsError
        ? error.errors
        : {
            request:
              error instanceof Error
                ? error.message
                : 'Không thể lưu thay đổi.',
          }
    page.props.errors = errors
    options.onError?.(errors)
  } finally {
    options.onFinish?.()
  }
}
function get(
  path: string,
  data: Record<string, unknown> = {},
  options: Options = {},
) {
  const url = new URL(path, window.location.origin)
  for (const [key, value] of Object.entries(data))
    if (value !== null && value !== undefined && value !== '')
      url.searchParams.set(key, String(value))
  return (options.replace ? vueRouter.replace : vueRouter.push)(
    url.pathname + url.search,
  ).finally(() => options.onFinish?.())
}
export const router = {
  get,
  visit: (path: string) => vueRouter.push(path),
  reload: () => loadPage(vueRouter.currentRoute.value.fullPath),
  post: (path: string, data: unknown = {}, options?: Options) =>
    submit(path, data, 'POST', options),
  put: (path: string, data: unknown, options?: Options) =>
    submit(path, data, 'PUT', options),
  delete: (path: string, options?: Options) =>
    submit(path, {}, 'DELETE', options),
}
export function useForm<T extends Record<string, unknown>>(defaults: T) {
  const keys = Object.keys(defaults)
  const initial = JSON.parse(JSON.stringify(defaults)) as T
  let transform: (data: T) => unknown = (data) => data
  type Form = T & {
    processing: boolean
    errors: Record<string, string>
    reset: (...keys: (keyof T)[]) => void
    clearErrors: () => void
    setError: (key: string, value: string) => void
    transform: (fn: (data: T) => unknown) => Form
    post: (path: string, options?: Options) => Promise<void>
    put: (path: string, options?: Options) => Promise<void>
    delete: (path: string, options?: Options) => Promise<void>
  }
  const form = reactive({
    ...defaults,
    processing: false,
    errors: {},
  }) as unknown as Form
  const send = async (path: string, method: string, options: Options = {}) => {
    if (form.processing) return
    form.processing = true
    form.errors = {}
    const data = Object.fromEntries(keys.map((key) => [key, form[key]])) as T
    await submit(path, transform(data), method, {
      ...options,
      onError: (errors) => {
        form.errors = errors
        options.onError?.(errors)
      },
      onFinish: () => {
        form.processing = false
        options.onFinish?.()
      },
    })
  }
  form.reset = (...selected) => {
    for (const key of selected.length
      ? selected
      : (Object.keys(initial) as (keyof T)[]))
      form[key] = JSON.parse(JSON.stringify(initial[key]))
  }
  form.clearErrors = () => {
    form.errors = {}
    page.props.errors = {}
  }
  form.setError = (key, value) => {
    form.errors[key] = value
  }
  form.transform = (fn) => {
    transform = fn
    return form
  }
  form.post = (path, options) => send(path, 'POST', options)
  form.put = (path, options) => send(path, 'PUT', options)
  form.delete = (path, options) => send(path, 'DELETE', options)
  return form
}
