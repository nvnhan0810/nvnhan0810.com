export type Locale = 'en' | 'vi'
export type SectionId = 'about' | 'skills' | 'apps' | 'blog' | 'contact'

export interface CvData {
  readonly name: string
  readonly title: string
  readonly phone: string
  readonly email: string
  readonly location: string
  readonly summary: string
  readonly education: {
    readonly school: string
    readonly major: string
    readonly period: string
    readonly gpa: string
  }
  readonly languages: string
  readonly interests: readonly string[]
}

export interface HomeMessages {
  readonly nav: Readonly<Record<SectionId | 'portfolio' | 'admin' | 'news' | 'todosApp', string>>
  readonly home: Readonly<
    Record<
      | 'portfolioLabel'
      | 'contactMe'
      | 'about'
      | 'location'
      | 'phone'
      | 'education'
      | 'skills'
      | 'contact'
      | 'interests'
      | 'readBlog'
      | 'latestPosts'
      | 'viewAllPosts'
      | 'allPosts'
      | 'appsTitle'
      | 'viewAllApps',
      string
    >
  >
  readonly blog: { readonly label: string; readonly noPosts: string }
  readonly apps: {
    readonly label: string
    readonly description: string
    readonly items: Readonly<
      Record<
        string,
        { readonly name: string; readonly summary: string; readonly features: readonly string[] }
      >
    >
  }
  readonly common: { readonly language: string }
  readonly cv: CvData
}

export interface TechTool {
  readonly name: string
  readonly slug: string
  readonly color: string
  readonly iconUrl?: string
}

export interface CatalogApp {
  readonly slug: string
  readonly kind: 'package' | 'project'
  readonly label: string
  readonly githubUrl: string
  readonly packagistUrl?: string
  readonly npmUrl?: string
  readonly tags: readonly string[]
}

export interface Post {
  readonly id: string | number
  readonly slug: string
  readonly title: string
  readonly description?: string | null
  readonly tags?: readonly { readonly id: string | number; readonly name: string }[]
}

export interface Portfolio {
  readonly messages: Readonly<Record<Locale, HomeMessages>>
  readonly profile: {
    readonly githubLink: string
    readonly linkedinLink: string
    readonly email: string
    readonly avatarPath: string
    readonly cvPdfPath: string
  }
  readonly techStack: readonly TechTool[]
  readonly apps: readonly CatalogApp[]
  readonly posts: readonly Post[]
}
