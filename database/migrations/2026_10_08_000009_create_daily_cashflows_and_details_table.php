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
        Schema::create('daily_cashflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('closing_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_omset', 15, 2)->default(0);
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('total_net_cash', 15, 2)->default(0);
            $table->string('status', 30)->default('submitted'); // submitted, verified_by_finance, revision_needed
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'report_date']);
            $table->index(['report_date', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('cashflow_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashflow_id')->constrained('daily_cashflows')->cascadeOnDelete();
            $table->string('payment_method', 30); // cash, qris, transfer_bank, edc_debit, gofood, grabfood, shopeefood
            $table->decimal('nominal', 15, 2)->default(0);
            $table->text('settlement_batch_proof_url')->nullable();
            $table->timestamps();

            $table->index(['cashflow_id', 'payment_method']);
        });

        Schema::create('cashflow_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashflow_id')->constrained('daily_cashflows')->cascadeOnDelete();
            $table->string('category', 100);
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('description');
            $table->text('receipt_photo_url')->nullable();
            $table->timestamps();

            $table->index(['cashflow_id']);
        });

        Schema::create('setoran_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashflow_id')->constrained('daily_cashflows')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('company_bank_accounts')->cascadeOnDelete();
            $table->decimal('transfer_amount', 15, 2)->default(0);
            $table->timestamp('transfer_time')->nullable();
            $table->text('proof_receipt_url');
            $table->string('status', 30)->default('pending_verification'); // pending_verification, verified, mismatch
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['cashflow_id', 'status']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setoran_transfers');
        Schema::dropIfExists('cashflow_expenses');
        Schema::dropIfExists('cashflow_details');
        Schema::dropIfExists('daily_cashflows');
    }
};
