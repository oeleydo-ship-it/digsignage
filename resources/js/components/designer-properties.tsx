import ColorInput from '@/components/color-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import WidgetSchemaForm from '@/components/widget-schema-form';
import { isWidgetType } from '@/lib/widget-keys';
import {
    MAX_WIDGET_FONT_SIZE,
    MIN_WIDGET_FONT_SIZE,
    clampWidgetFontSize,
} from '@/lib/widget-style';
import type { DesignDocumentElement, WidgetDefinition } from '@/types';

export default function DesignerProperties({
    element,
    disabled,
    onChange,
    widgets = [],
}: {
    element: DesignDocumentElement;
    disabled: boolean;
    onChange: (props: DesignDocumentElement['props']) => void;
    widgets?: WidgetDefinition[];
}) {
    const props = element.props;
    const widget =
        widgets.find((entry) => entry.key === element.type) ??
        (element.type === 'chart'
            ? widgets.find((entry) => entry.key === 'charts')
            : element.type === 'iframe'
              ? widgets.find((entry) => entry.key === 'web_page')
              : undefined);
    const set = (key: string, value: string | number) =>
        onChange({ ...props, [key]: value });
    const showAppearance = !isWidgetType(element.type);

    return (
        <fieldset
            disabled={disabled}
            className="space-y-3 rounded-lg border p-3"
        >
            <legend className="px-1 text-xs font-semibold">
                {widget ? widget.label : 'Appearance'}
            </legend>
            {showAppearance ? (
                <>
            <label className="block text-xs">
                Text color
                <ColorInput
                    aria-label="Text color"
                    value={String(props.color ?? '#ffffff')}
                    onValue={(color) => set('color', color)}
                />
            </label>
            {['shape', 'button'].includes(element.type) && (
                <label className="block text-xs">
                    Fill
                    <ColorInput
                        aria-label="Fill"
                        fallback="#2563eb"
                        value={String(props.fill ?? '#2563eb')}
                        onValue={(color) => set('fill', color)}
                    />
                </label>
            )}
            <div className="grid grid-cols-2 gap-2">
                <label className="text-xs">
                    Font size
                    <Input
                        aria-label="Font size"
                        type="number"
                        min={MIN_WIDGET_FONT_SIZE}
                        max={MAX_WIDGET_FONT_SIZE}
                        value={clampWidgetFontSize(props.fontSize, 24)}
                        onChange={(e) =>
                            set(
                                'fontSize',
                                clampWidgetFontSize(e.target.value, 24),
                            )
                        }
                    />
                    <Input
                        aria-label="Font size slider"
                        type="range"
                        min={MIN_WIDGET_FONT_SIZE}
                        max={MAX_WIDGET_FONT_SIZE}
                        value={clampWidgetFontSize(props.fontSize, 24)}
                        onChange={(e) =>
                            set(
                                'fontSize',
                                clampWidgetFontSize(e.target.value, 24),
                            )
                        }
                    />
                </label>
                <label className="text-xs">
                    Corners
                    <Input
                        aria-label="Corners"
                        type="number"
                        min={0}
                        value={Number(props.radius ?? 0)}
                        onChange={(e) =>
                            set('radius', Math.max(0, Number(e.target.value)))
                        }
                    />
                </label>
            </div>
            <select
                aria-label="Font family"
                className="w-full rounded border p-2"
                value={String(props.fontFamily ?? 'Arial')}
                onChange={(e) => set('fontFamily', e.target.value)}
            >
                {['Arial', 'Georgia', 'Verdana', 'Courier New'].map((font) => (
                    <option key={font}>{font}</option>
                ))}
            </select>
            <div className="flex gap-1">
                <Button
                    size="sm"
                    variant={props.fontWeight === '700' ? 'default' : 'outline'}
                    onClick={() =>
                        set(
                            'fontWeight',
                            props.fontWeight === '700' ? '400' : '700',
                        )
                    }
                >
                    Bold
                </Button>
                {['left', 'center', 'right'].map((align) => (
                    <Button
                        key={align}
                        size="sm"
                        variant={props.align === align ? 'default' : 'outline'}
                        onClick={() => set('align', align)}
                    >
                        {align}
                    </Button>
                ))}
            </div>
            {['image', 'logo'].includes(element.type) && (
                <>
                    <div className="space-y-1 text-xs">
                        <span>Image framing</span>
                        <div className="grid gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={props.objectFit === 'contain' ? 'outline' : 'default'}
                                onClick={() => onChange({ ...props, objectFit: 'cover', imageZoom: 1, imageX: 50, imageY: 50 })}
                            >
                                Fill block
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={props.objectFit === 'contain' ? 'default' : 'outline'}
                                onClick={() => onChange({ ...props, objectFit: 'contain', imageZoom: 1, imageX: 50, imageY: 50 })}
                            >
                                Show full image
                            </Button>
                        </div>
                        <p className="text-muted-foreground">Fill crops the edges; show full image keeps every edge visible.</p>
                    </div>
                    <label className="block text-xs">
                        Image zoom inside block
                        <Input
                            aria-label="Image zoom"
                            type="range"
                            min={1}
                            max={4}
                            step={0.05}
                            value={Number(props.imageZoom ?? 1)}
                            onChange={(e) =>
                                set('imageZoom', Number(e.target.value))
                            }
                        />
                    </label>
                    {['imageX', 'imageY'].map((key) => (
                        <label key={key} className="block text-xs">
                            {key === 'imageX'
                                ? 'Horizontal crop'
                                : 'Vertical crop'}
                            <Input
                                aria-label={key}
                                type="range"
                                min={0}
                                max={100}
                                value={Number(props[key] ?? 50)}
                                onChange={(e) =>
                                    set(key, Number(e.target.value))
                                }
                            />
                        </label>
                    ))}
                </>
            )}
                </>
            ) : null}
            {widget ? (
                <WidgetSchemaForm
                    widget={widget}
                    values={props}
                    disabled={disabled}
                    onChange={onChange}
                />
            ) : null}
        </fieldset>
    );
}
