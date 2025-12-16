<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class MigrateHistoricalActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:migrate
                            {--batch-size=100 : Number of records to process in each batch}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate historical data to activity logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting historical activities migration...');
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('This will create activity logs for all existing data. Do you want to proceed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $batchSize = (int) $this->option('batch-size');
        $activityLogger = new ActivityLogger();

        $stats = [
            'questions' => 0,
            'answers' => 0,
            'comments' => 0,
            'votes' => 0,
            'publishes' => 0,
            'features' => 0,
            'correctness' => 0,
        ];

        try {
            // Migrate Questions
            $this->info('📝 Migrating questions...');
            $stats['questions'] = $this->migrateQuestions($activityLogger, $batchSize);

            // Migrate Answers
            $this->info('💬 Migrating answers...');
            $stats['answers'] = $this->migrateAnswers($activityLogger, $batchSize);

            // Migrate Comments
            $this->info('💭 Migrating comments...');
            $stats['comments'] = $this->migrateComments($activityLogger, $batchSize);

            // Migrate Votes
            $this->info('👍 Migrating votes...');
            $stats['votes'] = $this->migrateVotes($activityLogger, $batchSize);

            // Migrate Publishing activities
            $this->info('📢 Migrating publishing activities...');
            $stats['publishes'] = $this->migratePublishing($activityLogger, $batchSize);

            // Migrate Featured questions
            $this->info('⭐ Migrating featured questions...');
            $stats['features'] = $this->migrateFeatured($activityLogger, $batchSize);

            // Migrate Correctness marks
            $this->info('✅ Migrating correctness marks...');
            $stats['correctness'] = $this->migrateCorrectness($activityLogger, $batchSize);

            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            $this->newLine();
            $this->table(
                ['Activity Type', 'Count'],
                [
                    ['Questions Created', $stats['questions']],
                    ['Answers Created', $stats['answers']],
                    ['Comments Created', $stats['comments']],
                    ['Votes', $stats['votes']],
                    ['Publishing', $stats['publishes']],
                    ['Featured', $stats['features']],
                    ['Correctness Marks', $stats['correctness']],
                    ['Total', array_sum($stats)],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error during migration: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Migrate questions
     */
    private function migrateQuestions(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $questions = Question::with('user', 'category')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($questions->isEmpty()) {
                break;
            }

            foreach ($questions as $question) {
                if ($question->user) {
                    $originalCreatedAt = $question->created_at;

                    $logger->logQuestionCreated($question, $question->user);

                    // Update the most recent activity log for this question to use original timestamp
                    Activity::where('subject_type', Question::class)
                        ->where('subject_id', $question->id)
                        ->where('description', 'created_question')
                        ->orderByDesc('id')
                        ->limit(1)
                        ->update(['created_at' => $originalCreatedAt]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate answers
     */
    private function migrateAnswers(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $answers = Answer::with('user', 'question')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($answers->isEmpty()) {
                break;
            }

            foreach ($answers as $answer) {
                if ($answer->user) {
                    $originalCreatedAt = $answer->created_at;

                    $logger->logAnswerCreated($answer, $answer->user);

                    // Update the most recent activity log for this answer to use original timestamp
                    Activity::where('subject_type', Answer::class)
                        ->where('subject_id', $answer->id)
                        ->where('description', 'created_answer')
                        ->orderByDesc('id')
                        ->limit(1)
                        ->update(['created_at' => $originalCreatedAt]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate comments
     */
    private function migrateComments(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $comments = Comment::with('user', 'commentable')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($comments->isEmpty()) {
                break;
            }

            foreach ($comments as $comment) {
                if ($comment->user) {
                    $originalCreatedAt = $comment->created_at;

                    $logger->logCommentCreated($comment, $comment->user);

                    // Update the most recent activity log for this comment to use original timestamp
                    Activity::where('subject_type', Comment::class)
                        ->where('subject_id', $comment->id)
                        ->where('description', 'created_comment')
                        ->orderByDesc('id')
                        ->limit(1)
                        ->update(['created_at' => $originalCreatedAt]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate votes
     */
    private function migrateVotes(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $votes = DB::table('votes')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($votes->isEmpty()) {
                break;
            }

            foreach ($votes as $vote) {
                $user = User::find($vote->user_id);
                if (!$user) {
                    continue;
                }

                // Get the votable model
                $votable = null;
                if ($vote->votable_type === Question::class) {
                    $votable = Question::find($vote->votable_id);
                } elseif ($vote->votable_type === Answer::class) {
                    $votable = Answer::find($vote->votable_id);
                } elseif ($vote->votable_type === Comment::class) {
                    $votable = Comment::find($vote->votable_id);
                }

                if ($votable) {
                    // Use last_voted_at if available, otherwise use created_at
                    $voteDate = $vote->last_voted_at ?? $vote->created_at ?? now();

                    // Create activity log
                    $activity = activity()
                        ->causedBy($user)
                        ->performedOn($votable)
                        ->withProperties([
                            'vote_type' => $vote->type,
                            'votable_type' => $vote->votable_type,
                            'votable_id' => $vote->votable_id,
                        ])
                        ->log('voted');

                    // Update created_at to original vote date
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => Carbon::parse($voteDate)]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate publishing activities
     */
    private function migratePublishing(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;

        // Migrate published questions
        $offset = 0;
        while (true) {
            $questions = Question::where('published', true)
                ->whereNotNull('published_by')
                ->with('publisher')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($questions->isEmpty()) {
                break;
            }

            foreach ($questions as $question) {
                if ($question->publisher && $question->published_at) {
                    $activity = activity()
                        ->causedBy($question->publisher)
                        ->performedOn($question)
                        ->withProperties([
                            'question_id' => $question->id,
                            'question_title' => $question->title,
                            'question_slug' => $question->slug,
                        ])
                        ->log('published_question');

                    // Update created_at to original published_at
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => $question->published_at]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        // Migrate published answers
        $offset = 0;
        while (true) {
            $answers = Answer::where('published', true)
                ->whereNotNull('published_by')
                ->with('publisher', 'question')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($answers->isEmpty()) {
                break;
            }

            foreach ($answers as $answer) {
                if ($answer->publisher && $answer->published_at) {
                    $activity = activity()
                        ->causedBy($answer->publisher)
                        ->performedOn($answer)
                        ->withProperties([
                            'question_id' => $answer->question_id,
                            'question_title' => $answer->question->title ?? null,
                            'question_slug' => $answer->question->slug ?? null,
                        ])
                        ->log('published_answer');

                    // Update created_at to original published_at
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => $answer->published_at]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        // Migrate published comments
        $offset = 0;
        while (true) {
            $comments = Comment::where('published', true)
                ->whereNotNull('published_by')
                ->with('publisher', 'commentable')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($comments->isEmpty()) {
                break;
            }

            foreach ($comments as $comment) {
                if ($comment->publisher && $comment->published_at) {
                    $question = $this->getQuestionFromCommentable($comment);

                    $activity = activity()
                        ->causedBy($comment->publisher)
                        ->performedOn($comment)
                        ->withProperties([
                            'commentable_type' => $comment->commentable_type,
                            'commentable_id' => $comment->commentable_id,
                            'question_id' => $question?->id,
                            'question_title' => $question?->title,
                            'question_slug' => $question?->slug,
                        ])
                        ->log('published_comment');

                    // Update created_at to original published_at
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => $comment->published_at]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate featured questions
     */
    private function migrateFeatured(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $featured = DB::table('user_featured_questions')
                ->where('type', 'featured')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($featured->isEmpty()) {
                break;
            }

            foreach ($featured as $feature) {
                $user = User::find($feature->user_id);
                $question = Question::find($feature->question_id);

                if ($user && $question) {
                    $featureDate = $feature->featured_at ?? $feature->created_at ?? now();

                    $activity = activity()
                        ->causedBy($user)
                        ->performedOn($question)
                        ->withProperties([
                            'title' => $question->title,
                            'slug' => $question->slug,
                            'is_featured' => true,
                        ])
                        ->log('featured_question');

                    // Update created_at to original featured date
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => Carbon::parse($featureDate)]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Migrate correctness marks
     */
    private function migrateCorrectness(ActivityLogger $logger, int $batchSize): int
    {
        $count = 0;
        $offset = 0;

        while (true) {
            $marks = DB::table('answer_correctness_marks')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($marks->isEmpty()) {
                break;
            }

            foreach ($marks as $mark) {
                $user = User::find($mark->user_id);
                $answer = Answer::with('question')->find($mark->answer_id);

                if ($user && $answer) {
                    $markDate = $mark->created_at ?? now();

                    $activity = activity()
                        ->causedBy($user)
                        ->performedOn($answer)
                        ->withProperties([
                            'question_id' => $answer->question_id,
                            'question_title' => $answer->question->title ?? null,
                            'question_slug' => $answer->question->slug ?? null,
                            'is_correct' => $mark->is_correct,
                        ])
                        ->log('marked_correct');

                    // Update created_at to original mark date
                    Activity::where('id', $activity->id)
                        ->update(['created_at' => Carbon::parse($markDate)]);

                    $count++;
                }
            }

            $offset += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        return $count;
    }

    /**
     * Get question from commentable
     */
    private function getQuestionFromCommentable(Comment $comment): ?Question
    {
        if ($comment->commentable_type === Question::class) {
            return $comment->commentable;
        }

        if ($comment->commentable_type === Answer::class) {
            return $comment->commentable->question ?? null;
        }

        return null;
    }
}

