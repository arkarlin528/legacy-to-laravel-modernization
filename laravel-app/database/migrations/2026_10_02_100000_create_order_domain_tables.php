<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The modernized schema. Compared with the legacy tables (see ../legacy-api/db/01-schema.sql):
 *  - plain snake_case names instead of tblOrder / CustNm / Curr
 *  - timestamps stored in UTC (timestamptz) instead of local Bangkok time
 *  - booleans instead of 'Y'/'N' chars, status as a readable string instead of 1..4
 *  - numeric(12,2) instead of money, real foreign keys and a sequence for order numbers
 * IDs are kept identical to the legacy IDs so existing clients' links keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->char('country', 2);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->timestampTz('ordered_at', 6);
            $table->string('status', 20)->index();
            $table->char('origin', 5);
            $table->char('destination', 5);
            $table->text('remarks')->nullable();
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            $table->timestampsTz(6);

            $table->index(['customer_id', 'status']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);

            $table->unique(['order_id', 'line_no']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->timestampTz('issued_at', 6);
            $table->timestampTz('due_at', 6);
            $table->decimal('amount', 12, 2);
            // Kept alongside paid_at: legacy data has invoices marked paid without a date (see migration report).
            $table->boolean('is_paid')->default(false);
            $table->timestampTz('paid_at', 6)->nullable();
            $table->timestampsTz(6);
        });

        // Replaces the legacy tblSequence counter table. The data migrator moves it to the legacy value.
        // (migrate:fresh drops tables but not standalone sequences, hence the DROP first.)
        DB::statement('DROP SEQUENCE IF EXISTS order_number_seq');
        DB::statement('CREATE SEQUENCE order_number_seq START 1');

        // Status values are a closed set; let the database enforce it too.
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('open','confirmed','shipped','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS order_number_seq');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
