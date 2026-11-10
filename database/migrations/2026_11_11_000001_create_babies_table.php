<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('babies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('weight_grams');
            $table->boolean('premature');
            $table->timestamp('incubator_started_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('babies');
    }
};
