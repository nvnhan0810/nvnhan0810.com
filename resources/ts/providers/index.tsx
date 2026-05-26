import { ThemeProvider } from "./theme-provider";

/** @deprecated Use ThemeProvider / I18nProvider directly in App.tsx */
const Providers = ({ children }: { children: React.ReactNode }) => {
  return <ThemeProvider defaultTheme="dark">{children}</ThemeProvider>;
};

export default Providers;
