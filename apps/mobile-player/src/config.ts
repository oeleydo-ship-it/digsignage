import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Application from 'expo-application';
import { Platform } from 'react-native';

export const PLAYER_VERSION = Application.nativeApplicationVersion ?? '1.0.0';
export const PLAYER_PLATFORM = Platform.OS === 'ios' ? 'ios' : 'android';
export const DEFAULT_SERVER = 'https://';

const SERVER_KEY = 'digsignage.server_url';

export function normalizeServer(url: string): string {
    return url.trim().replace(/\/+$/, '');
}

export function isValidServer(url: string): boolean {
    return /^https?:\/\/[^\s/]+/i.test(normalizeServer(url));
}

export function playerUrl(serverUrl: string): string {
    return `${normalizeServer(serverUrl)}/player`;
}

export async function loadServer(): Promise<string | null> {
    try {
        return await AsyncStorage.getItem(SERVER_KEY);
    } catch {
        return null;
    }
}

export async function saveServer(url: string): Promise<void> {
    await AsyncStorage.setItem(SERVER_KEY, normalizeServer(url));
}
