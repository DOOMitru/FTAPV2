type Theme = 'dark' | 'light';

const STORAGE_KEY = 'theme';

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function storedTheme(): Theme | null {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        // Private browsing, or site data blocked. Fall back to the system.
        return null;
    }
}

export function currentTheme(): Theme {
    return storedTheme() ?? (systemPrefersDark() ? 'dark' : 'light');
}

export function applyTheme(theme: Theme): void {
    document.documentElement.setAttribute('data-theme', theme);

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Nothing to do — the attribute still applies for this page view.
    }
}

export function toggleTheme(): void {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

export function initTheme(): void {
    // [data-theme] is already correct from the pre-paint script; the toggle's
    // icon and label swap off that attribute via CSS, so there is nothing
    // here to sync — just wire up the click handler.
    document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
        el.addEventListener('click', toggleTheme);
    });
}
