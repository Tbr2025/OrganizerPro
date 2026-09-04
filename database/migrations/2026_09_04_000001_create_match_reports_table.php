<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CricHeroes match-report PDF an organizer uploads, and the blog post drafted from it.
 *
 * Kept in its own table rather than on `posts.meta` because the PDF is uploaded BEFORE any post
 * exists — you upload, read what was extracted, then generate — and because re-generating has to
 * reuse the same source without asking for the file again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();

            // Private disk path. A match report is not public until it is a published post.
            $table->string('pdf_path')->nullable();
            $table->string('pdf_name')->nullable();
            $table->unsignedInteger('pdf_size')->nullable();

            // What pdftotext read out. Stored so a regenerate needs no re-upload, and so a bad
            // draft can be diagnosed against what the model was actually given.
            $table->longText('extracted_text')->nullable();

            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('model')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One report per match: uploading again replaces the source rather than piling up.
            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_reports');
    }
};
