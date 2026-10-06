import { ADMIN_MODULES } from "@/ts/constants/admin-modules";
import { profile } from "@/ts/constants/profile";
import { AuthUser } from "@/ts/types/auth";
import { Link, router } from "@inertiajs/react";
import {
  BookOpenIcon,
  CircleUserRound,
  Github,
  Linkedin,
  MailIcon,
} from "lucide-react";
import { useRoute } from "ziggy-js";
import { Button } from "../ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "../ui/dropdown-menu";
import ThemeToggle from "../ui/theme-toggle";

const Header = ({ auth }: { auth: AuthUser | null }) => {
  const route = useRoute();

  return (
    <header className="sticky top-0 z-50 w-full max-w-[100vw] overflow-x-hidden border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="mx-auto w-full min-w-0 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex h-14 min-w-0 items-center justify-between gap-2">
          <div className="flex min-w-0 items-center gap-1 overflow-x-auto sm:gap-3">
            <Link
              href={route("posts.index")}
              className="shrink-0 text-muted-foreground hover:text-foreground transition-colors p-2"
              title="Blog"
            >
              <BookOpenIcon className="w-5 h-5" />
            </Link>
            {auth && (
              <nav
                className="flex min-w-0 items-center gap-0.5 sm:gap-1"
                aria-label="Admin modules"
              >
                {ADMIN_MODULES.map((mod) => (
                  <Link
                    key={mod.key}
                    href={route(mod.route)}
                    className="shrink-0 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-sm"
                    title={mod.title}
                  >
                    {mod.label}
                  </Link>
                ))}
              </nav>
            )}
          </div>

          <div className="flex shrink-0 items-center gap-1 sm:gap-2">
            <ThemeToggle />
            <a
              href={profile.githubLink}
              target="_blank"
              rel="noopener noreferrer"
              className="text-muted-foreground hover:text-foreground transition-colors p-2"
              title="GitHub"
            >
              <Github className="w-5 h-5" />
            </a>

            <a
              href={profile.linkedinLink}
              target="_blank"
              rel="noopener noreferrer"
              className="hidden text-muted-foreground hover:text-foreground transition-colors p-2 sm:inline-flex"
              title="LinkedIn"
            >
              <Linkedin className="w-5 h-5" />
            </a>

            <a
              href={`mailto:${profile.email}`}
              className="hidden text-muted-foreground hover:text-foreground transition-colors p-2 sm:inline-flex"
              title="Email"
            >
              <MailIcon className="w-5 h-5" />
            </a>

            {!auth ? (
              <a
                href={route("google.login")}
                className="text-muted-foreground hover:text-foreground transition-colors p-2"
              >
                <CircleUserRound className="w-5 h-5" />
              </a>
            ) : (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="max-w-[7rem] truncate sm:max-w-[12rem]"
                  >
                    {auth.name}
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem
                    onClick={() => router.get(route("logout"))}
                    className="cursor-pointer"
                  >
                    Đăng xuất
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
