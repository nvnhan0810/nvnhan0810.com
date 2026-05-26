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
};

const PublicLayout = ({ children, auth }: PublicLayoutProps) => {
  const { cv } = useTranslation();

  return (
    <div className="min-h-screen bg-background font-sans antialiased text-foreground">
      <SiteNav auth={auth} active="blog" />
      <main className="mx-auto max-w-5xl flex-grow px-4 py-10 sm:px-6 sm:py-12">
        {children}
      </main>
      <footer className="border-t border-border/60 py-8 text-center text-sm text-muted-foreground">
        © {new Date().getFullYear()} {cv.name}
      </footer>
    </div>
  );
};

export default PublicLayout;
