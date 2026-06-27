<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retention_policies', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('status', 32)->default('draft')->after('action');
            $table->timestamp('activated_at')->nullable()->after('effective_at');
            $table->timestamp('last_previewed_at')->nullable()->after('activated_at');
            $table->unsignedInteger('last_preview_count')->nullable()->after('last_previewed_at');
            $table->boolean('requires_manual_approval')->default(false)->after('last_preview_count');
            $table->foreignId('activated_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
        });

        if (Schema::hasColumn('retention_policies', 'trigger_event')) {
            Schema::table('retention_policies', function (Blueprint $table): void {
                $table->renameColumn('trigger_event', 'trigger_type');
            });
        }

        DB::table('retention_policies')->whereNull('uuid')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('retention_policies')->where('id', $row->id)->update([
                    'uuid' => (string) Str::uuid(),
                    'status' => ($row->enabled ?? false) ? 'active' : 'inactive',
                ]);
            }
        });

        Schema::table('retention_policies', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
            $table->dropIndex(['resource_type', 'enabled']);
            $table->dropColumn('enabled');
            $table->index(['resource_type', 'status']);
        });

        Schema::table('retention_exceptions', function (Blueprint $table): void {
            $table->string('scope', 32)->default('single_resource')->after('user_id');
            $table->unsignedBigInteger('resource_id')->nullable()->change();
            $table->timestamp('review_at')->nullable()->after('ends_at');
            $table->string('status', 32)->default('active')->after('review_at');
        });

        DB::table('retention_exceptions')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $status = 'active';
                if ($row->revoked_at !== null) {
                    $status = 'revoked';
                } elseif ($row->ends_at !== null && $row->ends_at < now()->toDateTimeString()) {
                    $status = 'expired';
                }

                DB::table('retention_exceptions')->where('id', $row->id)->update([
                    'scope' => 'single_resource',
                    'status' => $status,
                ]);
            }
        });

        Schema::create('retention_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('retention_policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->string('resource_type', 64)->nullable();
            $table->string('mode', 16);
            $table->string('status', 32);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cutoff_at');
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamps();

            $table->index(['retention_policy_id', 'mode', 'status']);
            $table->index(['resource_type', 'status']);
        });

        Schema::create('retention_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retention_run_id')->constrained('retention_runs')->cascadeOnDelete();
            $table->string('resource_type', 64);
            $table->string('resource_identifier', 128);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('action', 32);
            $table->string('status', 32);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['retention_run_id', 'status']);
            $table->unique(['retention_run_id', 'resource_type', 'resource_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_run_items');
        Schema::dropIfExists('retention_runs');

        Schema::table('retention_exceptions', function (Blueprint $table): void {
            $table->dropColumn(['scope', 'review_at', 'status']);
        });

        Schema::table('retention_policies', function (Blueprint $table): void {
            $table->boolean('enabled')->default(false);
        });

        DB::table('retention_policies')->update([
            'enabled' => DB::raw("status = 'active'"),
        ]);

        Schema::table('retention_policies', function (Blueprint $table): void {
            $table->dropForeign(['activated_by']);
            $table->dropColumn([
                'uuid',
                'status',
                'activated_at',
                'last_previewed_at',
                'last_preview_count',
                'requires_manual_approval',
                'activated_by',
            ]);
            $table->renameColumn('trigger_type', 'trigger_event');
            $table->dropIndex(['resource_type', 'status']);
            $table->index(['resource_type', 'enabled']);
        });
    }
};
