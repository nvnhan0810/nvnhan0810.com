import { createContext, useContext, useEffect, useState } from "react"

type Theme = "dark" | "light" | "system"

type ThemeProviderProps = {
  children: React.ReactNode
  defaultTheme?: Theme
  storageKey?: string
}

type ThemeProviderState = {
  theme: Theme
  setTheme: (theme: Theme) => void
}

const initialState: ThemeProviderState = {
  theme: "system",
  setTheme: () => null,
}

const ThemeProviderContext = createContext<ThemeProviderState>(initialState)

const THEME_COLOR_LIGHT = "#ffffff"
const THEME_COLOR_DARK = "#171a20"

const resolveTheme = (theme: Theme): "dark" | "light" => {
  if (theme === "system") {
    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light"
  }
  return theme
}

const applyDomTheme = (resolved: "dark" | "light"): void => {
  const root = window.document.documentElement
  root.classList.remove("light", "dark")
  root.classList.add(resolved)

  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) {
    meta.setAttribute(
      "content",
      resolved === "dark" ? THEME_COLOR_DARK : THEME_COLOR_LIGHT,
    )
  }
}

export function ThemeProvider({
  children,
  defaultTheme = "system",
  storageKey = "vite-ui-theme",
  ...props
}: ThemeProviderProps) {
  const [theme, setTheme] = useState<Theme>(() => {
    if (typeof window === "undefined") {
      return defaultTheme
    }
    return (localStorage.getItem(storageKey) as Theme) || defaultTheme
  })

  useEffect(() => {
    applyDomTheme(resolveTheme(theme))

    if (theme !== "system") {
      return
    }

    const media = window.matchMedia("(prefers-color-scheme: dark)")
    const onChange = (): void => {
      applyDomTheme(resolveTheme("system"))
    }
    media.addEventListener("change", onChange)
    return () => media.removeEventListener("change", onChange)
  }, [theme])

  const value = {
    theme,
    setTheme: (next: Theme) => {
      if (typeof window !== "undefined") {
        localStorage.setItem(storageKey, next)
      }
      setTheme(next)
    },
  }

  return (
    <ThemeProviderContext.Provider {...props} value={value}>
      {children}
    </ThemeProviderContext.Provider>
  )
}

export const useTheme = () => {
  const context = useContext(ThemeProviderContext)

  if (context === undefined)
    throw new Error("useTheme must be used within a ThemeProvider")

  return context
}
