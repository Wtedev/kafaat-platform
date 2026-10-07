<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('issued_at');
            $table->text('override_reason')->nullable()->after('pdf_error');
            $table->foreignId('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('overridden_by');
            $table->text('revoke_reason')->nullable()->after('revoked_at');
        });

        DB::statement('CREATE UNIQUE INDEX certificates_one_active_per_owner ON certificates (user_id, certificateable_type, certificateable_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS certificates_one_active_per_owner');

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by');
            $table->dropColumn(['emailed_at', 'override_reason', 'revoked_at', 'revoke_reason']);
        });
    }
};
