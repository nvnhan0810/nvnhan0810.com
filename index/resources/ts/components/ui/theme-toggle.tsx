import { Button } from "@/ts/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/ts/components/ui/tooltip";
import { useTheme } from "@/ts/providers/theme-provider";
import { Moon, Sun } from "lucide-react";
import { useEffect, useState } from "react";

type Props = {
  className?: string;
};

/**
 * Toggles between soft-gray light and dark. Ignores "system" for a clear 1-tap control.
 */
export const ThemeToggle = ({ className }: Props): React.ReactElement => {
  const { theme, setTheme } = useTheme();
  const [isDark, setIsDark] = useState(theme === "dark");

  useEffect(() => {
    if (theme === "dark") {
      setIsDark(true);
      return;
    }
    if (theme === "light") {
      setIsDark(false);
      return;
    }
    setIsDark(document.documentElement.classList.contains("dark"));
  }, [theme]);

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className={className ?? "cursor-pointer"}
          aria-label={isDark ? "Chuyển light theme" : "Chuyển dark theme"}
          onClick={() => setTheme(isDark ? "light" : "dark")}
        >
          {isDark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
        </Button>
      </TooltipTrigger>
      <TooltipContent side="bottom">
        {isDark ? "Light (xám dịu)" : "Dark"}
      </TooltipContent>
    </Tooltip>
  );
};

export default ThemeToggle;
