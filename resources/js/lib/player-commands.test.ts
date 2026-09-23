import { describe, expect, it, vi } from 'vitest';
import {
    executePlayerCommand,
    type CommandHandlers,
    type PlayerCommand,
} from './player-commands';

function command(name: string): PlayerCommand {
    return {
        command_id: 1,
        screen_id: 2,
        command: name,
        payload: { emergency_id: 9, title: 'Evacuate' },
        status: 'pending',
    };
}

function handlers(): CommandHandlers {
    return {
        sync: vi.fn(async () => undefined),
        reload: vi.fn(),
        clearCache: vi.fn(async () => undefined),
        emergencyStart: vi.fn(),
        emergencyStop: vi.fn(),
    };
}

describe('player emergency commands', () => {
    it('activates the emergency before synchronizing its manifest', async () => {
        const player = handlers();

        await expect(
            executePlayerCommand(command('emergency-start'), player),
        ).resolves.toEqual({ action: 'emergency-start', emergency_id: 9 });

        expect(player.emergencyStart).toHaveBeenCalledWith({
            emergency_id: 9,
            title: 'Evacuate',
        });
        expect(player.sync).toHaveBeenCalledOnce();
    });

    it('removes the overlay and synchronizes restored queue state', async () => {
        const player = handlers();

        await expect(
            executePlayerCommand(command('emergency-stop'), player),
        ).resolves.toEqual({ action: 'emergency-stop' });

        expect(player.emergencyStop).toHaveBeenCalledWith({
            emergency_id: 9,
            title: 'Evacuate',
        });
        expect(player.sync).toHaveBeenCalledOnce();
        expect(
            vi.mocked(player.emergencyStop).mock.invocationCallOrder[0],
        ).toBeLessThan(vi.mocked(player.sync).mock.invocationCallOrder[0]);
    });
});
