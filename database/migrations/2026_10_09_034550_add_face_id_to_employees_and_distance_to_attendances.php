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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('face_id', 100)->nullable()->after('status');
            $table->string('face_photo_path', 255)->nullable()->after('face_id');
            $table->timestamp('face_registered_at')->nullable()->after('face_photo_path');
            $table->json('face_data')->nullable()->after('face_registered_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_distance_meters', 8, 2)->nullable()->after('clock_in_long');
            $table->decimal('clock_out_distance_meters', 8, 2)->nullable()->after('clock_out_long');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_distance_meters', 'clock_out_distance_meters']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['face_id', 'face_photo_path', 'face_registered_at', 'face_data']);
        });
    }
};
