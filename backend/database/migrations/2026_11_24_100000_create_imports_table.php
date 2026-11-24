<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->char('file_hash', 64)->comment('SHA-256 of the uploaded file');
            $table->unsignedBigInteger('file_size');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('batch_id', 36)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // The same file can only be imported once per user, even with concurrent uploads.
            $table->unique(['user_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
