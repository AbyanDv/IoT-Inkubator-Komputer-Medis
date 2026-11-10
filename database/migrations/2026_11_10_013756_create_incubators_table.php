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
        Schema::create('incubators', function (Blueprint $table) {
            $table->id();
            $table->string('device_code')->unique();
            $table->string('name');
            $table->string('mac_address')->index();
            $table->string('device_token')->unique();
            $table->string('firmware_version')->nullable();
            $table->string('status')->default('offline');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->integer('last_config_version')->default(1);
            $table->foreignId('current_baby_id')->nullable()->constrained('babies')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incubators');
    }
};
