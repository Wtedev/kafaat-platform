<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->boolean('session_topics_enabled')->default(false)->after('description');
            $table->json('session_topics')->nullable()->after('session_topics_enabled');
            $table->boolean('whatsapp_groups_enabled')->default(false)->after('session_topics');
            $table->string('whatsapp_group_male', 512)->nullable()->after('whatsapp_groups_enabled');
            $table->string('whatsapp_group_female', 512)->nullable()->after('whatsapp_group_male');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn([
                'session_topics_enabled',
                'session_topics',
                'whatsapp_groups_enabled',
                'whatsapp_group_male',
                'whatsapp_group_female',
            ]);
        });
    }
};
