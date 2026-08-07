<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvidenceUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('evidence.upload') ?? false;
    }

    public function rules(): array
    {
        return [
            'deliverable_id' => [
                'required',
                'integer',
                'exists:scheduled_load_deliverables,id',
            ],
            'title' => ['nullable', 'string', 'max:260'],
            'file' => ['required', 'file', 'max:1048576'],
        ];
    }
}
