<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__content_draft_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_draft_id')->constrained('dashed__content_drafts')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('heading');
            $table->text('intent')->nullable();
            $table->longText('body')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->index(['content_draft_id', 'sort_order']);
        });

        if (Schema::hasColumn('dashed__content_drafts', 'h2_sections')) {
            $drafts = DB::table('dashed__content_drafts')
                ->whereNotNull('h2_sections')
                ->get(['id', 'h2_sections']);

            foreach ($drafts as $draft) {
                $sections = json_decode($draft->h2_sections, true) ?: [];
                foreach ($sections as $index => $section) {
                    DB::table('dashed__content_draft_sections')->insert([
                        'content_draft_id' => $draft->id,
                        'sort_order' => (int) ($section['order'] ?? $index),
                        'heading' => (string) ($section['heading'] ?? ''),
                        'intent' => $section['intent'] ?? null,
                        'body' => $section['body'] ?? null,
                        'error_message' => $section['error_message'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_draft_sections');
    }
};
