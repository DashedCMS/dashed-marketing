<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__content_draft_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_draft_id')->constrained('dashed__content_drafts')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('question');
            $table->text('answer');
            $table->timestamps();

            $table->index(['content_draft_id', 'sort_order']);
        });

        if (Schema::hasColumn('dashed__content_drafts', 'faqs')) {
            $drafts = DB::table('dashed__content_drafts')
                ->whereNotNull('faqs')
                ->get(['id', 'faqs']);

            foreach ($drafts as $draft) {
                $faqs = json_decode($draft->faqs, true) ?: [];
                foreach ($faqs as $index => $faq) {
                    DB::table('dashed__content_draft_faqs')->insert([
                        'content_draft_id' => $draft->id,
                        'sort_order' => $index,
                        'question' => (string) ($faq['question'] ?? ''),
                        'answer' => (string) ($faq['answer'] ?? ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__content_draft_faqs');
    }
};
