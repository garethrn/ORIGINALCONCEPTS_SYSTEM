<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('kind', 20)->default('labour');
            $table->boolean('after_hours')->default(false);
            $table->decimal('quantity', 12, 3)->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('labour_rate_cents')->nullable();
            $table->unsignedInteger('call_out_fee_cents')->nullable();
            $table->unsignedInteger('travel_rate_cents')->nullable();
        });

        foreach (['quotes', 'invoices', 'credit_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('subtotal_cents')->default(0)->change();
                $table->integer('vat_cents')->default(0)->change();
                $table->integer('total_cents')->default(0)->change();
            });
        }

        foreach (['quote_lines', 'invoice_lines', 'credit_note_lines'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('unit_price_cents')->change();
                $table->integer('net_cents')->change();
                $table->integer('vat_cents')->change();
                $table->integer('total_cents')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'after_hours', 'quantity']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['labour_rate_cents', 'call_out_fee_cents', 'travel_rate_cents']);
        });

        foreach (['quotes', 'invoices', 'credit_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('subtotal_cents')->default(0)->change();
                $table->unsignedInteger('vat_cents')->default(0)->change();
                $table->unsignedInteger('total_cents')->default(0)->change();
            });
        }

        foreach (['quote_lines', 'invoice_lines', 'credit_note_lines'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('unit_price_cents')->change();
                $table->unsignedInteger('net_cents')->change();
                $table->unsignedInteger('vat_cents')->change();
                $table->unsignedInteger('total_cents')->change();
            });
        }
    }
};
