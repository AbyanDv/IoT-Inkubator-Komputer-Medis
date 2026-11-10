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
        Schema::create('device_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incubator_id')->constrained('incubators')->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained('babies')->cascadeOnDelete();
            $table->foreignId('paired_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paired_at');
            $table->timestamp('unpaired_at')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
    }
};
