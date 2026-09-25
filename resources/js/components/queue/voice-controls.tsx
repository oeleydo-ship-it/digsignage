import { Loader2, Volume2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { availableVoices, BrowserSpeechVoiceProvider } from '@/lib/queue-voice';
import type { QueueVoiceSettings } from '@/types';

/** The speech voices this browser offers, loaded once they are ready. */
export function useBrowserVoices(): SpeechSynthesisVoice[] {
    const [voices, setVoices] = useState<SpeechSynthesisVoice[]>([]);

    useEffect(() => {
        if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
            return;
        }

        let active = true;
        const load = () =>
            void availableVoices().then((list) => {
                if (active) {
                    setVoices(list);
                }
            });

        load();
        window.speechSynthesis.addEventListener('voiceschanged', load);

        return () => {
            active = false;
            window.speechSynthesis.removeEventListener('voiceschanged', load);
        };
    }, []);

    return voices;
}

/**
 * Voice picker limited to the chosen languages. The saved name is kept even
 * when this browser lacks it, since displays may have other voices.
 */
export function VoiceSelect({
    id,
    value,
    languages,
    onChange,
}: {
    id: string;
    value: string | null;
    languages: string[];
    onChange: (voice: string | null) => void;
}) {
    const voices = useBrowserVoices();
    const prefixes = languages.map((language) =>
        language.slice(0, 2).toLowerCase(),
    );
    const matching = voices.filter((voice) =>
        prefixes.some((prefix) => voice.lang.toLowerCase().startsWith(prefix)),
    );
    const missing =
        value !== null && !matching.some((voice) => voice.name === value);

    return (
        <select
            id={id}
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value || null)}
            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
        >
            <option value="">Automatic for each language</option>
            {missing && <option value={value}>{value}</option>}
            {matching.map((voice) => (
                <option key={`${voice.name}-${voice.lang}`} value={voice.name}>
                    {voice.name} ({voice.lang})
                </option>
            ))}
        </select>
    );
}

/** Speaks a sample call with the settings on screen, before saving. */
export function PreviewAnnouncementButton({
    settings,
}: {
    settings: QueueVoiceSettings;
}) {
    const provider = useRef<BrowserSpeechVoiceProvider | null>(null);
    const [playing, setPlaying] = useState(false);
    const supported =
        typeof window !== 'undefined' && 'speechSynthesis' in window;

    useEffect(() => () => provider.current?.cancel(), []);

    return (
        <Button
            type="button"
            variant="outline"
            data-test="preview-queue-voice"
            disabled={!supported}
            title={
                supported
                    ? undefined
                    : 'This browser cannot speak announcements.'
            }
            onClick={() => {
                provider.current ??= new BrowserSpeechVoiceProvider();

                if (playing) {
                    provider.current.cancel();
                    setPlaying(false);

                    return;
                }

                setPlaying(true);
                void provider.current
                    .announce({
                        ticketNumber: 'A102',
                        counterName: 'Counter 3',
                        settings: { ...settings, repeat_count: 1 },
                    })
                    .finally(() => setPlaying(false));
            }}
        >
            {playing ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <Volume2 className="size-4" />
            )}
            {playing ? 'Stop' : 'Play sample'}
        </Button>
    );
}
