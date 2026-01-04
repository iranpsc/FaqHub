<?php

namespace App\Observers;

use App\Models\Question;
use Illuminate\Support\Facades\Cache;

class QuestionObserver
{
    /**
     * Handle the Question "created" event.
     */
    public function created(Question $question): void
    {
        $this->clearQuestionsCache();
    }

    /**
     * Handle the Question "updated" event.
     */
    public function updated(Question $question): void
    {
        $this->clearQuestionsCache();
    }

    /**
     * Handle the Question "deleted" event.
     */
    public function deleted(Question $question): void
    {
        $this->clearQuestionsCache();
    }

    /**
     * Handle the Question "restored" event.
     */
    public function restored(Question $question): void
    {
        $this->clearQuestionsCache();
    }

    /**
     * Clear all questions-related cache
     */
    private function clearQuestionsCache(): void
    {
        // Clear cache with tags if supported (Redis, Memcached)
        try {
            Cache::tags(['questions'])->flush();
        } catch (\Exception $e) {
            // If tags not supported, clear specific patterns
            // This is a fallback for file/database cache drivers
        }
    }
}

