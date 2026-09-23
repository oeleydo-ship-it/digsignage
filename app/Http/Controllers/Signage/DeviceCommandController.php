<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Player\DispatchDeviceCommand;
use App\Enums\DeviceCommandType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DeviceCommand;
use App\Models\Screen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeviceCommandController extends Controller
{
    /**
     * Show command history and the send-command form.
     */
    public function index(Request $request, string $current_team, Screen $screen): Response
    {
        Gate::authorize('view', $screen);
        $this->assertTeam($request, $current_team, $screen);

        $team = $request->user()->currentTeam;

        $commands = DeviceCommand::query()
            ->where('screen_id', $screen->id)
            ->with('issuer:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DeviceCommand $command) => [
                'id' => $command->id,
                'command' => $command->command->value,
                'command_label' => $command->command->label(),
                'payload' => $command->payload,
                'status' => $command->status->value,
                'status_label' => $command->status->label(),
                'result' => $command->result,
                'issued_by' => $command->issuer?->name,
                'created_at' => $command->created_at?->toIso8601String(),
                'sent_at' => $command->sent_at?->toIso8601String(),
                'acknowledged_at' => $command->acknowledged_at?->toIso8601String(),
                'completed_at' => $command->completed_at?->toIso8601String(),
                'expires_at' => $command->expires_at?->toIso8601String(),
            ]);

        return Inertia::render('signage/screens/commands', [
            'screen' => [
                'id' => $screen->id,
                'name' => $screen->name,
                'paired' => $screen->isPaired(),
                'status' => $screen->status->value,
                'status_label' => $screen->status->label(),
                'current_channel_id' => $screen->current_channel_id,
            ],
            'commands' => $commands,
            'channels' => Channel::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name']),
            'types' => collect(DeviceCommandType::cases())->map(fn (DeviceCommandType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'canCommand' => $request->user()->can('command', $screen),
        ]);
    }

    /**
     * Dispatch a remote command to a paired screen.
     */
    public function store(
        Request $request,
        string $current_team,
        Screen $screen,
        DispatchDeviceCommand $dispatch,
    ): RedirectResponse {
        Gate::authorize('command', $screen);
        $this->assertTeam($request, $current_team, $screen);

        $validated = $request->validate([
            'command' => ['required', Rule::enum(DeviceCommandType::class)],
            'payload' => ['nullable', 'array'],
            'payload.channel_id' => ['nullable', 'integer'],
            'payload.title' => ['nullable', 'string', 'max:120'],
            'payload.message' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispatch->handle(
            $screen,
            DeviceCommandType::from($validated['command']),
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Command sent.')]);

        return back(fallback: route('screens.commands.index', [$request->user()->currentTeam, $screen]));
    }

    protected function assertTeam(Request $request, string $currentTeam, Screen $screen): void
    {
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
    }
}
