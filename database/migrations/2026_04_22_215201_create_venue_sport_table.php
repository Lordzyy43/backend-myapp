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
        Schema::create('venue_sport', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel venues
            $table->foreignId('venue_id')->constrained()->onDelete('cascade');
            // Menghubungkan ke tabel sports
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');

            /* Opsional: Jika ingin memastikan tidak ada data ganda 
           (misal: 1 venue tidak sengaja terhubung ke 1 sport yang sama 2 kali)
        */
            $table->unique(['venue_id', 'sport_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_sport');
    }
};
