export type Locale = "en" | "vi";

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
    contact: string;
    interests: string;
    readBlog: string;
    latestPosts: string;
    viewAllPosts: string;
    allPosts: string;
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
  common: {
    language: string;
  };
  cv: CvData;
};
