<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('profiles')
            ->where(function ($q) {
                $q->whereNull('membership_type')->orWhere('membership_type', '');
            })
            ->update(['membership_type' => 'beneficiary']);

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('membership_type', 32)->default('beneficiary')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('membership_type', 32)->nullable()->default(null)->change();
        });
    }
};
