<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_house_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 32)->nullable()->unique();

            // بيانات الجمعية
            $table->string('organization_name');
            $table->string('license_number', 50);
            $table->string('region', 50);
            $table->string('city', 100);
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('staff_size', 20)->nullable();

            // ممثل الجمعية
            $table->string('contact_name');
            $table->string('contact_role');
            $table->string('contact_phone', 20);
            $table->string('contact_email');

            // الاحتياج
            $table->json('departments');
            $table->string('other_department')->nullable();
            $table->json('support_types');
            $table->text('challenge');
            $table->text('expected_outcome');
            $table->string('priority', 20);
            $table->string('delivery_mode', 20);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            // المتابعة الداخلية
            $table->string('status', 20)->default('new')->index();
            $table->text('staff_notes')->nullable();
            $table->string('submitted_ip', 45)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_house_requests');
    }
};
