<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            // Snapshot cabang saat jadwal dibuat (karyawan bisa di-rolling ke cabang lain)
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_off')->default(false);
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            // Snapshot data shift agar laporan tidak berubah saat master shift diedit
            $table->string('shift_name', 50)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
