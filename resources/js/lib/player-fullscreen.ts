export function isBrowserFullscreen(): boolean {
    const doc = document as Document & {
        webkitFullscreenElement?: Element | null;
    };

    return Boolean(document.fullscreenElement || doc.webkitFullscreenElement);
}

export function isKioskWindow(): boolean {
    if (typeof window === 'undefined' || typeof screen === 'undefined') {
        return false;
    }

    return (
        Math.abs(window.outerHeight - screen.height) <= 16 &&
        Math.abs(window.outerWidth - screen.width) <= 16
    );
}

export async function enterPlayerFullscreen(): Promise<boolean> {
    const root = document.documentElement as HTMLElement & {
        webkitRequestFullscreen?: () => Promise<void> | void;
    };

    try {
        if (root.requestFullscreen) {
            await root.requestFullscreen({ navigationUI: 'hide' });
        } else if (root.webkitRequestFullscreen) {
            await root.webkitRequestFullscreen();
        } else {
            return isBrowserFullscreen() || isKioskWindow();
        }

        return true;
    } catch {
        return isBrowserFullscreen() || isKioskWindow();
    }
}
