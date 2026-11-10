<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('age_min_hours');
            $table->unsignedSmallInteger('weight_min_grams');
            $table->unsignedSmallInteger('weight_max_grams');
            $table->decimal('temp_min', 4, 1);
            $table->decimal('temp_max', 4, 1);
            $table->timestamps();
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->decimal('skin_temperature', 4, 1);
            $table->decimal('incubator_temperature', 4, 1);
            $table->unsignedTinyInteger('humidity');
            $table->decimal('setpoint', 4, 1)->nullable();
            $table->unsignedTinyInteger('heaters');
            $table->boolean('buzzer');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['baby_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
        Schema::dropIfExists('temperature_profiles');
    }
};
