export type TechTool = {
  name: string;
  /** https://simpleicons.org slug */
  slug: string;
  /** Brand hex without # — used by cdn.simpleicons.org */
  color: string;
  /** When cdn.simpleicons.org does not host the icon */
  iconUrl?: string;
};

export const techStack: TechTool[] = [
  { name: "PHP", slug: "php", color: "777BB4" },
  { name: "Laravel", slug: "laravel", color: "FF2D20" },
  { name: "JavaScript", slug: "javascript", color: "F7DF1E" },
  { name: "TypeScript", slug: "typescript", color: "3178C6" },
  { name: "React", slug: "react", color: "61DAFB" },
  { name: "Vue.js", slug: "vuedotjs", color: "4FC08D" },
  { name: "Nuxt", slug: "nuxt", color: "00DC82" },
  { name: "Inertia", slug: "inertia", color: "9553E9" },
  { name: "Tailwind CSS", slug: "tailwindcss", color: "06B6D4" },
  { name: "MySQL", slug: "mysql", color: "4479A1" },
  { name: "Redis", slug: "redis", color: "FF4438" },
  { name: "Flutter", slug: "flutter", color: "02569B" },
  { name: "Docker", slug: "docker", color: "2496ED" },
  {
    name: "AWS",
    slug: "amazonwebservices",
    color: "FF9900",
    iconUrl: "/images/tech/aws.svg",
  },
  { name: "Google Cloud", slug: "googlecloud", color: "4285F4" },
  { name: "Git", slug: "git", color: "F05032" },
  { name: "Nginx", slug: "nginx", color: "009639" },
];

export const techIconUrl = (tool: TechTool): string =>
  tool.iconUrl ?? `https://cdn.simpleicons.org/${tool.slug}/${tool.color}`;
