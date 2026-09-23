<?php

namespace App\Http\Requests\Signage;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $location = $this->route('location');
        $locationId = $location instanceof Location ? $location->id : null;
        $parentId = $this->input('parent_id');

        $unique = Rule::unique('locations', 'name')
            ->where(fn ($query) => $query
                ->where('team_id', $teamId)
                ->when(
                    $parentId,
                    fn ($query) => $query->where('parent_id', $parentId),
                    fn ($query) => $query->whereNull('parent_id'),
                ));

        if ($locationId) {
            $unique->ignore($locationId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $unique],
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone:all'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
