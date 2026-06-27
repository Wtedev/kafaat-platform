<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 32)->default('active')->after('is_active');
            $table->timestamp('privacy_deleted_at')->nullable()->after('account_status');
            $table->timestamp('anonymized_at')->nullable()->after('privacy_deleted_at');
        });

        DB::table('users')->where('is_active', false)->update(['account_status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['account_status', 'privacy_deleted_at', 'anonymized_at']);
        });
    }
};
