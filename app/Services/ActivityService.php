<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use Carbon\Carbon;

class ActivityService
{
    /**
     * Generate activity report for a specific period with selective loading
     *
     * Returns most recent activities for questions, answers and comments in the chosen time period.
     * Only returns published resources, sorted descending by created_at.
     *
     * @param int $months Number of months to generate report for
     * @param int $offset Number of months to offset from current date
     * @param array $limits Activity limits per type per month
     * @return array
     */
    public function generateActivityReport(int $months = 3, int $offset = 0, array $limits = []): array
    {
        $defaultLimits = [
            'questions' => 10,
            'answers' => 8,
            'comments' => 5
        ];

        $limits = array_merge($defaultLimits, $limits);
        $months = max(1, $months);
        $offset = max(0, $offset);

        $now = Carbon::now();
        $endDate = $offset === 0
            ? $now->copy()
            : $now->copy()->subMonths($offset)->endOfMonth();
        $startDate = $now->copy()->subMonths($offset + $months - 1)->startOfMonth();

        $periodStart = $startDate->copy();
        $periodEnd = $endDate->copy();

        $allActivities = collect();
        $groupedActivities = [];

        // Generate activities for each month in the period (newest to oldest)
        $currentMonth = $periodEnd->copy()->startOfMonth();
        $iterations = 0;
        while ($currentMonth->gte($periodStart) && $iterations < $months) {
            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth();

            if ($monthStart->lt($periodStart)) {
                $monthStart = $periodStart->copy();
            }

            if ($monthEnd->gt($periodEnd)) {
                $monthEnd = $periodEnd->copy();
            }

            $monthActivities = $this->getSelectiveActivitiesForPeriod(
                $monthStart,
                $monthEnd,
                $limits
            );

            if ($monthActivities->isNotEmpty()) {
                $monthName = $this->getPersianMonth($monthStart);
                // Sort month activities by created_at descending before storing
                $sortedMonthActivities = $monthActivities->sortByDesc('created_at')->values();
                $groupedActivities[$monthName] = $sortedMonthActivities->all();
                $allActivities = $allActivities->merge($sortedMonthActivities);
            }

            $currentMonth = $currentMonth->subMonth()->startOfMonth();
            $iterations++;
        }

        // Sort all activities by created_at descending (most recent first)
        $allActivities = $allActivities->sortByDesc('created_at')->values();

        return [
            'activities' => $allActivities,
            'grouped_activities' => $groupedActivities,
            'period' => [
                'start_date' => jdate($periodStart)->format('Y/m/d'),
                'end_date' => jdate($periodEnd)->format('Y/m/d'),
                'months' => $months,
                'offset' => $offset
            ],
            'limits' => $limits
        ];
    }

