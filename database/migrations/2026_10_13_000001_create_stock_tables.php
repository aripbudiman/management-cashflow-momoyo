<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 30);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'item_id']);
        });

        Schema::create('stock_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('submitted'); // submitted, verified, revision_needed
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('revision_notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'report_date']);
            $table->index(['report_date', 'status']);
        });

        Schema::create('stock_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('stock_reports')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('opening_qty', 12, 2)->default(0);
            $table->decimal('in_qty', 12, 2)->default(0);
            $table->decimal('out_qty', 12, 2)->default(0);
            $table->decimal('waste_qty', 12, 2)->default(0);
            $table->decimal('expected_qty', 12, 2)->default(0);
            $table->decimal('physical_qty', 12, 2)->nullable();
            $table->decimal('diff_qty', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'item_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->string('status', 20)->default('sent'); // sent, received, cancelled
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('received_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['from_branch_id', 'status']);
            $table->index(['to_branch_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('type', 20); // in, out, waste, adjustment, transfer_in, transfer_out
            $table->decimal('qty', 12, 2); // bertanda: + menambah, - mengurangi
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->foreignId('report_id')->nullable()->constrained('stock_reports')->cascadeOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('moved_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'item_id', 'moved_at']);
            $table->index(['report_id']);
            $table->index(['moved_at', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_report_items');
        Schema::dropIfExists('stock_reports');
        Schema::dropIfExists('branch_stocks');
        Schema::dropIfExists('items');
    }
};
