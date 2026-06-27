<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_export_files', function (Blueprint $table): void {
            $table->string('failure_code', 64)->nullable()->after('status');
            $table->timestamp('first_downloaded_at')->nullable()->after('expires_at');
            $table->timestamp('last_downloaded_at')->nullable()->after('first_downloaded_at');
        });

        if (Schema::hasColumn('privacy_export_files', 'downloaded_at')) {
            DB::table('privacy_export_files')
                ->whereNotNull('downloaded_at')
                ->update([
                    'first_downloaded_at' => DB::raw('downloaded_at'),
                    'last_downloaded_at' => DB::raw('downloaded_at'),
                ]);

            Schema::table('privacy_export_files', function (Blueprint $table): void {
                $table->dropColumn('downloaded_at');
            });
        }

        Schema::table('privacy_export_files', function (Blueprint $table): void {
            $table->unique('privacy_request_id');
            $table->index('privacy_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_export_files', function (Blueprint $table): void {
            $table->dropUnique(['privacy_request_id']);
            $table->dropIndex(['privacy_request_id']);
        });

        Schema::table('privacy_export_files', function (Blueprint $table): void {
            $table->timestamp('downloaded_at')->nullable()->after('expires_at');
        });

        DB::table('privacy_export_files')
            ->whereNotNull('first_downloaded_at')
            ->update([
                'downloaded_at' => DB::raw('first_downloaded_at'),
            ]);

        Schema::table('privacy_export_files', function (Blueprint $table): void {
            $table->dropColumn(['failure_code', 'first_downloaded_at', 'last_downloaded_at']);
        });
    }
};
