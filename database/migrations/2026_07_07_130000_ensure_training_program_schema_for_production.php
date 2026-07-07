<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هجرة آمنة للإنتاج: تضيف أعمدة البرنامج الجديدة إن فاتت هجرات سابقة (تفادي 500 عند إنشاء برنامج).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                if (! Schema::hasColumn('training_programs', 'delivery_mode')) {
                    $table->string('delivery_mode', 32)->nullable();
                }
                if (! Schema::hasColumn('training_programs', 'venue')) {
                    $table->string('venue', 255)->nullable();
                }
                if (! Schema::hasColumn('training_programs', 'competency_track')) {
                    $table->string('competency_track', 32)->nullable();
                }
            });
        }

        if (Schema::hasTable('learning_paths') && ! Schema::hasColumn('learning_paths', 'competency_track')) {
            Schema::table('learning_paths', function (Blueprint $table): void {
                $table->string('competency_track', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        // لا حذف — قد تكون الأعمدة من هجرات سابقة.
    }
};
