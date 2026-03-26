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
        Schema::create('sports', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();

            // 🔥 slug untuk URL / filtering (contoh: futsal, badminton)
            $table->string('slug')->unique();

            // 🔥 icon (bisa simpan file path atau nama icon library)
            $table->string('icon')->nullable();

            // 🔥 optional image (thumbnail)
            $table->string('image')->nullable();

            // 🔥 untuk enable/disable sport
            $table->boolean('is_active')->default(true);

            // 🔥 sorting order (biar frontend rapi)
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sports');
    }
};
