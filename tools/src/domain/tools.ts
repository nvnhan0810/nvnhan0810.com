export interface Tool {
  readonly slug: string
  readonly name: string
  readonly category: string
  readonly description: string
  readonly platforms: readonly string[]
}

export const agent: Tool = {
  slug: 'agent',
  name: 'nvn-agent',
  category: 'Developer tools / CLI',
  description: 'Một workspace ngay trong terminal. Làm việc với AI, duyệt file, chỉnh sửa code và xem Git trong cùng một giao diện.',
  platforms: ['Linux x86_64', 'WSL Intel', 'macOS dòng M'],
}

export const tools: readonly Tool[] = [agent]
