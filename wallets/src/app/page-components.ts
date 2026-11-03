import { markRaw, shallowReactive, type Component } from 'vue'

const loaders = import.meta.glob<{ default: Component }>(
  '../presentation/Pages/**/*.vue',
)
export const pageComponents = shallowReactive<Record<string, Component>>({})

export async function preparePageComponent(name: string) {
  if (pageComponents[name]) return
  const loader = loaders[`../presentation/Pages/${name}.vue`]
  if (loader) pageComponents[name] = markRaw((await loader()).default)
}
