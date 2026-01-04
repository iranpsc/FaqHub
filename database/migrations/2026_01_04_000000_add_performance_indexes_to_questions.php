<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Index for published status filtering (most common query)
            $table->index(['published', 'created_at'], 'idx_questions_published_created');
            
            // Index for category filtering
            $table->index(['category_id', 'published', 'created_at'], 'idx_questions_category_published');
            
            // Index for views sorting
            $table->index(['views', 'created_at'], 'idx_questions_views');
            
            // Index for user_id lookups
            $table->index(['user_id', 'published'], 'idx_questions_user_published');
            
            // Index for featured questions
            $table->index(['featured', 'published'], 'idx_questions_featured');
        });

        Schema::table('votes', function (Blueprint $table) {
            // Composite index for votable polymorphic relation with type
            $table->index(['votable_type', 'votable_id', 'type'], 'idx_votes_votable_type');
            
            // Index for user votes lookup
            $table->index(['user_id', 'votable_type', 'votable_id'], 'idx_votes_user_votable');
        });

        Schema::table('answers', function (Blueprint $table) {
            // Index for question answers with published status
            $table->index(['question_id', 'published'], 'idx_answers_question_published');
            
            // Index for correct answers
            $table->index(['question_id', 'is_correct'], 'idx_answers_question_correct');
        });

        Schema::table('comments', function (Blueprint $table) {
            // Index for commentable polymorphic relation with published status
            $table->index(['commentable_type', 'commentable_id', 'published'], 'idx_comments_commentable_published');
        });

        Schema::table('question_tag', function (Blueprint $table) {
            // Index for tag filtering (reverse lookup)
            $table->index(['tag_id', 'question_id'], 'idx_question_tag_reverse');
        });

        Schema::table('user_pinned_questions', function (Blueprint $table) {
            // Index for question_id lookups (already has user_id index)
            $table->index('question_id', 'idx_pinned_question_id');
        });

        Schema::table('user_featured_questions', function (Blueprint $table) {
            // Index for question_id lookups
            $table->index(['question_id', 'type'], 'idx_featured_question_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('idx_questions_published_created');
            $table->dropIndex('idx_questions_category_published');
            $table->dropIndex('idx_questions_views');
            $table->dropIndex('idx_questions_user_published');
            $table->dropIndex('idx_questions_featured');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('idx_votes_votable_type');
            $table->dropIndex('idx_votes_user_votable');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('idx_answers_question_published');
            $table->dropIndex('idx_answers_question_correct');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('idx_comments_commentable_published');
        });

        Schema::table('question_tag', function (Blueprint $table) {
            $table->dropIndex('idx_question_tag_reverse');
        });

        Schema::table('user_pinned_questions', function (Blueprint $table) {
            $table->dropIndex('idx_pinned_question_id');
        });

        Schema::table('user_featured_questions', function (Blueprint $table) {
            $table->dropIndex('idx_featured_question_type');
        });
    }
};

