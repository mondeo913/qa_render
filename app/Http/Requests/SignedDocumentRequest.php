<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class SignedDocumentRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->hasPermission('scheduled_load.upload_signed') ?? false; }
    public function rules(): array {
        return [
            'file'=>['required','file','max:524288'],
            'signer_name'=>['nullable','string','max:220'],
            'signer_position'=>['nullable','string','max:220'],
            'signed_on'=>['nullable','date'],
            'official_number'=>['nullable','string','max:120'],
            'observations'=>['nullable','string','max:2000'],
        ];
    }
}
