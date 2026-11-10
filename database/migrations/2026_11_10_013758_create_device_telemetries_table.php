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
        Schema::create('device_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incubator_id')->constrained('incubators')->cascadeOnDelete();
            $table->foreignId('baby_id')->nullable()->constrained('babies')->nullOnDelete();
            $table->decimal('temperature_object', 5, 2)->nullable();
            $table->decimal('temperature_room', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->boolean('heater_status')->default(false);
            $table->integer('heater_pwm')->default(0);
            $table->boolean('fan_status')->default(false);
            $table->integer('fan_pwm')->default(0);
            $table->boolean('blue_led_status')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['incubator_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_telemetries');
    }
};
