<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_page_hits', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status');
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['status', 'day']);
            $table->index('status');
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_page_hits');
    }
};
