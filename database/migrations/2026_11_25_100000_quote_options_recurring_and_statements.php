<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->integer('total_cents')->default(0);
            $table->timestamps();
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->foreignId('quote_option_id')->nullable()->constrained('quote_options')->nullOnDelete();
            $table->boolean('is_optional')->default(false);
            $table->boolean('option_accepted')->default(false);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('selected_option_id')->nullable()->constrained('quote_options')->nullOnDelete();
        });

        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('frequency');
            $table->date('next_on');
            $table->date('ends_on')->nullable();
            $table->boolean('auto_issue')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->integer('unit_price_cents');
            $table->boolean('price_includes_vat');
            $table->string('vat_code');
            $table->unsignedSmallInteger('vat_basis_points');
            $table->integer('net_cents');
            $table->integer('vat_cents');
            $table->integer('total_cents');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_schedule_lines');
        Schema::dropIfExists('recurring_schedules');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_option_id');
        });
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_option_id');
            $table->dropColumn(['is_optional', 'option_accepted']);
        });
        Schema::dropIfExists('quote_options');
    }
};
