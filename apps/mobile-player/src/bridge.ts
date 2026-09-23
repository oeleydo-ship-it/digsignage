import { PLAYER_PLATFORM, PLAYER_VERSION } from './config';

/**
 * Exposes `window.digsignagePlayer` to the web player before it boots, matching
 * the Windows (Electron) shell contract in resources/js/types/global.d.ts.
 * Calls are relayed to native over postMessage and resolved by id.
 */
export const bridgeScript = `
(function () {
    if (window.digsignagePlayer) { return; }
    var pending = {};
    var seq = 0;

    function call(type) {
        return new Promise(function (resolve, reject) {
            var id = ++seq;
            pending[id] = { resolve: resolve, reject: reject };
            window.ReactNativeWebView.postMessage(JSON.stringify({ id: id, type: type }));
            setTimeout(function () {
                if (pending[id]) { delete pending[id]; resolve(null); }
            }, 20000);
        });
    }

    window.__digsignageResolve = function (id, ok, value) {
        var entry = pending[id];
        if (!entry) { return; }
        delete pending[id];
        if (ok) { entry.resolve(value); } else { entry.reject(new Error(String(value))); }
    };

    window.digsignagePlayer = {
        platform: ${JSON.stringify(PLAYER_PLATFORM)},
        version: ${JSON.stringify(PLAYER_VERSION)},
        captureScreenshot: function () { return call('screenshot'); },
        restart: function () { return call('restart'); },
        info: function () { return call('info'); }
    };
})();
true;
`;

export type BridgeMessage = {
    id: number;
    type: 'screenshot' | 'restart' | 'info';
};

export function resolveScript(id: number, ok: boolean, value: unknown): string {
    return `window.__digsignageResolve && window.__digsignageResolve(${id}, ${ok}, ${JSON.stringify(value ?? null)}); true;`;
}

/** Forces the web player to re-run its sync/reconnect effects after resume. */
export const resyncScript = `
window.dispatchEvent(new Event('offline'));
setTimeout(function () { window.dispatchEvent(new Event('online')); }, 50);
true;
`;

/** Removes the pairing so the web player shows a fresh pairing code. */
export const unpairScript = `
try {
    localStorage.removeItem('digsignage.device_token');
    localStorage.removeItem('digsignage.device_uuid');
} catch (e) {}
window.location.reload();
true;
`;
