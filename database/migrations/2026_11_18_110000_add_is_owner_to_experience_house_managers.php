<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صاحبة النظام: الوحيدة التي تستطيع حذف أعضاء الفريق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experience_house_managers', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('is_active');
        });

        DB::table('experience_house_managers')
            ->where('email', 'lamaalmushyqih@gmail.com')
            ->update(['is_owner' => true]);
    }

    public function down(): void
    {
        Schema::table('experience_house_managers', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
