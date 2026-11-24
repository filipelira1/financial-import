<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('raw')->comment('The rejected line as read from the file');
            $table->json('errors')->comment('List of reasons the line was rejected');
            $table->timestamp('created_at')->nullable();

            $table->unique(['import_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_failures');
    }
};
