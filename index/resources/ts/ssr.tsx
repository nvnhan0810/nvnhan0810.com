import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import ReactDOMServer from "react-dom/server";

import { I18nProvider } from "./providers/i18n-provider";
import { ThemeProvider } from "./providers/theme-provider";
import type { Locale } from "./i18n";
import { Ziggy } from "./utils/ziggy";

(globalThis as any).Ziggy = Ziggy;

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    resolve: (name) => {
      const pages = import.meta.glob(["./pages/**/*.tsx", "./domains/**/*.tsx"], { eager: true });
      return pages[`./pages/${name}.tsx`] ?? pages[`./${name}.tsx`];
    },
    setup({ App, props }) {
      return (
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
    },
  }),
);
