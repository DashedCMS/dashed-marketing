<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__form_flow_enrollments')) {
            return;
        }

        Schema::create('dashed__form_flow_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('flow_id');
            $table->string('email')->index();
            $table->string('locale', 5)->default('nl');
            $table->string('site_id')->nullable();
            $table->json('sent_steps')->nullable();
            $table->timestamp('next_mail_at')->nullable()->index();
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('unenrolled_at')->nullable();
            $table->timestamps();

            // The unique constraint is load-bearing: it prevents duplicate
            // enrolments for the same submitter on the same (form, flow) pair.
            $table->unique(['form_id', 'flow_id', 'email'], 'form_flow_email_unique');
            $table->index('flow_id');
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__form_flow_enrollments');
    }
};
