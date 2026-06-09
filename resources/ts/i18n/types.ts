export type Locale = "en" | "vi";

export type AppItemMessages = {
  name: string;
  summary: string;
  features: string[];
};

export type AppDetailMessages = {
  name: string;
  tagline: string;
  metaDescription: string;
  summary: string;
  highlights: string[];
  featureGroups?: { title: string; items: string[] }[];
  learningFlow?: {
    title: string;
    intro: string;
    loop: string[];
    stepColumn: string;
    extensionColumn: string;
    mobileColumn: string;
    steps: { step: string; extension: string; mobile: string }[];
    note: string;
  };
  whenToUse?: {
    title: string;
    situationColumn: string;
    suggestionColumn: string;
    items: { situation: string; suggestion: string }[];
  };
  shortcuts?: {
    title: string;
    shortcutColumn: string;
    actionColumn: string;
    items: { shortcut: string; action: string }[];
  };
  layers?: {
    title: string;
    description?: string;
    items: { name: string; description: string; stack: string }[];
  };
  techStack: {
    title: string;
    items: { component: string; tech: string }[];
  };
  repoStructure: {
    title: string;
    items: { folder: string; description: string }[];
  };
  platformsNote?: string;
};

export type CvData = {
  name: string;
  title: string;
  phone: string;
  email: string;
  location: string;
  summary: string;
  education: {
    school: string;
    major: string;
    period: string;
    gpa: string;
  };
  languages: string;
  interests: string[];
};

export type Messages = {
  nav: {
    portfolio: string;
    blog: string;
    about: string;
    skills: string;
    contact: string;
    admin: string;
    apps: string;
  };
  home: {
    portfolioLabel: string;
    contactMe: string;
    about: string;
    location: string;
    phone: string;
    education: string;
    skills: string;
    contact: string;
    interests: string;
    readBlog: string;
    latestPosts: string;
    viewAllPosts: string;
    allPosts: string;
    appsTitle: string;
    viewAllApps: string;
  };
  blog: {
    label: string;
    latestPosts: string;
    taggedPosts: string;
    tags: string;
    noPosts: string;
    metaDescription: string;
    searchPlaceholder: string;
    search: string;
    backToBlog: string;
    article: string;
    series: string;
    sourceOriginal: string;
    editPost: string;
  };
  apps: {
    label: string;
    title: string;
    description: string;
    metaDescription: string;
    featuresLabel: string;
    viewOnPackagist: string;
    viewOnNpm: string;
    viewOnGithub: string;
    backToApps: string;
    viewDetails: string;
    items: Record<string, AppItemMessages>;
    details: Record<string, AppDetailMessages>;
  };
  common: {
    language: string;
  };
  cv: CvData;
};
