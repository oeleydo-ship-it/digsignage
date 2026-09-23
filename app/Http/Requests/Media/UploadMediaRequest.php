<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $max = (int) config('media.max_file_kilobytes');

        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:'.$max],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('team_id', $teamId),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('folder_id') === '') {
            $this->merge(['folder_id' => null]);
        }
    }

    public function messages(): array
    {
        $messages = [];
        foreach ($this->file('files', []) as $index => $file) {
            if (! $file->isValid()) {
                $messages["files.$index.uploaded"] = match ($file->getError()) {
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server cannot write temporary upload files. Please ask the administrator to configure a writable PHP upload directory and restart the server.',
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the server upload size limit. Please choose a smaller file.',
                    UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                    default => 'The file could not be uploaded. Please try again.',
                };
            }
        }

        return $messages;
    }
}
