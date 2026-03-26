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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();

            // 🔥 owner (user)
            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 🔥 basic info
            $table->string('name');
            $table->string('slug')->unique();

            $table->text('address');
            $table->string('city');

            $table->text('description')->nullable();

            // 🔥 timestamps (WAJIB untuk tracking)
            $table->timestamps();

            /**
             * 🔥 INDEXING (PERFORMANCE)
             */

            // sering dipakai filter
            $table->index('city');

            // untuk query owner (dashboard)
            $table->index('owner_id');

            // 🔥 optional (prevent duplicate venue name per owner)
            $table->unique(['owner_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
