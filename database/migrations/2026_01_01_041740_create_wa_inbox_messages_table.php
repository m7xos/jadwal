<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_number', 32);
            $table->string('sender_name', 120)->nullable();
            $table->text('message');
            $table->timestamp('received_at')->useCurrent();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('personils')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('personils')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->text('reply_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index('sender_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_inbox_messages');
    }
};
