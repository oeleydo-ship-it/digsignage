const { app, BrowserWindow, ipcMain, globalShortcut } = require('electron');
const fs = require('fs');
const path = require('path');

const PLAYER_VERSION = '1.0.0';
const DEFAULT_SERVER = 'http://127.0.0.1:8000';
const APP_USER_MODEL_ID = 'com.digsignage.windows-player';

function appIconPath() {
    const ico = path.join(__dirname, '..', 'build', 'icon.ico');
    const png = path.join(__dirname, '..', 'build', 'icon.png');

    if (fs.existsSync(ico)) {
        return ico;
    }

    if (fs.existsSync(png)) {
        return png;
    }

    return undefined;
}

function windowOptions(overrides) {
    return {
        icon: appIconPath(),
        autoHideMenuBar: true,
        webPreferences: {
            preload: preloadPath(),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
        },
        ...overrides,
    };
}

function configPath() {
    return path.join(app.getPath('userData'), 'config.json');
}

function readConfig() {
    try {
        return JSON.parse(fs.readFileSync(configPath(), 'utf8'));
    } catch {
        return {};
    }
}

function writeConfig(config) {
    fs.mkdirSync(path.dirname(configPath()), { recursive: true });
    fs.writeFileSync(configPath(), JSON.stringify(config, null, 2));
}

function parseServerArg() {
    const flag = process.argv.find((arg) => arg.startsWith('--server='));

    if (flag) {
        return flag.slice('--server='.length).trim();
    }

    return process.env.DIGSIGNAGE_SERVER || '';
}

function normalizeServer(url) {
    return String(url || '')
        .trim()
        .replace(/\/+$/, '');
}

function playerUrl(serverUrl) {
    return `${normalizeServer(serverUrl)}/player`;
}

function preloadPath() {
    return path.join(__dirname, 'preload.cjs');
}

function createPlayerWindow(serverUrl) {
    const win = new BrowserWindow(windowOptions({
        width: 1280,
        height: 720,
        fullscreen: true,
        backgroundColor: '#000000',
        title: 'DigSignage Player',
    }));

    const lockZoom = () => {
        win.webContents.setZoomFactor(1);
        void win.webContents.setVisualZoomLevelLimits(1, 1);
    };

    lockZoom();
    win.webContents.on('did-finish-load', () => {
        lockZoom();
        void win.webContents.insertCSS(
            'html,body,#app{height:100%;height:100dvh;width:100%;max-width:100%;margin:0;padding:0;overflow:hidden;background:#000}',
        );
    });
    win.once('ready-to-show', () => {
        if (!win.isFullScreen()) {
            win.setFullScreen(true);
        }
    });

    win.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL, isMainFrame) => {
        if (!isMainFrame || errorCode === -3) {
            return;
        }

        win.loadFile(path.join(__dirname, 'offline.html'), {
            query: { server: `${serverUrl} (${errorDescription})` },
        });
    });

    void win.loadURL(playerUrl(serverUrl));

    return win;
}

function createSetupWindow(initialUrl) {
    const win = new BrowserWindow(windowOptions({
        width: 560,
        height: 520,
        resizable: false,
        backgroundColor: '#0b0b0f',
        title: 'DigSignage Player',
    }));

    const file = path.join(__dirname, 'setup.html');
    const query = initialUrl ? { server: initialUrl } : {};
    win.loadFile(file, { query });

    return win;
}

app.setName('DigSignage Player');

if (process.platform === 'win32') {
    app.setAppUserModelId(APP_USER_MODEL_ID);
}

app.whenReady().then(() => {
    ipcMain.handle('player:info', () => ({
        platform: 'windows',
        version: PLAYER_VERSION,
        serverUrl: readConfig().serverUrl || DEFAULT_SERVER,
    }));

    ipcMain.handle('player:start', (event, serverUrl) => {
        const server = normalizeServer(serverUrl) || DEFAULT_SERVER;
        writeConfig({ ...readConfig(), serverUrl: server });
        createPlayerWindow(server);
        const setup = BrowserWindow.fromWebContents(event.sender);
        setup?.close();

        return { ok: true, serverUrl: server };
    });

    ipcMain.handle('player:screenshot', async (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);

        if (!win) {
            return null;
        }

        const image = await win.capturePage();

        return image.toDataURL();
    });

    ipcMain.handle('player:restart', () => {
        app.relaunch();
        app.exit(0);
    });

    const fromArg = normalizeServer(parseServerArg());
    const stored = normalizeServer(readConfig().serverUrl || '');
    const server = fromArg || stored;

    if (fromArg) {
        writeConfig({ ...readConfig(), serverUrl: fromArg });
        createPlayerWindow(fromArg);
    } else if (server) {
        createPlayerWindow(server);
    } else {
        createSetupWindow(DEFAULT_SERVER);
    }

    globalShortcut.register('Control+Q', () => app.quit());
    globalShortcut.register('F11', () => {
        const focused = BrowserWindow.getFocusedWindow();

        if (focused) {
            focused.setFullScreen(!focused.isFullScreen());
        }
    });
});

app.on('will-quit', () => {
    globalShortcut.unregisterAll();
});

app.on('window-all-closed', () => {
    app.quit();
});
