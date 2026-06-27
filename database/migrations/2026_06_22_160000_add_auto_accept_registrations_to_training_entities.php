<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->boolean('auto_accept_registrations')->default(false)->after('capacity');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->boolean('auto_accept_registrations')->default(false)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn('auto_accept_registrations');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn('auto_accept_registrations');
        });
    }
};
