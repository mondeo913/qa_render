<?php

use App\Models\TemplateRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        TemplateRequirement::query()
            ->where('code', 'BITACORA_EXCEL')
            ->get()
            ->each(function (TemplateRequirement $requirement): void {
                $extensions = array_values(array_unique(array_merge(
                    array_map('strtolower', $requirement->allowed_extensions ?? []),
                    ['doc', 'docx']
                )));
                $requirement->update(['allowed_extensions' => $extensions]);
            });
    }

    public function down(): void
    {
        TemplateRequirement::query()
            ->where('code', 'BITACORA_EXCEL')
            ->get()
            ->each(function (TemplateRequirement $requirement): void {
                $extensions = array_values(array_filter(
                    array_map('strtolower', $requirement->allowed_extensions ?? []),
                    static fn (string $extension): bool => !in_array($extension, ['doc', 'docx'], true)
                ));
                $requirement->update(['allowed_extensions' => $extensions]);
            });
    }
};
