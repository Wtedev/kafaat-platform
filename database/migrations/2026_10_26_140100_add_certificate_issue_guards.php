<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('certificates')
            ->select('user_id', 'certificateable_type', 'certificateable_id')
            ->groupBy('user_id', 'certificateable_type', 'certificateable_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Refusing to add the certificate uniqueness index because duplicate certificates exist for the same beneficiary and activity.'
            );
        }

        Schema::table('certificates', function (Blueprint $table): void {
            $table->timestamp('emailed_at')->nullable();
            $table->unique(
                ['user_id', 'certificateable_type', 'certificateable_id'],
                'certificates_user_certificateable_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropUnique('certificates_user_certificateable_unique');
            $table->dropColumn('emailed_at');
        });
    }
};
