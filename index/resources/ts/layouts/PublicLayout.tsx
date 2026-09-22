import SiteNav from "@/ts/components/common/SiteNav";
import { useTranslation } from "@/ts/providers/i18n-provider";
import "@sass/app.scss";
import { AuthUser } from "../types/auth";

export type RootProps = {
  auth: AuthUser | null;
  locale: "en" | "vi";
};

type PublicLayoutProps = RootProps & {
  children: React.ReactNode;
  active?: "home" | "blog" | "apps" | "news";
};

const PublicLayout = ({ children, auth, active = "blog" }: PublicLayoutProps) => {
  const { cv } = useTranslation();

  return (
    <div className="min-h-screen max-w-[100vw] overflow-x-hidden bg-background font-sans antialiased text-foreground">
      <SiteNav auth={auth} active={active} />
      <main className="mx-auto w-full min-w-0 max-w-5xl flex-grow overflow-x-hidden px-4 py-10 sm:px-6 sm:py-12">
        {children}
      </main>
      <footer className="border-t border-border/60 py-8 text-center text-sm text-muted-foreground">
        © {new Date().getFullYear()} {cv.name}
      </footer>
    </div>
  );
};

export default PublicLayout;
