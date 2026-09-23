<?php

namespace App\Http\Requests\Platform;

use App\Enums\StorageProvider;
use App\Models\StorageDisk;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStorageDiskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', Rule::enum(StorageProvider::class)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'bucket' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'root' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255', 'url'],
            'url' => ['nullable', 'string', 'max:255', 'url'],
            'access_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:512'],
            'path_style_endpoint' => ['boolean'],
            'visibility' => ['required', 'string', Rule::in(['private', 'public'])],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Require the credentials and endpoint each provider actually needs.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $provider = StorageProvider::tryFrom((string) $this->input('provider'));

                if ($provider === null || ! $provider->isS3()) {
                    return;
                }

                if (blank($this->input('bucket'))) {
                    $validator->errors()->add('bucket', __('A bucket name is required for :provider.', [
                        'provider' => $provider->label(),
                    ]));
                }

                if ($provider->requiresEndpoint() && blank($this->input('endpoint'))) {
                    $validator->errors()->add('endpoint', __('An endpoint URL is required for :provider.', [
                        'provider' => $provider->label(),
                    ]));
                }

                if (! $provider->requiresEndpoint() && blank($this->input('endpoint')) && blank($this->input('region'))) {
                    $validator->errors()->add('region', __('A region is required for :provider.', [
                        'provider' => $provider->label(),
                    ]));
                }

                // Credentials may be left blank on an update to keep the stored secret.
                $existing = $this->route('storage_disk');
                $keeping = $existing instanceof StorageDisk && filled($existing->secret_key);

                if (blank($this->input('access_key')) && ! $keeping) {
                    $validator->errors()->add('access_key', __('An access key is required.'));
                }

                if (blank($this->input('secret_key')) && ! $keeping) {
                    $validator->errors()->add('secret_key', __('A secret key is required.'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'path_style_endpoint' => $this->boolean('path_style_endpoint'),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'team_id' => $this->input('team_id') === '' ? null : $this->input('team_id'),
        ]);
    }
}
