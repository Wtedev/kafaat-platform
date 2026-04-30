<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('certificateable_type');
            $table->unsignedBigInteger('certificateable_id');
            $table->string('certificate_number')->unique();
            $table->string('verification_code')->unique();
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            // Composite index for polymorphic lookups
            $table->index(['certificateable_type', 'certificateable_id'], 'certificates_certificateable_index');
            // Fast lookup by user
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
