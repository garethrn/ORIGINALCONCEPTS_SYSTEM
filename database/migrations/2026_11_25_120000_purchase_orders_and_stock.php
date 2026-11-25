<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false);
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
        });

        Schema::table('bill_lines', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable()->unique();
            $table->string('status')->default('draft')->index();
            $table->date('issue_date')->nullable();
            $table->date('expected_on')->nullable();
            $table->text('notes')->nullable();
            $table->integer('subtotal_cents')->default(0);
            $table->integer('vat_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
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

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('reason');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->timestamps();
            $table->unique(['product_id', 'source_type', 'source_id', 'reason']);
        });

        $now = now();
        DB::table('document_sequences')->insert([
            'type' => 'purchase_order',
            'prefix' => 'PO',
            'next_number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'quantity_on_hand']);
        });
        DB::table('document_sequences')->where('type', 'purchase_order')->delete();
    }
};
