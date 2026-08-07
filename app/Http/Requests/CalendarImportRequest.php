<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendarImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('calendar.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'contracting_agency_id' => [
                'required',
                'integer',
                'exists:contracting_agencies,id',
            ],
            'schedule_year' => [
                'required',
                'integer',
                'min:2020',
                'max:2100',
            ],
            'calendar_file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:20480',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'calendar_file.mimes' => 'La pauta debe ser un archivo Excel XLSX o XLS.',
            'schedule_year.required' => 'Indique el año al que corresponde la pauta.',
        ];
    }
}
