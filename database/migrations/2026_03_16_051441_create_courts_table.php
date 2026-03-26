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
        Schema::create('courts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();

            $table->foreignId('sport_id')
                ->constrained('sports')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unique(['venue_id', 'name']);

            $table->decimal('price_per_hour', 10, 2)->unsigned();

            $table->enum('status', ['active', 'inactive'])
                ->default('active');

            $table->string('slug')->nullable();
            $table->unique(['venue_id', 'slug']);

            $table->timestamps();
            $table->softDeletes();

            // 🔥 smarter index
            $table->index(['venue_id', 'sport_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
