<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QuestionFilterService
{
    /**
     * Filter questions based on request parameters
     *
     * @param Request $request
     * @return Builder
     */
    public function filter(Request $request): Builder
    {
        $query = Question::query();

        $user = $request->user();

        // Select only necessary columns from questions table
        $query->select('questions.*');

        // Optimize eager loading with specific columns
        $query->with([
            'user:id,name,email,level,avatar',
            'category:id,name,slug',
            'tags:id,name,slug'
        ]);

        // Use subquery counts for better performance (avoids separate queries)
        $query->withCount([
            'votes',
            'answers',
            'comments',
            'answers as unpublished_answers_count' => function ($query) {
                $query->where('published', false);
            },
            'comments as unpublished_comments_count' => function ($query) {
                $query->where('published', false);
            }
        ]);

        // Apply visibility filter first to reduce dataset
        $query->visible($user);

        // Apply category filter early for better index usage
        if ($request->filled('category_id')) {
            $query->where('questions.category_id', $request->category_id);
        }

        // Apply tags filter (OR logic - questions must have ANY of the selected tags)
        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            // Use whereIn with subquery for better performance
            $query->whereIn('questions.id', function ($subQuery) use ($tags) {
                $subQuery->select('question_id')
                    ->from('question_tag')
                    ->whereIn('tag_id', $tags);
            });
        }

        // Apply user-specific status joins
        $query->withUserPinStatus($user)
              ->withUserFeatureStatus($user);

        // Apply sorting
        if ($this->hasActiveFilters($request)) {
            $this->applySortingFilters($request, $query, $user);
        } else {
            $query->orderByPinStatus($user);
        }

        return $query;
    }

    /**
     * Apply sorting filters based on request parameters
     *
     * @param Request $request
     * @param Builder $query
     * @param mixed $user
     * @return void
     */
    private function applySortingFilters(Request $request, Builder $query, $user): void
    {
        // Handle filter-based parameters (unanswered, unsolved)
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'unanswered':
                    $query->whereDoesntHave('answers')
                        ->orderBy('created_at', 'desc');
                    return;
                case 'unsolved':
                    // Assuming unsolved means no accepted answer
                    $query->whereDoesntHave('answers', function ($q) {
                        $q->where('is_correct', true);
                    })->orderBy('created_at', 'desc');
                    return;
                case 'solved':
                    // Assuming solved means at least one accepted answer
                    $query->whereHas('answers', function ($q) {
                        $q->where('is_correct', true);
                    })->orderBy('created_at', 'desc');
                    return;
                case 'unpublished':
                    $query->where('questions.published', false)
                        ->orderBy('created_at', 'desc');
                    return;
            }
        }

        // Handle sort and order parameters
        if ($request->filled('sort') && $request->filled('order')) {
            $sortField = $request->sort;
            $sortOrder = $request->order;

            switch ($sortField) {
                case 'created_at':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'votes':
                    $query->orderBy('votes_count', $sortOrder);
                    break;
                case 'answers_count':
                    $query->orderBy('answers_count', $sortOrder);
                    break;
                case 'views_count':
                    $query->orderBy('views', $sortOrder);
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
            return;
        }
    }

    /**
     * Check if the request has any active filtering parameters
     *
     * @param Request $request
     * @return bool
     */
    private function hasActiveFilters(Request $request): bool
    {
        return $request->filled('category_id') ||
               $request->filled('tags') ||
               $request->filled('filter') ||
               $request->filled('sort') ||
               $request->has('newest') ||
               $request->has('oldest') ||
               $request->has('most_votes') ||
               $request->has('most_answers') ||
               $request->has('most_views') ||
               $request->has('unanswered') ||
               $request->has('unsolved') ||
               $request->has('unpublished');
    }

    /**
     * Get paginated questions with filters applied
     *
     * @param Request $request
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginatedQuestions(Request $request, int $perPage = 10)
    {
        $query = $this->filter($request);

        // Use cursor pagination for better performance on large datasets
        // But keep regular pagination for compatibility
        return $query->paginate($perPage);
    }

    /**
     * Generate a cache key based on request parameters
     *
     * @param Request $request
     * @param int $perPage
     * @return string
     */
    private function getCacheKey(Request $request, int $perPage): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $page = $request->get('page', 1);

        $params = [
            'user' => $userId,
            'page' => $page,
            'per_page' => $perPage,
            'category_id' => $request->get('category_id'),
            'tags' => $request->get('tags'),
            'filter' => $request->get('filter'),
            'sort' => $request->get('sort'),
            'order' => $request->get('order'),
        ];

        return 'questions:list:' . md5(json_encode($params));
    }

    /**
     * Clear questions list cache
     * Call this when questions are created, updated, or deleted
     *
     * @return void
     */
    public function clearCache(): void
    {
        // Clear all questions list cache
        Cache::tags(['questions'])->flush();
    }
}
