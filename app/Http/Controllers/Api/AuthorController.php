<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\QuestionResource;

class AuthorController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth.optional');
    }

    /**
     * Get paginated list of authors/users with their activity statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'score'); // score, questions_count, answers_count, name, created_at
            $sortOrder = $request->get('sort_order', 'desc');
            $search = $request->get('search');

            $query = User::withCount(['questions', 'answers', 'comments'])
                ->with(['questions' => function ($query) {
                    $query->published()->latest()->limit(3);
                }]);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            switch ($sortBy) {
                case 'questions_count':
                    $query->orderBy('questions_count', $sortOrder);
                    break;
                case 'answers_count':
                    $query->orderBy('answers_count', $sortOrder);
                    break;
                case 'name':
                    $query->orderBy('name', $sortOrder);
                    break;
                case 'created_at':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'score':
                    $query->orderBy('score', $sortOrder);
                    break;
                default:
                    $query->orderBy('score', $sortOrder);
                    break;
            }

            // Add secondary sorting by created_at for consistency
            if ($sortBy !== 'created_at') {
                $query->orderBy('created_at', 'desc');
            }

            $users = $query->paginate($perPage);

            $formattedUsers = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'score' => $user->score ?? 0,
                    'level' => $user->level ?? 1,
                    'role' => $user->role,
                    'image_url' => $user->image_url,
                    'questions_count' => $user->questions_count,
                    'answers_count' => $user->answers_count,
                    'comments_count' => $user->comments_count,
                    'total_activity' => $user->questions_count + $user->answers_count + $user->comments_count,
                    'created_at' => $user->created_at,
                    'recent_questions' => $user->questions->map(function ($question) {
                        return [
                            'id' => $question->id,
                            'title' => $question->title,
                            'slug' => $question->slug,
                            'created_at' => $question->created_at,
                        ];
                    }),
                ];
            });

            return response()->json([
                'data' => $formattedUsers,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'links' => [
                    'first' => $users->url(1),
                    'last' => $users->url($users->lastPage()),
                    'prev' => $users->previousPageUrl(),
                    'next' => $users->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست نویسندگان',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single author details with their activity
     *
     * @param User $author
     * @return JsonResponse
     */
    public function show(User $author): JsonResponse
    {
        try {
            $author->loadCount(['questions', 'answers', 'comments']);

            $formattedUser = [
                'id' => $author->id,
                'username' => $author->username,
                'name' => $author->name,
                'email' => $author->email,
                'image_url' => $author->image_url,
                'score' => $author->score ?? 0,
                'level' => $author->level ?? 1,
                'role' => $author->role,
                'questions_count' => $author->questions_count,
                'answers_count' => $author->answers_count,
                'comments_count' => $author->comments_count,
                'created_at' => $author->created_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedUser,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'نویسنده یافت نشد.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get paginated questions for a specific author
     */
    public function questions(Request $request, User $user)
    {
        try {
            $perPage = (int) $request->get('per_page', 10);

            $questions = \App\Models\Question::query()
                ->where('user_id', $user->id)
                ->with(['user', 'category', 'tags'])
                ->withCount(['votes', 'answers'])
                ->published()
                ->paginate($perPage);

            return QuestionResource::collection($questions);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت سوالات نویسنده',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
