import type { PlayerItem, PlayerManifest } from '@/lib/player-runtime';
import type { PlayerQueueUpdate } from '@/lib/player-echo';
import type { QueueVoiceLanguage, QueueVoiceSettings, WidgetPayload } from '@/types';

export type QueueVoiceRequest = {
    ticketNumber: string;
    counterName: string;
    settings: QueueVoiceSettings;
    soundOnly?: boolean;
};

export interface QueueVoiceProvider {
    announce(request: QueueVoiceRequest): Promise<void>;
    cancel(): void;
}

type PendingAnnouncement = {
    key: string;
    request: QueueVoiceRequest;
};

export class QueueAnnouncementQueue {
    private readonly items: PendingAnnouncement[] = [];
    private readonly keys = new Set<string>();
    private readonly recentKeys = new Set<string>();
    private draining: Promise<void> | null = null;
    private generation = 0;
    private delayTimer: ReturnType<typeof setTimeout> | null = null;
    private finishDelay: (() => void) | null = null;

    public constructor(
        private readonly provider: QueueVoiceProvider,
        private readonly maximumPending = 50,
    ) {}

    public enqueue(key: string, request: QueueVoiceRequest): boolean {
        if (key === '' || this.keys.has(key) || this.recentKeys.has(key) || this.keys.size >= this.maximumPending) {
            return false;
        }

        this.keys.add(key);
        this.items.push({ key, request });
        this.start();

        return true;
    }

    public clear(): void {
        this.generation += 1;
        this.items.length = 0;
        this.keys.clear();
        this.recentKeys.clear();
        this.provider.cancel();

        if (this.delayTimer !== null) {
            clearTimeout(this.delayTimer);
            this.delayTimer = null;
        }

        this.finishDelay?.();
        this.finishDelay = null;
    }

    public get size(): number {
        return this.keys.size;
    }

    private start(): void {
        if (this.draining !== null) return;

        this.draining = this.drain().finally(() => {
            this.draining = null;
            if (this.items.length > 0) this.start();
        });
    }

    private async drain(): Promise<void> {
        const generation = this.generation;

        while (this.items.length > 0 && generation === this.generation) {
            const item = this.items.shift();
            if (!item) return;

            try {
                await this.provider.announce(item.request);
            } catch {
                // A provider failure must not block later ticket calls.
            } finally {
                this.keys.delete(item.key);
                this.recentKeys.add(item.key);
                if (this.recentKeys.size > 100) {
                    this.recentKeys.delete(this.recentKeys.values().next().value ?? '');
                }
            }

            if (this.items.length > 0 && generation === this.generation) {
                await this.pause(item.request.settings.announcement_delay_seconds * 1000);
            }
        }
    }

    private pause(milliseconds: number): Promise<void> {
        if (milliseconds <= 0) return Promise.resolve();

        return new Promise((resolve) => {
            this.finishDelay = resolve;
            this.delayTimer = setTimeout(() => {
                this.delayTimer = null;
                this.finishDelay = null;
                resolve();
            }, milliseconds);
        });
    }
}

