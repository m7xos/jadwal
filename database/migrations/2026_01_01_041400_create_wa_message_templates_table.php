<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_message_templates')) {
            return;
        }

        Schema::create('wa_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_templates');
    }
};
