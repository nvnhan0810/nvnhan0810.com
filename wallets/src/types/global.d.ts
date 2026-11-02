/// <reference types="vite/client" />
export {}
declare global {
  function route(
    name: string,
    params?: Record<string, unknown> | number | string,
  ): string
}
declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof route
  }
}
