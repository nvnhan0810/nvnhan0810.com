import "@sass/app.scss";
import { useForcedDarkTheme } from "@/ts/providers/theme-provider";

type PortfolioLayoutProps = {
  children: React.ReactNode;
};

const PortfolioLayout = ({ children }: PortfolioLayoutProps) => {
  useForcedDarkTheme();

  return (
    <div className="min-h-screen max-w-[100vw] overflow-x-hidden bg-background font-sans antialiased text-foreground">
      {children}
    </div>
  );
};

export default PortfolioLayout;
