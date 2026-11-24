<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Deleting an import undoes it.
            $table->foreignId('import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number')->nullable()->comment('Line in the source file (header = 1)');
            $table->date('occurred_on');
            $table->string('description');
            $table->unsignedBigInteger('amount_cents')->comment('Always positive; the sign comes from type');
            $table->string('type', 16)->comment('income | expense');
            $table->timestamps();

            // Idempotency key: a chunk that runs twice cannot insert the same line twice.
            $table->unique(['import_id', 'line_number']);
            // Paginated list, newest first, with a stable tie-breaker.
            $table->index(['user_id', 'occurred_on', 'id']);
            // Covering index: the dashboard SUM(CASE ...) reads only the index.
            $table->index(['user_id', 'type', 'amount_cents']);
        });

        // The database itself refuses anything but the two types and non-positive amounts.
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ('income', 'expense'))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_check CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
