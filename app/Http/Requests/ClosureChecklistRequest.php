<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class ClosureChecklistRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->hasPermission('scheduled_load.verify') ?? false; }
    public function rules(): array {
        return [
            'evidences_correct'=>['required','accepted'],
            'package_prepared_for_signature'=>['required','accepted'],
            'observations'=>['nullable','string','max:4000'],
        ];
    }
}
