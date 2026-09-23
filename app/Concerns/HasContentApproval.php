<?php

namespace App\Concerns;

use App\Models\ContentApprovalEvent;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContentApproval
{
    /**
     * @return MorphMany<ContentApprovalEvent, $this>
     */
    public function approvalEvents(): MorphMany
    {
        return $this->morphMany(ContentApprovalEvent::class, 'approvable')->latest('id');
    }
}
