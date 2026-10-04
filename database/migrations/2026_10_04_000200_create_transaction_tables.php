<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional data: purchases, batches, stock movements, sales (invoices),
 * returns, payments and expenses.
 *
 * Completed transactions are never deleted; they are cancelled or reversed
 * through compensating records so the history stays auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 40);
            $table->string('supplier_invoice_no', 80)->nullable();
            $table->date('purchase_date');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 20)->default('received');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'reference_no']);
            $table->index(['pharmacy_id', 'purchase_date']);
            $table->index(['pharmacy_id', 'status']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date');
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->unsignedInteger('quantity_received');
            $table->integer('quantity');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['medicine_id', 'expiry_date']);
            $table->index(['pharmacy_id', 'expiry_date']);
            $table->index(['pharmacy_id', 'batch_number']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 40);
            $table->timestamp('sold_at');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('returned_amount', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('paid');
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'invoice_number']);
            $table->index(['pharmacy_id', 'sold_at']);
            $table->index(['pharmacy_id', 'payment_status']);
            $table->index(['customer_id', 'sold_at']);
            $table->index(['user_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots so the invoice stays correct if master data changes later.
            $table->string('medicine_name', 200);
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index('medicine_id');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('return_number', 40);
            $table->timestamp('returned_at');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            // Portion of the total paid back in money; the rest reduced the customer's due.
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->string('reason', 500);
            $table->timestamps();

            $table->unique(['pharmacy_id', 'return_number']);
            $table->index(['pharmacy_id', 'returned_at']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->boolean('restocked')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('direction', 3); // in | out
            $table->string('type', 30);     // sale, customer_payment, refund, supplier_payment
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at');
            $table->string('reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'paid_at']);
            $table->index(['pharmacy_id', 'type']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity'); // signed: + in, - out
            $table->integer('balance_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medicine_id', 'created_at']);
            $table->index(['pharmacy_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('description', 500);
            $table->string('reference', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacy_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('purchases');
    }
};
