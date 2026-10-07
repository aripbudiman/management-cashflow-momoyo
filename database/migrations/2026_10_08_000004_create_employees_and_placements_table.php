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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nik', 30)->unique();
            $table->string('full_name');
            $table->string('phone_number', 20)->nullable();
            $table->foreignId('current_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active, inactive, probation
            $table->timestamps();

            $table->index(['current_branch_id', 'status']);
        });

        Schema::create('employee_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('surat_tugas_number')->nullable();
            $table->text('surat_tugas_file_url')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_placements');
        Schema::dropIfExists('employees');
    }
};
