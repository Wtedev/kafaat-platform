<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('father_name', 100)->nullable()->after('first_name');
            $table->string('grandfather_name', 100)->nullable()->after('father_name');
            $table->string('family_name', 100)->nullable()->after('grandfather_name');

            $table->string('identity_type', 20)->nullable()->after('phone');
            $table->text('identity_number_ciphertext')->nullable()->after('identity_type');
            $table->string('identity_number_lookup_hash', 64)->nullable()->after('identity_number_ciphertext');
            $table->string('identity_number_last4', 4)->nullable()->after('identity_number_lookup_hash');
            $table->timestamp('identity_confirmed_at')->nullable()->after('identity_number_last4');
            $table->timestamp('profile_completed_at')->nullable()->after('identity_confirmed_at');

            $table->unique('identity_number_lookup_hash');
            $table->index(['identity_type', 'identity_number_last4']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['identity_number_lookup_hash']);
            $table->dropIndex(['identity_type', 'identity_number_last4']);

            $table->dropColumn([
                'first_name',
                'father_name',
                'grandfather_name',
                'family_name',
                'identity_type',
                'identity_number_ciphertext',
                'identity_number_lookup_hash',
                'identity_number_last4',
                'identity_confirmed_at',
                'profile_completed_at',
            ]);
        });
    }
};
