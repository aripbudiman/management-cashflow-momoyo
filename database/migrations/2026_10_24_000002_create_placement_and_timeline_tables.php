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
        Schema::create('project_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_of')->nullable()->constrained('project_placements')->nullOnDelete();
            $table->string('nomor_surat')->nullable()->unique();
            $table->date('tgl_surat')->nullable();
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->string('lokasi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('penandatangan_nama')->nullable();
            $table->string('penandatangan_jabatan')->nullable();
            $table->string('status', 20)->default('draft'); // draft | diterbitkan | dibatalkan | selesai
            $table->text('override_reason')->nullable();
            $table->longText('template_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('project_placement_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('project_placements')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('peran', 50);
            $table->timestamps();

            $table->unique(['placement_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('timeline_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('field', 30);
            $table->string('nilai_lama')->nullable();
            $table->string('nilai_baru')->nullable();
            $table->string('alasan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timeline_changes');
        Schema::dropIfExists('project_placement_employees');
        Schema::dropIfExists('project_placements');
    }
};
