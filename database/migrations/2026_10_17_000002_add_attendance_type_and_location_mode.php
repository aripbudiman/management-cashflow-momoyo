<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // radius = wajib di area cabang, flexible = bisa absen di mana saja (lokasi tetap dicatat)
            $table->string('attendance_type', 20)->default('radius')->after('status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Karyawan fleksibel bisa tidak terikat cabang
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            // Snapshot tipe absensi saat absen dilakukan
            $table->string('location_mode', 20)->default('radius')->after('status');
        });

        // Admin Pusat bekerja fleksibel: set otomatis untuk karyawan yang berrole admin_pusat
        DB::table('employees')
            ->whereIn('user_id', DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.name', 'admin_pusat')
                ->select('users.id'))
            ->update(['attendance_type' => 'flexible']);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('location_mode');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('attendance_type');
        });
        // branch_id sengaja tidak dikembalikan NOT NULL: baris tanpa cabang akan membuat rollback gagal
    }
};
