<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 🔥 tipe notif (booking, promo, review, system)
            $table->string('type');

            $table->string('title');
            $table->text('message');

            // 🔥 polymorphic relation (optional, powerful banget)
            $table->nullableMorphs('notifiable');
            // notifiable_id + notifiable_type

            // 🔥 optional link (redirect ke frontend)
            $table->string('action_url')->nullable();

            // 🔥 payload tambahan (json)
            $table->json('data')->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
