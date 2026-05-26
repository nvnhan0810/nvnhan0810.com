import {
  DEFAULT_LOCALE,
  getMessages,
  isLocale,
  type Locale,
  type Messages,
} from "@/ts/i18n";
import { router } from "@inertiajs/react";
import { createContext, useCallback, useContext, useMemo } from "react";
import { useRoute } from "ziggy-js";

type I18nContextValue = {
  locale: Locale;
  messages: Messages;
  cv: Messages["cv"];
  t: (key: string, params?: Record<string, string>) => string;
  setLocale: (locale: Locale) => void;
};

const I18nContext = createContext<I18nContextValue | null>(null);

function resolveKey(messages: Messages, key: string): string | undefined {
  const parts = key.split(".");
  let current: unknown = messages;

  for (const part of parts) {
    if (current == null || typeof current !== "object") {
      return undefined;
    }
    current = (current as Record<string, unknown>)[part];
  }

  return typeof current === "string" ? current : undefined;
}

function interpolate(
  template: string,
  params?: Record<string, string>
): string {
  if (!params) {
    return template;
  }

  return Object.entries(params).reduce(
    (result, [key, value]) =>
      result.replace(new RegExp(`{{\\s*${key}\\s*}}`, "g"), value),
    template
  );
}

type I18nProviderProps = {
  children: React.ReactNode;
  locale?: string;
};

export const I18nProvider = ({ children, locale: localeProp }: I18nProviderProps) => {
  const route = useRoute();
  const locale: Locale =
    localeProp && isLocale(localeProp) ? localeProp : DEFAULT_LOCALE;
  const messages = useMemo(() => getMessages(locale), [locale]);

  const t = useCallback(
    (key: string, params?: Record<string, string>) => {
      const value = resolveKey(messages, key);
      return interpolate(value ?? key, params);
    },
    [messages]
  );

  const setLocale = useCallback(
    (nextLocale: Locale) => {
      if (nextLocale === locale) {
        return;
      }

      router.post(
        route("locale.update"),
        { locale: nextLocale },
        { preserveScroll: true }
      );
    },
    [locale, route]
  );

  const value = useMemo<I18nContextValue>(
    () => ({
      locale,
      messages,
      cv: messages.cv,
      t,
      setLocale,
    }),
    [locale, messages, t, setLocale]
  );

  return (
    <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
  );
};

export const useTranslation = () => {
  const context = useContext(I18nContext);

  if (!context) {
    throw new Error("useTranslation must be used within I18nProvider");
  }

  return context;
};
