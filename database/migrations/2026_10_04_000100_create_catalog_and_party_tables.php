<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data: categories, manufacturers, suppliers, customers and medicines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacy_id', 'name']);
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('country', 80)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacy_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('tax_number', 60)->nullable();
            // Positive balance = amount the pharmacy owes this supplier.
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacy_id', 'name']);
            $table->index(['pharmacy_id', 'phone']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->date('date_of_birth')->nullable();
            // Positive balance = amount this customer owes the pharmacy.
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacy_id', 'name']);
            $table->index(['pharmacy_id', 'phone']);
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->string('generic_name', 180)->nullable();
            $table->string('strength', 60)->nullable();
            $table->string('sku', 60);
            $table->string('barcode', 64)->nullable();
            $table->string('unit', 30);
            // Default prices; batches can carry their own.
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedInteger('min_stock_level')->default(0);
            // Denormalised cache of SUM(batches.quantity); written only by InventoryService.
            $table->integer('stock_quantity')->default(0);
            $table->boolean('requires_prescription')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pharmacy_id', 'sku']);
            $table->unique(['pharmacy_id', 'barcode']);
            $table->index(['pharmacy_id', 'name']);
            $table->index(['pharmacy_id', 'generic_name']);
            $table->index(['pharmacy_id', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('categories');
    }
};
