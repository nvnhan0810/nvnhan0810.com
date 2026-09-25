import SiteNav from "@/ts/components/common/SiteNav";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { cn } from "@/ts/utils";
import "@sass/app.scss";
import { AuthUser } from "../types/auth";

export type RootProps = {
  auth: AuthUser | null;
  locale: "en" | "vi";
};

type PublicLayoutProps = RootProps & {
  children: React.ReactNode;
  active?: "home" | "blog" | "apps" | "news";
  /** Full viewport content width (list pages). Header/nav stays container-width. */
  wide?: boolean;
};

const PublicLayout = ({
  children,
  auth,
  active = "blog",
  wide = false,
}: PublicLayoutProps) => {
  const { cv } = useTranslation();

  return (
    <div className="min-h-screen max-w-[100vw] overflow-x-hidden bg-background font-sans antialiased text-foreground">
      <SiteNav auth={auth} active={active} />
      <main
        className={cn(
          "mx-auto w-full min-w-0 flex-grow overflow-x-hidden px-4 py-10 sm:px-6 sm:py-12",
          wide ? "max-w-none" : "max-w-5xl"
        )}
      >
        {children}
      </main>
      <footer className="border-t border-border/60 py-8 text-center text-sm text-muted-foreground">
        © {new Date().getFullYear()} {cv.name}
      </footer>
    </div>
  );
};

export default PublicLayout;
