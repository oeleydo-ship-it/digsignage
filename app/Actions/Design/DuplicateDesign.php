<?php

namespace App\Actions\Design;

use App\Models\Design;
use App\Models\User;

class DuplicateDesign
{
    /**
     * Duplicate a design as a new draft.
     */
    public function handle(User $user, Design $design): Design
    {
        return app(SaveDesign::class)->handle($user, $design->team, [
            'name' => $design->name.' copy',
            'description' => $design->description,
            'document' => $design->document,
        ]);
    }
}