const digitWords: Record<QueueVoiceLanguage, string[]> = {
    'en-US': ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
    'ar-AE': ['صفر', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'],
    'fil-PH': ['sero', 'isa', 'dalawa', 'tatlo', 'apat', 'lima', 'anim', 'pito', 'walo', 'siyam'],
    'hi-IN': ['शून्य', 'एक', 'दो', 'तीन', 'चार', 'पाँच', 'छह', 'सात', 'आठ', 'नौ'],
};

function spokenValue(value: string, language: QueueVoiceLanguage): string {
    return Array.from(value.trim().toUpperCase())
        .filter((character) => /[A-Z0-9]/.test(character))
        .map((character) => /\d/.test(character) ? digitWords[language][Number(character)] : character)
        .join(' ');
}

export function announcementText(ticket: string, counter: string, language: QueueVoiceLanguage): string {
    const number = spokenValue(ticket, language);
    const destination = counter
        .replace(/^counter\s*/i, '')
        .trim()
        .replace(/\d/g, (digit) => digitWords[language][Number(digit)]);

    switch (language) {
        case 'ar-AE': return `التذكرة ${number}، يرجى التوجه إلى الشباك ${destination}.`;
        case 'fil-PH': return `Ticket ${number}, pumunta po sa Counter ${destination}.`;
        case 'hi-IN': return `टिकट ${number}, कृपया काउंटर ${destination} पर जाएँ।`;
        default: return `Ticket ${number}, please proceed to Counter ${destination}.`;
    }
}

function matchesFilter(settings: Record<string, unknown>, key: string, actual: number | null): boolean {
    const selected = Number(settings[key] ?? 0);
    return selected === 0 || (actual !== null && selected === actual);
}

function widgetWantsCallCue(widget: WidgetPayload | null | undefined, update: PlayerQueueUpdate, cue: 'sound' | 'voice'): boolean {
    if (!widget?.key.startsWith('queue_') || widget.settings[cue] !== true) return false;
    const settings = widget.settings as Record<string, unknown>;
    return matchesFilter(settings, 'service_id', update.service_id)
        && matchesFilter(settings, 'location_id', update.location_id)
        && matchesFilter(settings, 'counter_id', update.counter_id);
}

function itemWantsCallCue(item: PlayerItem, update: PlayerQueueUpdate, cue: 'sound' | 'voice'): boolean {
    if (widgetWantsCallCue(item.widget, update, cue)) return true;
    const elements = (item.document as { elements?: Array<{ widget?: WidgetPayload }> } | null)?.elements ?? [];
    return elements.some((element) => widgetWantsCallCue(element.widget, update, cue));
}

function manifestWantsCallCue(manifest: PlayerManifest, update: PlayerQueueUpdate, cue: 'sound' | 'voice'): boolean {
    const playlists = [manifest.playback.playlist, ...manifest.playback.zones.map((zone) => zone.playlist)];
    return playlists.some((playlist) => playlist?.items.some((item) => itemWantsCallCue(item, update, cue)) === true);
}

export function manifestWantsQueueSound(manifest: PlayerManifest, update: PlayerQueueUpdate): boolean {
    return manifestWantsCallCue(manifest, update, 'sound');
}

export function manifestWantsQueueVoice(manifest: PlayerManifest, update: PlayerQueueUpdate): boolean {
    return manifestWantsCallCue(manifest, update, 'voice');
}

export function shouldAnnounceQueueCall(
    update: PlayerQueueUpdate,
    now = Date.now(),
): update is PlayerQueueUpdate & { ticket_number: string; counter_name: string; called_at: string } {
    if (update.status !== 'called' && update.status !== 'serving') return false;
    if (!update.ticket_number || !update.counter_name || !update.called_at) return false;

    const age = now - Date.parse(update.called_at);
    return Number.isFinite(age) && age >= -5_000 && age < 30_000;
}

export class BrowserSpeechVoiceProvider implements QueueVoiceProvider {
    private cancellationVersion = 0;
    private readonly pendingSpeech = new Set<() => void>();

    async announce({ ticketNumber, counterName, settings, soundOnly = false }: QueueVoiceRequest): Promise<void> {
        const cancellationVersion = this.cancellationVersion;

        if (settings.chime) {
            try {
                await Promise.race([
                    this.chime(settings.volume),
                    new Promise<void>((resolve) => window.setTimeout(resolve, 250)),
                ]);
            } catch {
                // Browser autoplay policy may block Web Audio; speech can continue.
            }
        }
        if (soundOnly || !('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) return;

        const voices = window.speechSynthesis.getVoices();

        for (let repeat = 0; repeat < settings.repeat_count; repeat += 1) {
            for (const language of settings.languages) {
                if (cancellationVersion !== this.cancellationVersion) return;

                const utterance = new SpeechSynthesisUtterance(announcementText(ticketNumber, counterName, language));
                utterance.lang = language;
                utterance.rate = settings.speed;
                utterance.volume = settings.volume;
                utterance.voice = voices.find((voice) => voice.name === settings.voice)
                    ?? voices.find((voice) => voice.lang.toLowerCase().startsWith(language.slice(0, 2).toLowerCase()))
                    ?? null;
                await this.speak(utterance);
            }
        }
    }

    cancel(): void {
        this.cancellationVersion += 1;
        window.speechSynthesis?.cancel();
        for (const finish of this.pendingSpeech) finish();
        this.pendingSpeech.clear();
    }

    private speak(utterance: SpeechSynthesisUtterance): Promise<void> {
        return new Promise((resolve) => {
            let completed = false;
            let watchdog: number | null = null;
            const finish = () => {
                if (completed) return;
                completed = true;
                if (watchdog !== null) window.clearTimeout(watchdog);
                this.pendingSpeech.delete(finish);
                resolve();
            };

            watchdog = window.setTimeout(finish, 60_000);
            this.pendingSpeech.add(finish);
            utterance.onend = finish;
            utterance.onerror = finish;
            window.speechSynthesis.speak(utterance);
        });
    }

    private async chime(volume: number): Promise<void> {
        const AudioContextClass = window.AudioContext;
        if (!AudioContextClass) return;
        const context = new AudioContextClass();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.frequency.value = 880;
        gain.gain.value = Math.min(0.18, volume * 0.18);
        oscillator.connect(gain); gain.connect(context.destination);
        oscillator.start(); oscillator.stop(context.currentTime + 0.16);
        await new Promise<void>((resolve) => { oscillator.onended = () => { void context.close(); resolve(); }; });
    }
}
