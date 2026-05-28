export type Locale = "en" | "vi";

export type CvProject = {
  name: string;
  description: string;
  stack: string[];
  links?: { label: string; href: string }[];
};

export type CvExperience = {
  company: string;
  period: string;
  highlights: string[];
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
  skills: { name: string; level: number }[];
  experience: CvExperience[];
  projects: CvProject[];
  languages: string;
  interests: string[];
};

export type Messages = {
  nav: {
    portfolio: string;
    blog: string;
    about: string;
    skills: string;
    experience: string;
    projects: string;
    contact: string;
    admin: string;
  };
  home: {
    portfolioLabel: string;
    downloadCv: string;
    contactMe: string;
    about: string;
    location: string;
    phone: string;
    education: string;
    skills: string;
    workExperience: string;
    projects: string;
    contact: string;
    interests: string;
    readBlog: string;
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
  };
  common: {
    language: string;
  };
  cv: CvData;
};
