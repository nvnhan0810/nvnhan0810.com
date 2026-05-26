import { useTranslation } from "@/ts/providers/i18n-provider";
import type { Locale } from "@/ts/i18n";
import { cn } from "@/ts/utils";

const options: { code: Locale; label: string }[] = [
  { code: "en", label: "EN" },
  { code: "vi", label: "VI" },
];

const LocaleSwitcher = ({ className }: { className?: string }) => {
  const { locale, setLocale } = useTranslation();

  return (
    <div
      className={cn(
        "inline-flex rounded-md border border-border p-0.5 text-xs font-medium",
        className
      )}
      role="group"
      aria-label="Language"
    >
      {options.map((option) => (
        <button
          key={option.code}
          type="button"
          onClick={() => setLocale(option.code)}
          className={cn(
            "rounded px-2 py-1 transition-colors",
            locale === option.code
              ? "bg-emerald-600 text-white"
              : "text-muted-foreground hover:text-foreground"
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
};

export default LocaleSwitcher;
