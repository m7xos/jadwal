<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personil_categories') && ! Schema::hasColumn('personil_categories', 'label_broadcast')) {
            Schema::table('personil_categories', function (Blueprint $table) {
                $table->string('label_broadcast', 120)->nullable()->after('nama');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personil_categories') && Schema::hasColumn('personil_categories', 'label_broadcast')) {
            Schema::table('personil_categories', function (Blueprint $table) {
                $table->dropColumn('label_broadcast');
            });
        }
    }
};
