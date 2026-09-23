import * as SystemUI from 'expo-system-ui';
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { DEFAULT_SERVER, loadServer, saveServer } from './src/config';
import PlayerScreen from './src/PlayerScreen';
import SetupScreen from './src/SetupScreen';

export default function App() {
    const [ready, setReady] = useState(false);
    const [server, setServer] = useState<string | null>(null);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [unpairRequest, setUnpairRequest] = useState(0);

    useEffect(() => {
        void SystemUI.setBackgroundColorAsync('#000000');
        void loadServer().then((stored) => {
            setServer(stored);
            setReady(true);
        });
    }, []);

    if (!ready) {
        return <View style={styles.root} />;
    }

    const connect = async (url: string) => {
        await saveServer(url);
        setServer(url);
        setSettingsOpen(false);
    };

    return (
        <View style={styles.root}>
            {server && (
                <PlayerScreen
                    key={server}
                    serverUrl={server}
                    unpairRequest={unpairRequest}
                    onOpenSettings={() => setSettingsOpen(true)}
                />
            )}

            {(!server || settingsOpen) && (
                <View style={StyleSheet.absoluteFill}>
                    <SetupScreen
                        initialServer={server ?? DEFAULT_SERVER}
                        fromPlayer={Boolean(server)}
                        onConnect={connect}
                        onBack={() => setSettingsOpen(false)}
                        onUnpair={() => {
                            setUnpairRequest((n) => n + 1);
                            setSettingsOpen(false);
                        }}
                    />
                </View>
            )}
        </View>
    );
}

const styles = StyleSheet.create({
    root: { flex: 1, backgroundColor: '#000' },
});
