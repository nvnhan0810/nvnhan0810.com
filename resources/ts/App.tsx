import { createInertiaApp } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";

import { I18nProvider } from "./providers/i18n-provider";
import { ThemeProvider } from "./providers/theme-provider";
import type { Locale } from "./i18n";
import { Ziggy } from "./utils/ziggy";

type GlobalWithZiggy = typeof globalThis & { Ziggy?: typeof Ziggy };

(globalThis as GlobalWithZiggy).Ziggy = Ziggy;

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob("./pages/**/*.tsx", { eager: true });
    return pages[`./pages/${name}.tsx`];
  },
  setup({ el, App, props }) {
    const appNode = (
      <ThemeProvider defaultTheme="dark">
        <App {...props}>
          {({ Component, props: pageProps, key }) => (
            <I18nProvider locale={pageProps.locale as Locale}>
              <Component key={key} {...pageProps} />
            </I18nProvider>
          )}
        </App>
      </ThemeProvider>
    );

    if (el.hasChildNodes()) {
      hydrateRoot(el, appNode);
      return;
    }

    createRoot(el).render(appNode);
  },
});
