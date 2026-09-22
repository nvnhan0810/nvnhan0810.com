import Header from "@components/common/Header";
import "@sass/app.scss";
import Footer from "../components/common/Footer";
import { AuthUser } from "../types/auth";

export type RootProps = {
  auth: AuthUser | null;
}

type PrivateLayoutProps = RootProps & {
  children: React.ReactNode;
};

const PrivateLayout = ({ children, auth }: PrivateLayoutProps) => {
  return (
    <div className="min-h-screen max-w-[100vw] overflow-x-hidden bg-background p-4 font-sans antialiased scroll-smooth scroll-pt-24">
      <Header auth={auth} />
      <div className="min-w-0 flex-grow py-4">
        {children}
      </div>
      <Footer />
    </div>
  );
};

export default PrivateLayout;
