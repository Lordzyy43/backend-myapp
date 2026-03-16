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
        Schema::create('promos', function (Blueprint $table) {
            $table->id();

            $table->string('promo_code')->unique();

            $table->string('description')->nullable();

            $table->enum('discount_type', ['percentage', 'fixed']);

            $table->decimal('discount_value', 10, 2);

            $table->date('start_date');

            $table->date('end_date');

            $table->integer('usage_limit')->nullable();

            $table->integer('used_count')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
