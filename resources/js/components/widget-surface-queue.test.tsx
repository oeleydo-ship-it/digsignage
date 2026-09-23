import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import type { WidgetJsonValue } from '@/types';
import WidgetSurface from './widget-surface';

function renderQueueWidget(key: string, data: Record<string, WidgetJsonValue>) {
    const container = document.createElement('div');
    container.innerHTML = renderToStaticMarkup(
        <WidgetSurface widget={{ key, settings: { heading: 'Queue' }, data }} />,
    );
    return container;
}

function board(data: Record<string, WidgetJsonValue>) {
    return renderQueueWidget('queue_board', data);
}

describe('queue monitor board', () => {
    it('blinks only the newly called counter and keeps the other visible', () => {
        const container = board({
            highlight_ticket_id: 12,
            now_serving: [
                { id: 12, number: 'REG005', counter: 'Counter 1' },
                { id: 11, number: 'REG003', counter: 'Counter 2' },
            ],
        });
        const highlighted = [...container.querySelectorAll('.queue-call-blink')];

        expect(container.textContent).toContain('REG005');
        expect(container.textContent).toContain('REG003');
        expect(highlighted).toHaveLength(1);
        expect(highlighted[0]?.textContent).toContain('REG005');
        expect(highlighted[0]?.textContent).not.toContain('REG003');
    });

    it('shows a ticket printed at the kiosk in the waiting section', () => {
        const container = board({ now_serving: [], waiting: [{ id: 15, number: 'REG006', position: 1 }] });

        expect(container.textContent).toContain('Waiting to be called');
        expect(container.textContent).toContain('Waiting');
        expect(container.textContent).toContain('REG006');
        expect(container.textContent).not.toContain('No tickets');
    });

    it('shows the next waiting kiosk ticket when no counter is serving', () => {
        const container = renderQueueWidget('queue_now_serving', {
            now_serving: [],
            waiting: [{ id: 15, number: 'REG006', position: 1 }],
        });

        expect(container.textContent).toContain('Waiting to be called');
        expect(container.textContent).toContain('REG006');
        expect(container.textContent).not.toContain('No one serving');
    });
});
