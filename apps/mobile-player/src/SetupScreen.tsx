import { StatusBar } from 'expo-status-bar';
import { useState } from 'react';
import {
    ActivityIndicator,
    Image,
    KeyboardAvoidingView,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    View,
} from 'react-native';
import { isValidServer, normalizeServer, PLAYER_PLATFORM, PLAYER_VERSION, playerUrl } from './config';

type Props = {
    initialServer: string;
    /** True when opened from a running player (shows back / unpair actions). */
    fromPlayer: boolean;
    onConnect: (serverUrl: string) => void;
    onBack: () => void;
    onUnpair: () => void;
};

async function reachable(url: string): Promise<boolean> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 8000);

    try {
        const response = await fetch(playerUrl(url), { signal: controller.signal });

        return response.status < 500;
    } catch {
        return false;
    } finally {
        clearTimeout(timer);
    }
}

export default function SetupScreen({ initialServer, fromPlayer, onConnect, onBack, onUnpair }: Props) {
    const [server, setServer] = useState(initialServer);
    const [error, setError] = useState('');
    const [checking, setChecking] = useState(false);

    const connect = async () => {
        setError('');
        const url = normalizeServer(server);

        if (!isValidServer(url)) {
            setError('Use a full http:// or https:// server URL.');
            return;
        }

        setChecking(true);
        const ok = await reachable(url);
        setChecking(false);

        if (!ok) {
            setError(`Could not reach ${playerUrl(url)}. Check the URL and network.`);
            return;
        }

        onConnect(url);
    };

    return (
        <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <StatusBar style="light" />
            <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
                <View style={styles.card}>
                    <Image source={require('../assets/icon.png')} style={styles.logo} />
                    <Text style={styles.kicker}>{PLAYER_PLATFORM === 'ios' ? 'iOS' : 'Android'} player</Text>
                    <Text style={styles.title}>{fromPlayer ? 'Player settings' : 'Connect this display'}</Text>
                    <Text style={styles.help}>
                        Enter your DigSignage server URL. The player will show a pairing code — enter it under
                        Screens in the dashboard.
                    </Text>

                    <TextInput
                        style={styles.input}
                        value={server}
                        onChangeText={setServer}
                        placeholder="https://signage.example.com"
                        placeholderTextColor="#52525b"
                        autoCapitalize="none"
                        autoCorrect={false}
                        keyboardType="url"
                        returnKeyType="go"
                        onSubmitEditing={connect}
                    />

                    <Pressable style={styles.button} onPress={connect} disabled={checking}>
                        {checking ? (
                            <ActivityIndicator color="#18181b" />
                        ) : (
                            <Text style={styles.buttonText}>{fromPlayer ? 'Save and reconnect' : 'Open player'}</Text>
                        )}
                    </Pressable>

                    <Text style={styles.error}>{error}</Text>

                    {fromPlayer && (
                        <View style={styles.actions}>
                            <Pressable style={styles.secondary} onPress={onBack}>
                                <Text style={styles.secondaryText}>Back to player</Text>
                            </Pressable>
                            <Pressable style={styles.secondary} onPress={onUnpair}>
                                <Text style={[styles.secondaryText, styles.danger]}>Unpair display</Text>
                            </Pressable>
                        </View>
                    )}

                    <Text style={styles.version}>Version {PLAYER_VERSION}</Text>
                    {!fromPlayer && (
                        <Text style={styles.version}>Tip: tap the top-left corner 5 times to reopen settings.</Text>
                    )}
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    root: { flex: 1, backgroundColor: '#0b0b0f' },
    scroll: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
    card: { width: '100%', maxWidth: 440, alignItems: 'center' },
    logo: { width: 72, height: 72, borderRadius: 18, marginBottom: 12 },
    kicker: { color: '#a1a1aa', fontSize: 12, letterSpacing: 3.6, textTransform: 'uppercase' },
    title: { color: '#f4f4f5', fontSize: 28, fontWeight: '600', marginTop: 12, marginBottom: 8, textAlign: 'center' },
    help: { color: '#a1a1aa', fontSize: 14, lineHeight: 21, textAlign: 'center' },
    input: {
        alignSelf: 'stretch',
        marginTop: 20,
        marginBottom: 16,
        paddingHorizontal: 14,
        paddingVertical: 12,
        borderWidth: 1,
        borderColor: '#3f3f46',
        borderRadius: 8,
        backgroundColor: '#18181b',
        color: '#f4f4f5',
        fontSize: 16,
    },
    button: {
        alignSelf: 'stretch',
        alignItems: 'center',
        backgroundColor: '#f4f4f5',
        borderRadius: 8,
        paddingVertical: 14,
    },
    buttonText: { color: '#18181b', fontWeight: '600', fontSize: 16 },
    error: { minHeight: 20, color: '#fca5a5', fontSize: 13, marginTop: 10, textAlign: 'center' },
    actions: { alignSelf: 'stretch', gap: 10, marginTop: 8 },
    secondary: { alignItems: 'center', borderWidth: 1, borderColor: '#3f3f46', borderRadius: 8, paddingVertical: 12 },
    secondaryText: { color: '#f4f4f5', fontSize: 15, fontWeight: '500' },
    danger: { color: '#fca5a5' },
    version: { color: '#52525b', fontSize: 12, marginTop: 16, textAlign: 'center' },
});
