<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->string('system_role', 40)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('memo');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->integer('debit_cents')->default(0);
            $table->integer('credit_cents')->default(0);
            $table->string('description')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vat_number')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable()->unique();
            $table->string('supplier_reference')->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('subtotal_cents')->default(0);
            $table->integer('vat_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('capital')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->integer('unit_price_cents');
            $table->boolean('price_includes_vat')->default(false);
            $table->string('vat_code');
            $table->unsignedSmallInteger('vat_basis_points');
            $table->integer('net_cents');
            $table->integer('vat_cents');
            $table->integer('total_cents');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('bank_account_id')->nullable();
            $table->string('number')->nullable()->unique();
            $table->date('spent_on');
            $table->string('payee');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->integer('net_cents');
            $table->integer('vat_cents');
            $table->integer('total_cents');
            $table->string('vat_code');
            $table->unsignedSmallInteger('vat_basis_points');
            $table->boolean('capital')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('posted_on');
            $table->string('description');
            $table->integer('amount_cents');
            $table->string('reference')->nullable();
            $table->string('import_hash', 64)->unique();
            $table->foreignId('journal_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reconciliation_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('contains');
            $table->foreignId('account_id')->constrained();
            $table->boolean('capital')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('statement_date');
            $table->integer('statement_balance_cents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreign('reconciliation_id')->references('id')->on('bank_reconciliations')->nullOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        $now = now();
        $rows = [
            ['1000', 'Current account', 'asset', 'bank'],
            ['1010', 'Petty cash', 'asset', null],
            ['1100', 'Accounts receivable', 'asset', 'receivable'],
            ['1200', 'Inventory', 'asset', 'inventory'],
            ['1300', 'VAT input', 'asset', 'vat_input'],
            ['1310', 'VAT input deferred', 'asset', 'vat_input_deferred'],
            ['2000', 'Accounts payable', 'liability', 'payable'],
            ['2100', 'VAT output', 'liability', 'vat_output'],
            ['2110', 'VAT output deferred', 'liability', 'vat_output_deferred'],
            ['3000', 'Retained earnings', 'equity', 'retained_earnings'],
            ['3100', 'Owner contributions', 'equity', null],
            ['3200', 'Owner drawings', 'equity', null],
            ['4000', 'Sales', 'income', 'sales'],
            ['4100', 'Other income', 'income', null],
            ['5000', 'Cost of sales', 'expense', 'cost_of_sales'],
            ['5100', 'Purchases', 'expense', 'purchases'],
            ['6000', 'General expenses', 'expense', 'expense'],
            ['6100', 'Bank charges', 'expense', null],
        ];
        foreach ($rows as [$code, $name, $type, $role]) {
            DB::table('accounts')->insert([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'system_role' => $role,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $bankId = DB::table('accounts')->where('code', '1000')->value('id');
        DB::table('bank_accounts')->insert([
            'account_id' => $bankId,
            'name' => 'Current account',
            'institution' => null,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('document_sequences')->insert([
            ['type' => 'bill', 'prefix' => 'BILL', 'next_number' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense', 'prefix' => 'EXP', 'next_number' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_rules');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('accounts');
        DB::table('document_sequences')->whereIn('type', ['bill', 'expense'])->delete();
    }
};
