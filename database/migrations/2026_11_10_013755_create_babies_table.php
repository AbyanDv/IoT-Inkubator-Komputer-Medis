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
        Schema::create('babies', function (Blueprint $table) {
            $table->id();
            $table->string('baby_code')->unique();
            $table->string('name');
            $table->dateTime('birth_date');
            $table->integer('gestational_age_weeks');
            $table->integer('weight_grams');
            $table->float('length_cm');
            $table->enum('gender', ['male', 'female']);
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('babies');
    }
};
