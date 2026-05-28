import { profile } from "@/ts/constants/profile";
import { AuthUser } from "@/ts/types/auth";
import { Link, router } from "@inertiajs/react";
import { BookOpenIcon, CircleUserRound, ListCollapse, MailIcon, TagIcon, Github, Linkedin } from "lucide-react";
import { useRoute } from "ziggy-js";
import { Button } from "../ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "../ui/dropdown-menu";

const Header = ({ auth }: { auth: AuthUser | null }) => {
  const route = useRoute();

  return (
    <header className="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex h-14 items-center justify-between">
          <div className="flex items-center gap-4">
            <Link href={route('posts.index')} className="text-muted-foreground hover:text-foreground transition-colors p-2" title="Blog">
              <BookOpenIcon className="w-5 h-5" />
            </Link>
            {auth && (
              <>
                <Link href={route('admin.tags.index')} className="text-muted-foreground hover:text-foreground transition-colors p-2" title="Quản lý thẻ">
                  <TagIcon className="w-5 h-5" />
                </Link>
                <Link href={route('admin.series.index')} className="text-muted-foreground hover:text-foreground transition-colors p-2" title="Quản lý series">
                  <ListCollapse className="w-5 h-5" />
                </Link>
              </>
            )}
          </div>
          
          <div className="flex items-center gap-2">
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
              className="text-muted-foreground hover:text-foreground transition-colors p-2"
              title="LinkedIn"
            >
              <Linkedin className="w-5 h-5" />
            </a>

            <a href={`mailto:${profile.email}`} className="text-muted-foreground hover:text-foreground transition-colors p-2" title="Email">
              <MailIcon className="w-5 h-5" />
            </a>

            {!auth ? (
              <a href={route('google.login')} className="text-muted-foreground hover:text-foreground transition-colors p-2">
                <CircleUserRound className="w-5 h-5" />
              </a>
            ) : (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="sm">{auth.name}</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => router.get(route('logout'))} className="cursor-pointer">
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
