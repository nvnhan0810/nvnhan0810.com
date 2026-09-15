const THEME_STORAGE_KEY = 'color-theme';

export type ColorTheme = 'light' | 'dark';

export function readStoredTheme(): ColorTheme | null {
    const raw = localStorage.getItem(THEME_STORAGE_KEY);
    if (raw === 'light' || raw === 'dark') {
        return raw;
    }
    return null;
}

export function applyTheme(theme: ColorTheme): void {
    const root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    localStorage.setItem(THEME_STORAGE_KEY, theme);
}

export function toggleTheme(): ColorTheme {
    const root = document.documentElement;
    const next: ColorTheme = root.classList.contains('dark') ? 'light' : 'dark';
    applyTheme(next);
    return next;
}
