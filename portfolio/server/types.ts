export type RenderResult =
  | {
      status: 200
      html: string
      title: string
      description: string
      locale: 'en' | 'vi'
      state: string
    }
  | { status: 302; location: string }

export interface Renderer {
  render(url: string, locale: 'en' | 'vi'): Promise<RenderResult>
}
