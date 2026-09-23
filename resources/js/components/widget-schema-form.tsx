import ColorInput from '@/components/color-input';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    MAX_WIDGET_FONT_SIZE,
    MIN_WIDGET_FONT_SIZE,
    clampWidgetFontSize,
} from '@/lib/widget-style';
import type { WidgetDefinition } from '@/types';

function colorFallback(value: string): string {
    return /^#[0-9a-fA-F]{3,6}$/.test(value) ? value : '#000000';
}

export default function WidgetSchemaForm({
    widget,
    values,
    disabled,
    onChange,
}: {
    widget: WidgetDefinition;
    values: Record<string, string | number | boolean | null>;
    disabled?: boolean;
    onChange: (values: Record<string, string | number | boolean | null>) => void;
}) {
    const set = (name: string, value: string | number | boolean | null) =>
        onChange({ ...values, [name]: value });

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-xs">{widget.description}</p>
            {widget.schema.map((field) => (
                <div key={field.name} className="space-y-1">
                    <Label htmlFor={`widget-${field.name}`}>{field.label}</Label>
                    {field.type === 'select' ? (
                        <select
                            id={`widget-${field.name}`}
                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            disabled={disabled}
                            value={String(values[field.name] ?? field.default ?? '')}
                            onChange={(event) => set(field.name, event.target.value)}
                        >
                            {field.options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    ) : field.type === 'boolean' ? (
                        <Checkbox
                            id={`widget-${field.name}`}
                            disabled={disabled}
                            checked={Boolean(
                                values[field.name] ?? field.default ?? false,
                            )}
                            onCheckedChange={(checked) =>
                                set(field.name, checked === true)
                            }
                        />
                    ) : field.type === 'textarea' ? (
                        <textarea
                            id={`widget-${field.name}`}
                            className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                            disabled={disabled}
                            value={String(values[field.name] ?? field.default ?? '')}
                            onChange={(event) => set(field.name, event.target.value)}
                        />
                    ) : field.type === 'color' ? (
                        <ColorInput
                            id={`widget-${field.name}`}
                            disabled={disabled}
                            fallback={colorFallback(
                                String(field.default ?? '#ffffff'),
                            )}
                            value={String(
                                values[field.name] ?? field.default ?? '#ffffff',
                            )}
                            onValue={(color) => set(field.name, color)}
                        />
                    ) : field.name === 'fontSize' ? (
                        <div className="space-y-1">
                            <Input
                                id={`widget-${field.name}`}
                                type="number"
                                min={MIN_WIDGET_FONT_SIZE}
                                max={MAX_WIDGET_FONT_SIZE}
                                disabled={disabled}
                                value={clampWidgetFontSize(
                                    values[field.name] ?? field.default ?? 48,
                                    Number(field.default ?? 48),
                                )}
                                onChange={(event) =>
                                    set(
                                        field.name,
                                        clampWidgetFontSize(
                                            event.target.value,
                                            Number(field.default ?? 48),
                                        ),
                                    )
                                }
                            />
                            <Input
                                aria-label={`${field.label} slider`}
                                type="range"
                                min={MIN_WIDGET_FONT_SIZE}
                                max={MAX_WIDGET_FONT_SIZE}
                                disabled={disabled}
                                value={clampWidgetFontSize(
                                    values[field.name] ?? field.default ?? 48,
                                    Number(field.default ?? 48),
                                )}
                                onChange={(event) =>
                                    set(
                                        field.name,
                                        clampWidgetFontSize(
                                            event.target.value,
                                            Number(field.default ?? 48),
                                        ),
                                    )
                                }
                            />
                        </div>
                    ) : (
                        <Input
                            id={`widget-${field.name}`}
                            type={
                                field.type === 'number'
                                    ? 'number'
                                    : field.type === 'datetime'
                                      ? 'datetime-local'
                                      : 'text'
                            }
                            min={
                                field.name === 'background_opacity'
                                    ? 0
                                    : undefined
                            }
                            max={
                                field.name === 'background_opacity'
                                    ? 100
                                    : undefined
                            }
                            inputMode={
                                field.type === 'url' ? 'url' : undefined
                            }
                            autoComplete={
                                field.type === 'url' ? 'off' : undefined
                            }
                            maxLength={
                                field.type === 'url' ? 2048 : undefined
                            }
                            spellCheck={
                                field.type === 'url' ? false : undefined
                            }
                            title={
                                field.type === 'url'
                                    ? String(
                                          values[field.name] ??
                                              field.default ??
                                              '',
                                      )
                                    : undefined
                            }
                            disabled={disabled}
                            value={String(values[field.name] ?? field.default ?? '')}
                            onChange={(event) =>
                                set(
                                    field.name,
                                    field.type === 'number'
                                        ? Number(event.target.value)
                                        : event.target.value,
                                )
                            }
                        />
                    )}
                    {field.help ? (
                        <p className="text-muted-foreground text-xs">{field.help}</p>
                    ) : null}
                </div>
            ))}
        </div>
    );
}
