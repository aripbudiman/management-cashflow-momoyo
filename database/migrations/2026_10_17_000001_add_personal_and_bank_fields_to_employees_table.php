<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('birth_place', 100)->nullable()->after('full_name');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->text('address')->nullable()->after('birth_date');
            $table->string('bank_name', 50)->nullable()->after('phone_number');
            $table->string('bank_account_number', 30)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['birth_place', 'birth_date', 'address', 'bank_name', 'bank_account_number', 'bank_account_name']);
        });
    }
};
