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
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_face_match_percentage', 5, 2)->nullable()->after('clock_in_distance_meters');
            $table->decimal('clock_out_face_match_percentage', 5, 2)->nullable()->after('clock_out_distance_meters');
            $table->boolean('is_face_mismatch')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_face_match_percentage', 'clock_out_face_match_percentage', 'is_face_mismatch']);
        });
    }
};
