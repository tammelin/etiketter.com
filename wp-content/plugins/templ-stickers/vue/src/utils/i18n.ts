declare global {
    interface Window {
        templStickersI18n: Record<string, string>;
    }
}

const strings = window.templStickersI18n || {};

export function __(key: string): string {
    return strings[key] || key;
}
