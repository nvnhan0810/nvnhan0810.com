export type AppItemMessages = {
  name: string
  summary: string
  features: string[]
}

export type AppDetailMessages = {
  name: string
  tagline: string
  metaDescription: string
  summary: string
  highlights: string[]
  featureGroups?: { title: string; items: string[] }[]
  learningFlow?: {
    title: string
    intro: string
    loop: string[]
    stepColumn: string
    extensionColumn: string
    mobileColumn: string
    steps: { step: string; extension: string; mobile: string }[]
    note: string
  }
  whenToUse?: {
    title: string
    situationColumn: string
    suggestionColumn: string
    items: { situation: string; suggestion: string }[]
  }
  shortcuts?: {
    title: string
    shortcutColumn: string
    actionColumn: string
    items: { shortcut: string; action: string }[]
  }
  layers?: {
    title: string
    description?: string
    items: { name: string; description: string; stack: string }[]
  }
  techStack: {
    title: string
    items: { component: string; tech: string }[]
  }
  repoStructure: {
    title: string
    items: { folder: string; description: string }[]
  }
  platformsNote?: string
}

export interface AppsMessages {
  apps: {
    label: string
    title: string
    description: string
    metaDescription: string
    featuresLabel: string
    viewOnPackagist: string
    viewOnNpm: string
    viewOnGithub: string
    backToApps: string
    viewDetails: string
    items: Record<string, AppItemMessages>
    details: Record<string, AppDetailMessages>
  }
}
