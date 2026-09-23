import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import ChannelZoneCanvas, {
    applyDrag,
    readZoneIndex,
    zonePixelSize,
} from './channel-zone-canvas';
import type { ChannelZoneRecord } from '@/types';

function zone(overrides: Partial<ChannelZoneRecord> = {}): ChannelZoneRecord {
    return {
        name: 'Main',
        playlist_id: null,
        x: 10,
        y: 20,
        width: 40,
        height: 30,
        z_index: 1,
        ...overrides,
    };
}

function dataTransfer(types: string[], data: Record<string, string> = {}) {
    return {
        types,
        getData: (type: string) => data[type] ?? '',
    } as unknown as DataTransfer;
}

describe('zonePixelSize', () => {
    it('converts percentage zones to stage pixels', () => {
        expect(
            zonePixelSize(zone({ width: 50, height: 25 }), 1920, 1080),
        ).toEqual({
            width: 960,
            height: 270,
        });
    });
});

describe('applyDrag', () => {
    it('moves a zone and clamps it inside the canvas', () => {
        expect(applyDrag(zone(), 'move', 5, -5)).toEqual({
            x: 15,
            y: 15,
            width: 40,
            height: 30,
        });

        expect(applyDrag(zone(), 'move', -50, 90)).toEqual({
            x: 0,
            y: 70,
            width: 40,
            height: 30,
        });
    });

    it('resizes from the east handle without exceeding the canvas', () => {
        expect(applyDrag(zone(), 'e', 10, 0)).toEqual({
            x: 10,
            y: 20,
            width: 50,
            height: 30,
        });

        expect(applyDrag(zone(), 'e', 500, 0).width).toBe(90);
    });

    it('resizes from the west handle by moving the origin', () => {
        expect(applyDrag(zone(), 'w', 5, 0)).toEqual({
            x: 15,
            y: 20,
            width: 35,
            height: 30,
        });

        expect(applyDrag(zone(), 'w', -5, 0)).toEqual({
            x: 5,
            y: 20,
            width: 45,
            height: 30,
        });
    });

    it('enforces the minimum zone size', () => {
        const resized = applyDrag(zone(), 'se', -500, -500);

        expect(resized.width).toBe(8);
        expect(resized.height).toBe(8);
    });

    it('resizes from the north handle without leaving the canvas', () => {
        const resized = applyDrag(zone(), 'n', 0, -500);

        expect(resized.y).toBe(0);
        expect(resized.height).toBe(50);
    });
});

describe('readZoneIndex', () => {
    it('returns null when the payload type is absent', () => {
        expect(readZoneIndex(dataTransfer([]))).toBeNull();
    });

    it('returns null for empty or non-integer payloads', () => {
        expect(
            readZoneIndex(dataTransfer(['application/x-zone-index'])),
        ).toBeNull();
        expect(
            readZoneIndex(
                dataTransfer(['application/x-zone-index'], {
                    'application/x-zone-index': 'abc',
                }),
            ),
        ).toBeNull();
        expect(
            readZoneIndex(
                dataTransfer(['application/x-zone-index'], {
                    'application/x-zone-index': '-1',
                }),
            ),
        ).toBeNull();
    });

    it('parses a valid zone index', () => {
        expect(
            readZoneIndex(
                dataTransfer(['application/x-zone-index'], {
                    'application/x-zone-index': '2',
                }),
            ),
        ).toBe(2);
    });
});

describe('ChannelZoneCanvas', () => {
    it('renders zones with their playlist names and pixel sizes', () => {
        render(
            <ChannelZoneCanvas
                zones={[zone({ playlist_id: 7 })]}
                playlists={[{ id: 7, name: 'Morning Loop' }]}
                selected={0}
                editable={false}
                onSelect={() => {}}
                onUpdate={() => {}}
            />,
        );

        const firstZone = screen.getByTestId('zone-0');

        expect(within(firstZone).getByText('Main')).toBeInTheDocument();
        expect(within(firstZone).getByText('Morning Loop')).toBeInTheDocument();
        expect(within(firstZone).getByText('768 × 324 px')).toBeInTheDocument();
    });

    it('calls onSelect when a zone is clicked', () => {
        const onSelect = vi.fn();

        render(
            <ChannelZoneCanvas
                zones={[zone(), zone({ name: 'Ticker', x: 60 })]}
                playlists={[]}
                selected={0}
                editable
                onSelect={onSelect}
                onUpdate={() => {}}
            />,
        );

        fireEvent.click(screen.getByTestId('zone-1'));

        expect(onSelect).toHaveBeenCalledWith(1);
    });

    it('assigns a playlist to the selected zone when a chip is clicked', () => {
        const onUpdate = vi.fn();

        render(
            <ChannelZoneCanvas
                zones={[zone()]}
                playlists={[{ id: 3, name: 'Promos' }]}
                selected={0}
                editable
                onSelect={() => {}}
                onUpdate={onUpdate}
            />,
        );

        fireEvent.click(screen.getByTestId('playlist-chip-3'));

        expect(onUpdate).toHaveBeenCalledWith(0, { playlist_id: 3 });
    });
});
