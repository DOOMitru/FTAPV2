import type Alpine from 'alpinejs';

declare global {
    interface Window {
        Alpine: typeof Alpine;
        toggleTheme: () => void;
        // Present only once Google's script has loaded, which is why every
        // caller checks before using it -- see recaptcha.ts.
        grecaptcha?: {
            ready: (callback: () => void) => void;
            execute: (siteKey: string, options: { action: string }) => Promise<string>;
        };
    }
}
