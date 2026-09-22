import "@sass/app.scss";

type PortfolioLayoutProps = {
  children: React.ReactNode;
};

const PortfolioLayout = ({ children }: PortfolioLayoutProps) => {
  return (
    <div className="min-h-screen max-w-[100vw] overflow-x-hidden bg-background font-sans antialiased text-foreground">
      {children}
    </div>
  );
};

export default PortfolioLayout;
