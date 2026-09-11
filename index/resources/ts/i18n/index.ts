import en from "./locales/en";
import vi from "./locales/vi";
import type { Locale, Messages } from "./types";

export const DEFAULT_LOCALE: Locale = "en";

export const locales: Record<Locale, Messages> = {
  en,
  vi,
};

export function isLocale(value: string): value is Locale {
  return value === "en" || value === "vi";
}

export function getMessages(locale: string): Messages {
  if (isLocale(locale)) {
    return locales[locale];
  }

  return locales[DEFAULT_LOCALE];
}

export type { Locale, Messages, CvData } from "./types";
