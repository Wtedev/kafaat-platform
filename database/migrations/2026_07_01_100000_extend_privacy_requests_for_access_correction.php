<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->string('correction_field_code', 64)->nullable()->after('request_details');
            $table->json('access_response')->nullable()->after('correction_field_code');
            $table->text('user_visible_response')->nullable()->after('access_response');
        });

        Schema::create('privacy_correction_payloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('privacy_request_id')->constrained()->restrictOnDelete();
            $table->string('field_code', 64);
            $table->text('encrypted_value');
            $table->string('value_lookup_hash', 128)->nullable();
            $table->string('value_last4', 8)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique('privacy_request_id');
            $table->index(['field_code', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_correction_payloads');

        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->dropColumn(['correction_field_code', 'access_response', 'user_visible_response']);
        });
    }
};
