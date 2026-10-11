<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefault(60);

        DB::table('program_attendance_links')->update([
            'open_minutes' => 60,
        ]);
    }

    public function down(): void
    {
        $this->setDefault(15);
    }

    private function setDefault(int $minutes): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('ALTER TABLE program_attendance_links ALTER COLUMN open_minutes SET DEFAULT '.$minutes);

            return;
        }

        // SQLite rebuilds the table to change a default, and that drop cascades
        // into attendance marks while a test transaction is open.
        if ($connection->transactionLevel() > 0) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('program_attendance_links', function (Blueprint $table) use ($minutes): void {
                $table->unsignedSmallInteger('open_minutes')->default($minutes)->change();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
