<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['news_id', 'is_primary']);
            $table->index(['news_id', 'sort_order']);
        });

        if (! Schema::hasTable('news')) {
            return;
        }

        DB::table('news')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->each(function (object $news): void {
                DB::table('news_images')->insert([
                    'news_id' => $news->id,
                    'path' => $news->image,
                    'is_primary' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_images');
    }
};
