<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('calendar_import_rows')) {
            return;
        }

        if (! Schema::hasColumn('calendar_import_rows', 'source_column')) {
            Schema::table('calendar_import_rows', function (Blueprint $table) {
                $table->string('source_column', 20)->nullable()->after('row_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('calendar_import_rows')) {
            return;
        }

        if (Schema::hasColumn('calendar_import_rows', 'source_column')) {
            Schema::table('calendar_import_rows', function (Blueprint $table) {
                $table->dropColumn('source_column');
            });
        }
    }
};
