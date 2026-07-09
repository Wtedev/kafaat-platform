<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_photos', function (Blueprint $table) {
            $table->string('category')->nullable()->after('caption');
        });

        DB::table('media_photos')
            ->whereNotNull('album')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $photo) {
                    $category = null;

                    foreach (['الفعاليات والمبادرات', 'زيارات واستضافات', 'مرافق الجمعية'] as $candidate) {
                        if (str_starts_with((string) $photo->album, $candidate)) {
                            $category = $candidate;
                            break;
                        }
                    }

                    if ($category !== null) {
                        DB::table('media_photos')->where('id', $photo->id)->update(['category' => $category]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('media_photos', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
