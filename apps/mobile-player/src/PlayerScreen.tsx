import { useKeepAwake } from 'expo-keep-awake';
import { NavigationBar } from 'expo-navigation-bar';
import * as ScreenOrientation from 'expo-screen-orientation';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    AppState,
    Pressable,
    StyleSheet,
    Text,
    useWindowDimensions,
    View,
} from 'react-native';
import { captureRef } from 'react-native-view-shot';
import { WebView, type WebViewMessageEvent } from 'react-native-webview';
import {
    bridgeScript,
    type BridgeMessage,
    resolveScript,
    resyncScript,
    unpairScript,
} from './bridge';
import { PLAYER_PLATFORM, PLAYER_VERSION, playerUrl } from './config';

const RETRY_SECONDS = 15;
const RESYNC_AFTER_BACKGROUND_MS = 30_000;
const SETTINGS_TAPS = 5;
const SETTINGS_TAP_WINDOW_MS = 3000;

type Props = {
    serverUrl: string;
    unpairRequest: number;
    onOpenSettings: () => void;
};

export default function PlayerScreen({ serverUrl, unpairRequest, onOpenSettings }: Props) {
    useKeepAwake();

    const { width, height } = useWindowDimensions();
    const webRef = useRef<WebView>(null);
    const shotRef = useRef<View>(null);
    const [reloadKey, setReloadKey] = useState(0);
    const [failure, setFailure] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [countdown, setCountdown] = useState(RETRY_SECONDS);
    const taps = useRef<number[]>([]);
    const backgroundedAt = useRef<number | null>(null);

    const restart = useCallback(() => {
        setFailure(null);
        setLoading(true);
        setReloadKey((key) => key + 1);
    }, []);

    // Signage content decides its own orientation; let the device rotate freely.
    useEffect(() => {
        void ScreenOrientation.unlockAsync();
    }, []);

    useEffect(() => {
        if (unpairRequest > 0) {
            webRef.current?.injectJavaScript(unpairScript);
        }
    }, [unpairRequest]);

    // Resync as soon as the app returns from the background.
    useEffect(() => {
        const sub = AppState.addEventListener('change', (state) => {
            if (state === 'active') {
                const away = backgroundedAt.current ? Date.now() - backgroundedAt.current : 0;
                backgroundedAt.current = null;

                if (failure) {
                    restart();
                } else if (away > RESYNC_AFTER_BACKGROUND_MS) {
                    webRef.current?.injectJavaScript(resyncScript);
                }
            } else if (backgroundedAt.current === null) {
                backgroundedAt.current = Date.now();
            }
        });

        return () => sub.remove();
    }, [failure, restart]);

    // Auto-retry while the server is unreachable.
    useEffect(() => {
        if (!failure) {
            return;
        }

        setCountdown(RETRY_SECONDS);
        const tick = setInterval(() => setCountdown((value) => Math.max(0, value - 1)), 1000);
        const retry = setTimeout(restart, RETRY_SECONDS * 1000);

        return () => {
            clearInterval(tick);
            clearTimeout(retry);
        };
    }, [failure, restart]);

    const reply = (id: number, ok: boolean, value: unknown) => {
        webRef.current?.injectJavaScript(resolveScript(id, ok, value));
    };

    const onMessage = async (event: WebViewMessageEvent) => {
        let message: BridgeMessage;

        try {
            message = JSON.parse(event.nativeEvent.data) as BridgeMessage;
        } catch {
            return;
        }

        switch (message.type) {
            case 'info':
                reply(message.id, true, {
                    platform: PLAYER_PLATFORM,
                    version: PLAYER_VERSION,
                    serverUrl,
                    width: Math.round(width),
                    height: Math.round(height),
                });
                break;
            case 'restart':
                reply(message.id, true, null);
                setTimeout(restart, 250);
                break;
            case 'screenshot':
                try {
                    const scale = Math.min(1, 1280 / Math.max(width, height));
                    const image = await captureRef(shotRef, {
                        format: 'jpg',
                        quality: 0.7,
                        result: 'data-uri',
                        width: Math.round(width * scale),
                        height: Math.round(height * scale),
                    });
                    reply(message.id, true, image);
                } catch (error) {
                    reply(message.id, false, error instanceof Error ? error.message : 'Screenshot failed');
                }
                break;
        }
    };

    // Hidden gesture: tap the top-left corner 5 times quickly to open settings.
    const onCornerTap = () => {
        const now = Date.now();
        taps.current = [...taps.current.filter((t) => now - t < SETTINGS_TAP_WINDOW_MS), now];

        if (taps.current.length >= SETTINGS_TAPS) {
            taps.current = [];
            onOpenSettings();
        }
    };

    return (
        <View style={styles.root}>
            <StatusBar hidden />
            <NavigationBar hidden />

            <View ref={shotRef} collapsable={false} style={styles.fill}>
                <WebView
                    key={reloadKey}
                    ref={webRef}
                    source={{ uri: playerUrl(serverUrl) }}
                    style={styles.web}
                    containerStyle={styles.web}
                    injectedJavaScriptBeforeContentLoaded={bridgeScript}
                    onMessage={onMessage}
                    originWhitelist={['*']}
                    javaScriptEnabled
                    domStorageEnabled
                    cacheEnabled
                    allowsInlineMediaPlayback
                    mediaPlaybackRequiresUserAction={false}
                    allowsFullscreenVideo
                    allowsBackForwardNavigationGestures={false}
                    bounces={false}
                    overScrollMode="never"
                    scrollEnabled={false}
                    setBuiltInZoomControls={false}
                    textZoom={100}
                    mixedContentMode="always"
                    androidLayerType="hardware"
                    contentInsetAdjustmentBehavior="never"
                    automaticallyAdjustContentInsets={false}
                    applicationNameForUserAgent={`DigSignagePlayer/${PLAYER_PLATFORM}-${PLAYER_VERSION}`}
                    onLoadEnd={() => setLoading(false)}
                    onError={({ nativeEvent }) => setFailure(nativeEvent.description || 'Network error')}
                    onHttpError={({ nativeEvent }) => {
                        if (nativeEvent.statusCode >= 500 && nativeEvent.url.includes('/player')) {
                            setFailure(`Server error ${nativeEvent.statusCode}`);
                        }
                    }}
                    onContentProcessDidTerminate={restart}
                    onRenderProcessGone={restart}
                />
            </View>

            {loading && !failure && (
                <View style={styles.overlay} pointerEvents="none">
                    <ActivityIndicator color="#2EC4B6" size="large" />
                </View>
            )}

            {failure && (
                <View style={styles.overlay}>
                    <Text style={styles.title}>Cannot reach the server</Text>
                    <Text style={styles.body}>{playerUrl(serverUrl)}</Text>
                    <Text style={styles.body}>{failure}</Text>
                    <Text style={styles.hint}>Retrying in {countdown}s…</Text>
                    <View style={styles.row}>
                        <Pressable style={styles.button} onPress={restart}>
                            <Text style={styles.buttonText}>Retry now</Text>
                        </Pressable>
                        <Pressable style={[styles.button, styles.secondary]} onPress={onOpenSettings}>
                            <Text style={[styles.buttonText, styles.secondaryText]}>Settings</Text>
                        </Pressable>
                    </View>
                </View>
            )}

            <Pressable style={styles.corner} onPress={onCornerTap} accessibilityLabel="Player settings" />
        </View>
    );
}

const styles = StyleSheet.create({
    root: { flex: 1, backgroundColor: '#000' },
    fill: { flex: 1, backgroundColor: '#000' },
    web: { flex: 1, backgroundColor: '#000' },
    overlay: {
        ...StyleSheet.absoluteFill,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#000',
        padding: 24,
    },
    title: { color: '#fff', fontSize: 26, fontWeight: '600', marginBottom: 12, textAlign: 'center' },
    body: { color: '#a1a1aa', fontSize: 15, marginBottom: 6, textAlign: 'center' },
    hint: { color: '#71717a', fontSize: 14, marginTop: 12 },
    row: { flexDirection: 'row', gap: 12, marginTop: 24 },
    button: { backgroundColor: '#f4f4f5', borderRadius: 8, paddingHorizontal: 20, paddingVertical: 12 },
    buttonText: { color: '#18181b', fontWeight: '600', fontSize: 16 },
    secondary: { backgroundColor: 'transparent', borderWidth: 1, borderColor: '#3f3f46' },
    secondaryText: { color: '#f4f4f5' },
    corner: { position: 'absolute', top: 0, left: 0, width: 72, height: 72 },
});
