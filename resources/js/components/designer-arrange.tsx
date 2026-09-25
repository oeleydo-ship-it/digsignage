import {
    AlignCenterHorizontal,
    AlignCenterVertical,
    AlignEndHorizontal,
    AlignEndVertical,
    AlignStartHorizontal,
    AlignStartVertical,
    ArrowDownToLine,
    ArrowUpToLine,
    ChevronDown,
    ChevronUp,
    Maximize,
    MoveHorizontal,
    MoveVertical,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    alignToCanvas,
    canReorder,
    reorderLayers,
    stretchToCanvas,
    type AlignAction,
    type OrderAction,
    type StretchAction,
} from '@/lib/designer-arrange';
import type { DesignDocument, DesignDocumentElement } from '@/types';

const ALIGN_ACTIONS: {
    action: AlignAction;
    label: string;
    Icon: typeof AlignStartVertical;
}[] = [
    { action: 'left', label: 'Align left', Icon: AlignStartVertical },
    {
        action: 'center',
        label: 'Center horizontally',
        Icon: AlignCenterVertical,
    },
    { action: 'right', label: 'Align right', Icon: AlignEndVertical },
    { action: 'top', label: 'Align top', Icon: AlignStartHorizontal },
    {
        action: 'middle',
        label: 'Center vertically',
        Icon: AlignCenterHorizontal,
    },
    { action: 'bottom', label: 'Align bottom', Icon: AlignEndHorizontal },
];

const STRETCH_ACTIONS: {
    action: StretchAction;
    label: string;
    Icon: typeof MoveHorizontal;
}[] = [
    { action: 'width', label: 'Span full width', Icon: MoveHorizontal },
    { action: 'height', label: 'Span full height', Icon: MoveVertical },
    { action: 'canvas', label: 'Fill the canvas', Icon: Maximize },
];

const ORDER_ACTIONS: {
    action: OrderAction;
    label: string;
    Icon: typeof ArrowUpToLine;
}[] = [
    { action: 'front', label: 'Bring to front', Icon: ArrowUpToLine },
    { action: 'forward', label: 'Bring forward', Icon: ChevronUp },
    { action: 'backward', label: 'Send backward', Icon: ChevronDown },
    { action: 'back', label: 'Send to back', Icon: ArrowDownToLine },
];

function ArrangeButton({
    label,
    disabled,
    onClick,
    children,
    testId,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: React.ReactNode;
    testId: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-7"
                    aria-label={label}
                    data-test={testId}
                    disabled={disabled}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

/**
 * Align, stretch and re-stack the selected element.
 *
 * Alignment is measured against the canvas: with a single selection there is
 * no sibling bounding box to align to.
 */
export default function DesignerArrange({
    document,
    selected,
    disabled = false,
    onApply,
    onReorder,
}: {
    document: DesignDocument;
    selected: DesignDocumentElement | null;
    disabled?: boolean;
    onApply: (patch: Partial<DesignDocumentElement>) => void;
    onReorder: (elements: DesignDocumentElement[]) => void;
}) {
    const inactive = disabled || selected === null || selected.locked;

    return (
        <div
            className="flex flex-wrap items-center gap-0.5"
            role="group"
            aria-label="Arrange element"
        >
            {ALIGN_ACTIONS.map(({ action, label, Icon }) => (
                <ArrangeButton
                    key={action}
                    label={label}
                    testId={`align-${action}`}
                    disabled={inactive}
                    onClick={() =>
                        selected &&
                        onApply(alignToCanvas(selected, document, action))
                    }
                >
                    <Icon className="size-4" />
                </ArrangeButton>
            ))}

            <span aria-hidden className="bg-border mx-1 h-5 w-px" />

            {STRETCH_ACTIONS.map(({ action, label, Icon }) => (
                <ArrangeButton
                    key={action}
                    label={label}
                    testId={`stretch-${action}`}
                    disabled={inactive}
                    onClick={() =>
                        selected &&
                        onApply(stretchToCanvas(selected, document, action))
                    }
                >
                    <Icon className="size-4" />
                </ArrangeButton>
            ))}

            <span aria-hidden className="bg-border mx-1 h-5 w-px" />

            {ORDER_ACTIONS.map(({ action, label, Icon }) => (
                <ArrangeButton
                    key={action}
                    label={label}
                    testId={`order-${action}`}
                    disabled={
                        inactive ||
                        !selected ||
                        !canReorder(document.elements, selected.id, action)
                    }
                    onClick={() =>
                        selected &&
                        onReorder(
                            reorderLayers(
                                document.elements,
                                selected.id,
                                action,
                            ),
                        )
                    }
                >
                    <Icon className="size-4" />
                </ArrangeButton>
            ))}
        </div>
    );
}
