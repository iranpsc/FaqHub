<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'totalQuestions' => Question::published()->count(),
                'totalAnswers' => Answer::published()->count(),
                'totalUsers' => User::count(),
                'solvedQuestions' => Question::whereHas('answers', function ($query) {
                    $query->where('is_correct', true);
                })->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'آمار با موفقیت دریافت شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت آمار',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommended questions (random selection)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendedQuestions(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 15);

            $questions = Question::with(['user', 'tags', 'category'])
                ->withCount(['answers', 'votes', 'comments'])
                ->where('published', true)
                ->inRandomOrder()
                ->limit($limit)
                ->get()
                ->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'title' => $question->title,
                        'slug' => $question->slug,
                        'created_at' => $question->created_at,
                        'answers_count' => $question->answers_count,
                        'votes_count' => $question->votes_count,
                        'views_count' => $question->views ?? 0,
                        'user' => [
                            'id' => $question->user?->id,
                            'name' => $question->user?->name,
                        ],
                        'category' => $question->category ? [
                            'id' => $question->category->id,
                            'name' => $question->category->name,
                        ] : null,
                        'tags' => $question->tags->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'name' => $tag->name,
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $questions,
                'message' => 'سوالات پیشنهادی با موفقیت دریافت شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت سوالات پیشنهادی',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular questions based on views and period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popularQuestions(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 15);
            $period = $request->get('period', 'all'); // week, month, year, all

            $query = Question::with(['user', 'tags', 'category'])
                ->withCount(['answers', 'votes', 'comments'])
                ->where('published', true);

            // Apply date filter based on period
            switch ($period) {
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
                case 'year':
                    $query->where('created_at', '>=', now()->subYear());
                    break;
                case 'all':
                default:
                    // No date filter for 'all'
                    break;
            }

            $questions = $query
                ->orderByDesc(DB::raw('COALESCE(views, 0)')) // Order by views (handle null values)
                ->orderByDesc('votes_count') // Secondary sort by votes
                ->orderByDesc('answers_count') // Tertiary sort by answers
                ->limit($limit)
                ->get()
                ->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'title' => $question->title,
                        'slug' => $question->slug,
                        'created_at' => $question->created_at,
                        'answers_count' => $question->answers_count,
                        'votes_count' => $question->votes_count,
                        'views_count' => $question->views ?? 0,
                        'user' => [
                            'id' => $question->user?->id,
                            'name' => $question->user?->name,
                        ],
                        'category' => $question->category ? [
                            'id' => $question->category->id,
                            'name' => $question->category->name,
                        ] : null,
                        'tags' => $question->tags->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'name' => $tag->name,
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $questions,
                'message' => 'سوالات محبوب با موفقیت دریافت شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت سوالات محبوب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get most active users based on score and recent activity
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activeUsers(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 5);

            $users = User::withCount(['questions', 'answers', 'comments'])
                ->orderByDesc('score')
                ->orderByDesc('questions_count')
                ->orderByDesc('answers_count')
                ->limit($limit)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'image' => $user->image,
                        'score' => $user->score ?? 0,
                        'questions_count' => $user->questions_count,
                        'answers_count' => $user->answers_count,
                        'comments_count' => $user->comments_count,
                        'total_activity' => $user->questions_count + $user->answers_count + $user->comments_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'کاربران فعال با موفقیت دریافت شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت کاربران فعال',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity feed showing activities with chronological pagination
     *
     * Query Parameters:
     * - limit (int): Number of activities to return (default: 30)
     * - offset (int): Number of activities to skip (default: 0)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activity(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 30);
            $offset = (int) $request->get('offset', 0);

            $activityService = new ActivityService();
            $result = $activityService->getActivities($limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $result['activities'],
                'grouped_data' => $result['grouped_activities'],
                'pagination' => $result['pagination'],
                'message' => 'فعالیت‌ها با موفقیت دریافت شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت فعالیت‌ها',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
