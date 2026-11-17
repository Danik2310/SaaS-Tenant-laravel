<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-recipient in-app notifications.
     *
     * One row per user so read state, deduplication and the unread badge never
     * need a pivot table. `dedupe_key` groups repeated events about the same
     * subject (e.g. one product running low) inside a cooldown window.
     */
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('level', 16)->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_read_created_index');
            $table->index(['user_id', 'type', 'dedupe_key'], 'notifications_user_type_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
