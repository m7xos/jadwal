<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('follow_up_reminders')) {
            return;
        }

        Schema::create('follow_up_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('nama_kegiatan');
            $table->date('tanggal');
            $table->time('jam');
            $table->string('tempat')->nullable();
            $table->string('no_wa', 30);
            $table->string('normalized_no_wa', 30)->index();
            $table->string('status')->default('pending'); // pending, acknowledged
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('last_response')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_reminders');
    }
};
