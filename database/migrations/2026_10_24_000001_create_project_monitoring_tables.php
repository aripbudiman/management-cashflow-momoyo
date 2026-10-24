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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('client_name')->nullable();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('pic_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('alamat')->nullable();
            $table->decimal('luas_m2', 10, 2)->nullable();
            $table->date('tgl_mulai_rencana')->nullable();
            $table->date('tgl_selesai_rencana')->nullable();
            $table->decimal('nilai_kontrak', 16, 2)->default(0);
            $table->string('progress_mode', 20)->default('weighted'); // weighted | simple
            $table->decimal('progress', 5, 2)->default(0);
            $table->string('status', 30)->default('draft'); // draft, rab_dikirim, berjalan, tagihan_dikirim, selesai, dibatalkan
            $table->text('catatan')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kategori', 30); // gambar_kerja, rendering_3d, material_list, lainnya
            $table->string('nama_file');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rab_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('status', 20)->default('draft'); // draft | sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'versi']);
        });

        Schema::create('work_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rab_version_id')->constrained()->cascadeOnDelete();
            $table->string('kode_bidang', 20);
            $table->string('nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('work_sections')->cascadeOnDelete();
            $table->string('uraian');
            $table->text('deskripsi')->nullable();
            $table->decimal('qty', 14, 2)->default(0);
            $table->string('satuan', 30);
            $table->decimal('harga_satuan', 16, 2)->default(0);
            $table->unsignedInteger('durasi_hari')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 20)->default('belum_mulai'); // belum_mulai | berjalan | selesai
            $table->date('tgl_rencana_mulai')->nullable();
            $table->date('tgl_rencana_selesai')->nullable();
            $table->date('tgl_aktual_mulai')->nullable();
            $table->date('tgl_aktual_selesai')->nullable();
            $table->boolean('is_addendum')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedTinyInteger('progress_dari');
            $table->unsignedTinyInteger('progress_ke');
            $table->text('catatan')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['work_item_id', 'created_at']);
        });

        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_log_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
        Schema::dropIfExists('progress_logs');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('work_sections');
        Schema::dropIfExists('rab_versions');
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('projects');
    }
};
