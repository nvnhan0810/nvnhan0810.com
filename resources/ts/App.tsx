import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";

import { I18nProvider } from "./providers/i18n-provider";
import { ThemeProvider } from "./providers/theme-provider";
import type { Locale } from "./i18n";
import { Ziggy } from "./utils/ziggy";

type GlobalWithZiggy = typeof globalThis & { Ziggy?: typeof Ziggy };
type WindowWithAnalytics = Window & {
  gtag?: (...args: unknown[]) => void;
  __gaInertiaHookInstalled?: boolean;
};

(globalThis as GlobalWithZiggy).Ziggy = Ziggy;

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob(["./pages/**/*.tsx", "./domains/**/*.tsx"], { eager: true });
    return pages[`./pages/${name}.tsx`] ?? pages[`./${name}.tsx`];
  },
  setup({ el, App, props }) {
    if (typeof window !== "undefined") {
      const analyticsWindow = window as WindowWithAnalytics;

      if (!analyticsWindow.__gaInertiaHookInstalled) {
        analyticsWindow.__gaInertiaHookInstalled = true;

        const trackPageView = (url: string) => {
          analyticsWindow.gtag?.("event", "page_view", {
            page_path: url,
            page_location: `${window.location.origin}${url}`,
            page_title: document.title,
          });
        };

        let currentUrl = `${window.location.pathname}${window.location.search}`;
        trackPageView(currentUrl);

        router.on("success", (event) => {
          const nextUrl = event.detail.page.url;
          if (nextUrl === currentUrl) {
            return;
          }

          currentUrl = nextUrl;
          trackPageView(nextUrl);
        });
      }
    }

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