    /**
     * Get selective activities for a specific period with performance optimization
     *
     * Fetches most recent published questions, answers and comments within the date range.
     * All resources are filtered to only include published items and sorted by created_at descending.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $limits
     * @return \Illuminate\Support\Collection
     */
    private function getSelectiveActivitiesForPeriod($startDate, $endDate, array $limits): \Illuminate\Support\Collection
    {
        $activities = collect();

        // Get most recent published questions for the period
        if ($limits['questions'] > 0) {
            $questions = Question::select(['id', 'title', 'slug', 'user_id', 'category_id', 'created_at', 'published_at'])
                ->with(['user:id,name,image', 'category:id,name'])
                ->published() // Only published questions
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderByDesc('created_at') // Sort by created_at descending
                ->limit($limits['questions'])
                ->get()
                ->map(function ($question) {
                    return [
                        'id' => 'question_' . $question->id,
                        'type' => 'question',
                        'user_name' => $question->user->name,
                        'user_id' => $question->user->id,
                        'user_image' => $question->user->image_url,
                        'title' => $question->title,
                        'slug' => $question->slug,
                        'question_id' => $question->id,
                        'category_name' => $question->category->name ?? null,
                        'description' => "کاربر '{$question->user->name}' سوال جدیدی با عنوان '{$question->title}' پرسید",
                        'created_at' => $question->created_at,
                        'url' => "/questions/{$question->slug}",
                        'month' => $this->getPersianMonth($question->created_at)
                    ];
                });

            $activities = $activities->merge($questions);
        }

        // Get most recent published answers for the period
        if ($limits['answers'] > 0) {
            $answers = Answer::select(['id', 'question_id', 'user_id', 'is_correct', 'created_at', 'published_at'])
                ->with(['user:id,name,image', 'question:id,title,slug'])
                ->published() // Only published answers
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderByDesc('created_at') // Sort by created_at descending
                ->limit($limits['answers'])
                ->get()
                ->map(function ($answer) {
                    return [
                        'id' => 'answer_' . $answer->id,
                        'type' => 'answer',
                        'user_name' => $answer->user->name,
                        'user_id' => $answer->user->id,
                        'user_image' => $answer->user->image_url,
                        'title' => $answer->question->title,
                        'question_id' => $answer->question->id,
                        'description' => "کاربر '{$answer->user->name}' به سوال '{$answer->question->title}' پاسخ داد",
                        'created_at' => $answer->created_at,
                        'url' => "/questions/{$answer->question->slug}",
                        'is_correct' => $answer->is_correct,
                        'month' => $this->getPersianMonth($answer->created_at)
                    ];
                });

            $activities = $activities->merge($answers);
        }

        // Get most recent published comments for the period
        if ($limits['comments'] > 0) {
            $comments = Comment::query()
                ->select([
                    'comments.id',
                    'comments.commentable_type',
                    'comments.commentable_id',
                    'comments.created_at',
                    'comments.published_at',
                    'users.name',
                    'users.id as user_id',
                    'users.image'
                ])
                ->join('users', 'comments.user_id', '=', 'users.id')
                ->published() // Only published comments
                ->whereBetween('comments.created_at', [$startDate, $endDate])
                ->orderByDesc('comments.created_at') // Sort by created_at descending
                ->limit($limits['comments'] * 2) // Get more to account for filtering
                ->get()
                ->map(function ($comment) {
                    $title = '';
                    $questionSlug = null;

                    if ($comment->commentable_type === 'App\Models\Question') {
                        $question = Question::select(['id', 'title', 'slug'])->find($comment->commentable_id);
                        $title = $question ? $question->title : 'سوال حذف شده';
                        $questionSlug = $question ? $question->slug : null;
                    } elseif ($comment->commentable_type === 'App\Models\Answer') {
                        $answer = Answer::select(['id'])->with('question:id,title,slug')->find($comment->commentable_id);
                        if ($answer && $answer->question) {
                            $title = $answer->question->title;
                            $questionSlug = $answer->question->slug;
                        } else {
                            $title = 'پاسخ حذف شده';
                            $questionSlug = null;
                        }
                    }

                    return [
                        'id' => 'comment_' . $comment->id,
                        'type' => 'comment',
                        'user_name' => $comment->name,
                        'user_id' => $comment->user_id,
                        'user_image' => $comment->image ? asset('storage/' . $comment->image) : null,
                        'title' => $title,
                        'question_slug' => $questionSlug,
                        'description' => "کاربر '{$comment->name}' نظری در '{$title}' ثبت کرد",
                        'created_at' => $comment->created_at,
                        'url' => $questionSlug ? "/questions/{$questionSlug}" : null,
                        'month' => $this->getPersianMonth($comment->created_at)
                    ];
                })
                ->filter(function ($comment) {
                    // Only include comments that have valid URLs (not deleted content)
                    return $comment['url'] !== null;
                })
                ->take($limits['comments']); // Limit to the desired number after filtering

            $activities = $activities->merge($comments);
        }

        // Return activities sorted by created_at descending (most recent first)
        return $activities->sortByDesc('created_at')->values();
    }

    /**
     * Get Persian month name with year from date
     *
     * @param string $date
     * @return string
     */
    private function getPersianMonth($date): string
    {
        $carbon = Carbon::parse($date);

        return jdate($carbon)->format('F Y');
    }

    /**
     * Check if there are more published activities available beyond the given offset
     *
     * Checks for published questions, answers, or comments with created_at before the offset date.
     *
     * @param int $offset
     * @return bool
     */
    public function hasMoreActivities(int $offset): bool
    {
        // Check if there are any published activities older than the current offset
        $checkDate = Carbon::now()->subMonths($offset)->startOfMonth();

        // Check for any published activities (questions, answers, or comments) before this date
        $hasQuestions = Question::published()
            ->where('created_at', '<', $checkDate)
            ->exists();

        $hasAnswers = Answer::published()
            ->where('created_at', '<', $checkDate)
            ->exists();

        $hasComments = Comment::published()
            ->where('created_at', '<', $checkDate)
            ->exists();

        return $hasQuestions || $hasAnswers || $hasComments;
    }

    /**
     * Get activity statistics for a period
     *
     * Returns counts of published questions, answers and comments within the time period.
     *
     * @param int $months
     * @param int $offset
     * @return array
     */
    public function getActivityStats(int $months = 3, int $offset = 0): array
    {
        $endDate = Carbon::now()->subMonths($offset);
        $startDate = $endDate->copy()->subMonths($months);

        $stats = [
            'total_questions' => Question::published()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'total_answers' => Answer::published()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'total_comments' => Comment::published()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'months' => $months,
                'offset' => $offset
            ]
        ];

        return $stats;
    }
}
