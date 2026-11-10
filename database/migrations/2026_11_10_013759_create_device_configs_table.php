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
        Schema::create('device_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incubator_id')->constrained('incubators')->cascadeOnDelete();
            $table->integer('config_version');
            $table->decimal('temperature_setpoint', 4, 2)->default(30.00);
            $table->decimal('pid_kp', 6, 3)->default(2.000);
            $table->decimal('pid_ki', 6, 3)->default(0.100);
            $table->decimal('pid_kd', 6, 3)->default(0.500);
            $table->string('fan_mode')->default('pwm');
            $table->boolean('blue_led_enabled')->default(false);
            $table->boolean('simulation_mode')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_configs');
    }
};
