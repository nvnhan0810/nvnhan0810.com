import LocaleSwitcher from "@/ts/components/common/LocaleSwitcher";
import { profile } from "@/ts/constants/profile";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { AuthUser } from "@/ts/types/auth";
import { Link } from "@inertiajs/react";
import {
  BookOpen,
  Boxes,
  Github,
  Home,
  Linkedin,
  Mail,
  Settings,
} from "lucide-react";
import { useRoute } from "ziggy-js";

type SiteNavProps = {
  auth: AuthUser | null;
  active?: "home" | "blog" | "apps";
};

const linkClass = (isActive: boolean) =>
  isActive
    ? "text-emerald-500"
    : "text-muted-foreground transition-colors hover:text-foreground";

const SiteNav = ({ auth, active = "blog" }: SiteNavProps) => {
  const route = useRoute();
  const { cv, t } = useTranslation();

  return (
    <nav className="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur-md">
      <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link href={route("home")} className="text-sm font-semibold tracking-tight">
          {cv.name}
        </Link>

        <div className="flex items-center gap-3 text-sm sm:gap-4">
          <Link
            href={route("home")}
            className={`inline-flex items-center gap-1.5 ${linkClass(active === "home")}`}
          >
            <Home className="h-4 w-4" />
            <span className="hidden sm:inline">{t("nav.portfolio")}</span>
          </Link>
          <Link
            href={route("posts.index")}
            className={`inline-flex items-center gap-1.5 ${linkClass(active === "blog")}`}
          >
            <BookOpen className="h-4 w-4" />
            {t("nav.blog")}
          </Link>
          <Link
            href={route("apps.index")}
            className={`inline-flex items-center gap-1.5 ${linkClass(active === "apps")}`}
          >
            <Boxes className="h-4 w-4" />
            <span className="hidden sm:inline">{t("nav.apps")}</span>
          </Link>

          {auth && (
            <Link
              href={route("admin.index")}
              className="text-muted-foreground transition-colors hover:text-foreground"
              title={t("nav.admin")}
            >
              <Settings className="h-4 w-4" />
            </Link>
          )}

          <LocaleSwitcher />

          <span className="hidden h-4 w-px bg-border sm:block" />

          <a
            href={profile.githubLink}
            target="_blank"
            rel="noopener noreferrer"
            className="hidden text-muted-foreground transition-colors hover:text-foreground sm:block"
            title="GitHub"
          >
            <Github className="h-4 w-4" />
          </a>
          <a
            href={profile.linkedinLink}
            target="_blank"
            rel="noopener noreferrer"
            className="hidden text-muted-foreground transition-colors hover:text-foreground sm:block"
            title="LinkedIn"
          >
            <Linkedin className="h-4 w-4" />
          </a>
          <a
            href={`mailto:${profile.email}`}
            className="hidden text-muted-foreground transition-colors hover:text-foreground sm:block"
            title="Email"
          >
            <Mail className="h-4 w-4" />
          </a>
        </div>
      </div>
    </nav>
  );
};

export default SiteNav;
