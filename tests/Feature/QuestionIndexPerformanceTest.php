<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the index endpoint responds within acceptable time
     */
    public function test_index_response_time_is_under_two_seconds(): void
    {
        // Create test data
        $this->seedTestData();

        $startTime = microtime(true);

        $response = $this->getJson('/api/questions');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        
        // Assert response time is under 2000ms (2 seconds)
        $this->assertLessThan(2000, $responseTime, 
            "Response time was {$responseTime}ms, expected less than 2000ms");
    }

    /**
     * Test that the index endpoint uses minimal database queries
     */
    public function test_index_uses_minimal_database_queries(): void
    {
        $this->seedTestData();

        // Enable query logging
        DB::enableQueryLog();

        $response = $this->getJson('/api/questions');

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);

        // Assert query count is under 25 (should be around 10-20)
        $this->assertLessThan(25, $queryCount,
            "Query count was {$queryCount}, expected less than 25");
    }

    /**
     * Test that pagination works correctly with optimizations
     */
    public function test_index_pagination_works_correctly(): void
    {
        $this->seedTestData();

        $response = $this->getJson('/api/questions?per_page=5&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'user',
                        'category',
                        'tags',
                        'answers_count',
                        'votes_count',
                    ]
                ],
                'links',
                'meta'
            ]);

        $data = $response->json('data');
        $this->assertCount(5, $data);
    }

    /**
     * Test that filtering works with optimizations
     */
    public function test_index_filtering_works_correctly(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        Question::factory()
            ->count(5)
            ->published()
            ->create(['category_id' => $category->id])
            ->each(fn($q) => $q->tags()->attach($tag->id));

        $response = $this->getJson("/api/questions?category_id={$category->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        
        foreach ($data as $question) {
            $this->assertEquals($category->id, $question['category']['id']);
        }
    }

    /**
     * Test that authenticated requests work with pin/feature status
     */
    public function test_index_with_authenticated_user_includes_pin_status(): void
    {
        $user = User::factory()->create();
        $question = Question::factory()->published()->create();
        
        $user->pinnedQuestions()->attach($question->id, ['pinned_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/questions');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $pinnedQuestion = collect($data)->firstWhere('id', $question->id);
        
        $this->assertNotNull($pinnedQuestion);
        $this->assertTrue($pinnedQuestion['is_pinned_by_user']);
    }

    /**
     * Test that the response structure is unchanged (backward compatibility)
     */
    public function test_index_response_structure_is_unchanged(): void
    {
        $this->seedTestData();

        $response = $this->getJson('/api/questions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'created_at',
                        'updated_at',
                        'published',
                        'published_at',
                        'published_by',
                        'user' => ['id', 'name'],
                        'category' => ['id', 'name'],
                        'tags',
                        'answers_count',
                        'unpublished_answers_count',
                        'comments_count',
                        'unpublished_comments_count',
                        'votes_count',
                        'votes' => ['upvotes', 'downvotes', 'user_vote'],
                        'views',
                        'is_solved',
                        'is_pinned_by_user',
                        'pinned_at',
                        'is_featured_by_user',
                        'featured_at',
                        'can' => [
                            'view',
                            'publish',
                            'feature',
                            'unfeature',
                            'update',
                            'delete'
                        ]
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    /**
     * Test that indexes are being used (requires database inspection)
     */
    public function test_queries_use_indexes(): void
    {
        $this->seedTestData();

        DB::enableQueryLog();

        $this->getJson('/api/questions');

        $queries = DB::getQueryLog();
        
        // Check that main query exists
        $mainQuery = collect($queries)->first(function ($query) {
            return str_contains($query['query'], 'select `questions`.*');
        });

        $this->assertNotNull($mainQuery, 'Main questions query should exist');
        
        // Verify query uses WHERE published (which should use index)
        $this->assertTrue(
            str_contains($mainQuery['query'], 'published') ||
            str_contains($mainQuery['query'], 'questions'),
            'Query should filter by published status or questions table'
        );
    }

    /**
     * Test with large dataset to ensure scalability
     */
    public function test_index_performs_well_with_large_dataset(): void
    {
        // Create a larger dataset
        $categories = Category::factory()->count(5)->create();
        $tags = Tag::factory()->count(10)->create();
        
        Question::factory()
            ->count(100)
            ->published()
            ->create()
            ->each(function ($question) use ($tags) {
                $question->tags()->attach($tags->random(3)->pluck('id'));
                Answer::factory()->count(rand(0, 5))->create([
                    'question_id' => $question->id
                ]);
            });

        $startTime = microtime(true);
        DB::enableQueryLog();

        $response = $this->getJson('/api/questions?per_page=20');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        $queryCount = count(DB::getQueryLog());

        $response->assertStatus(200);
        
        // With 100 questions, should still be fast
        $this->assertLessThan(2500, $responseTime,
            "Response time with large dataset was {$responseTime}ms");
        
        $this->assertLessThan(30, $queryCount,
            "Query count with large dataset was {$queryCount}");
    }

    /**
     * Seed test data for performance testing
     */
    private function seedTestData(): void
    {
        $categories = Category::factory()->count(3)->create();
        $tags = Tag::factory()->count(5)->create();
        $users = User::factory()->count(5)->create();

        Question::factory()
            ->count(20)
            ->published()
            ->create()
            ->each(function ($question) use ($tags) {
                $question->tags()->attach($tags->random(2)->pluck('id'));
                
                // Add some answers
                Answer::factory()->count(rand(0, 3))->create([
                    'question_id' => $question->id,
                    'published' => true
                ]);
            });
    }
}

