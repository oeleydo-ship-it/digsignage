import { useEffect, useRef, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

function toHexColor(value: string, fallback: string): string {
    const trimmed = value.trim();

    if (/^#[0-9a-fA-F]{6}$/.test(trimmed)) {
        return trimmed;
    }

    if (/^#[0-9a-fA-F]{3}$/.test(trimmed)) {
        const r = trimmed[1];
        const g = trimmed[2];
        const b = trimmed[3];

        return `#${r}${r}${g}${g}${b}${b}`;
    }

    return fallback;
}

export default function ColorInput({
    value,
    fallback = '#ffffff',
    onValue,
    ...rest
}: Omit<
    ComponentProps<typeof Input>,
    'type' | 'value' | 'onChange' | 'onInput'
> & {
    value: string;
    fallback?: string;
    onValue: (color: string) => void;
}) {
    const frame = useRef<number | null>(null);
    const pending = useRef<string | null>(null);
    const onValueRef = useRef(onValue);
    onValueRef.current = onValue;

    useEffect(
        () => () => {
            if (frame.current !== null) {
                window.cancelAnimationFrame(frame.current);
            }
        },
        [],
    );

    const emit = (next: string) => {
        pending.current = toHexColor(next, fallback);

        if (frame.current !== null) {
            return;
        }

        frame.current = window.requestAnimationFrame(() => {
            frame.current = null;
            const color = pending.current;

            if (color !== null) {
                onValueRef.current(color);
            }
        });
    };

    return (
        <Input
            {...rest}
            type="color"
            value={toHexColor(value, fallback)}
            onInput={(event) => emit(event.currentTarget.value)}
            onChange={(event) => emit(event.currentTarget.value)}
        />
    );
}
