<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Snapshot cabang saat pengajuan dibuat (karyawan bisa di-rolling)
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained('branches')->nullOnDelete();
            $table->unsignedSmallInteger('total_days')->default(1)->after('end_date');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['total_days', 'approved_at']);
        });
    }
};
