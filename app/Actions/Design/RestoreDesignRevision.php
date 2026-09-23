<?php

namespace App\Actions\Design;

use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\User;

class RestoreDesignRevision
{
    /**
     * Restore a previous canvas revision onto the design.
     */
    public function handle(User $user, Design $design, DesignRevision $revision): Design
    {
        abort_unless($revision->design_id === $design->id, 404);
        abort_unless($revision->team_id === $design->team_id, 403);

        return app(SaveDesign::class)->handle($user, $design->team, [
            'name' => $design->name,
            'description' => $design->description,
            'status' => $design->status->value,
            'document' => $revision->document,
        ], $design);
    }
}
