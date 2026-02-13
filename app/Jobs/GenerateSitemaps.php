<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url as SitemapUrl;

class GenerateSitemaps implements ShouldQueue
{
    use Queueable, Dispatchable;

    /**
     * Maximum number of links per sitemap file.
     */
    private const MAX_LINKS_PER_FILE = 5000;

    /**
     * Collected generated sitemap filenames (relative to public/sitemap).
     *
     * @var array<int, string>
     */
    private array $generatedFiles = [];

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $targetDir = public_path('sitemap');

        if (! File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // Generate entity-specific sitemaps
        $this->generateQuestionsSitemaps($baseUrl, $targetDir);
        $this->generateCategoriesSitemaps($baseUrl, $targetDir);
        $this->generateTagsSitemaps($baseUrl, $targetDir);
        $this->generateAuthorsSitemaps($baseUrl, $targetDir);

        // Create a sitemap index referencing all generated files
        if (! empty($this->generatedFiles)) {
            $index = SitemapIndex::create();
            foreach ($this->generatedFiles as $relativeFile) {
                $index->add($baseUrl . '/sitemap/' . ltrim($relativeFile, '/'));
            }
            $index->writeToFile($targetDir . DIRECTORY_SEPARATOR . 'sitemap.xml');
            $this->generatedFiles[] = 'sitemap.xml';
        }

        $this->uploadSitemapsToFtp($targetDir);
    }

    /**
     * Upload generated sitemap files to the configured FTP disk.
     */
    private function uploadSitemapsToFtp(string $targetDir): void
    {
        $host = config('filesystems.disks.sitemap_ftp.host');
        $username = config('filesystems.disks.sitemap_ftp.username');

        if (empty($host) || empty($username)) {
            return;
        }

        try {
            $disk = Storage::disk('sitemap_ftp');
            foreach ($this->generatedFiles as $relativeFile) {
                $localPath = $targetDir . DIRECTORY_SEPARATOR . $relativeFile;
                if (File::exists($localPath)) {
                    $disk->put($relativeFile, File::get($localPath));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to upload sitemaps to FTP: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    private function generateQuestionsSitemaps(string $baseUrl, string $targetDir): void
    {
        $part = 1;
        Question::query()
            ->published()
            ->orderBy('id')
            ->chunkById(self::MAX_LINKS_PER_FILE, function ($questions) use (&$part, $baseUrl, $targetDir) {
                $sitemap = Sitemap::create();

                foreach ($questions as $question) {
                    $loc = $baseUrl . '/questions/' . ltrim((string) $question->slug, '/');
                    $tag = SitemapUrl::create($loc);
                    if ($question->updated_at) {
                        $tag->setLastModificationDate($question->updated_at);
                    }
                    $sitemap->add($tag);
                }

                $file = "questions-sitemap-{$part}.xml";
                $sitemap->writeToFile($targetDir . DIRECTORY_SEPARATOR . $file);
                $this->generatedFiles[] = $file;
                $part++;
            });
    }

    private function generateCategoriesSitemaps(string $baseUrl, string $targetDir): void
    {
        $part = 1;
        Category::query()
            ->orderBy('id')
            ->chunkById(self::MAX_LINKS_PER_FILE, function ($categories) use (&$part, $baseUrl, $targetDir) {
                $sitemap = Sitemap::create();

                foreach ($categories as $category) {
                    $loc = $baseUrl . '/categories/' . ltrim((string) $category->slug, '/');
                    $tag = SitemapUrl::create($loc);
                    if ($category->updated_at) {
                        $tag->setLastModificationDate($category->updated_at);
                    }
                    $sitemap->add($tag);
                }

                $file = $part === 1 && count($categories) < self::MAX_LINKS_PER_FILE
                    ? 'categories-sitemap.xml'
                    : "categories-sitemap-{$part}.xml";

                $sitemap->writeToFile($targetDir . DIRECTORY_SEPARATOR . $file);
                $this->generatedFiles[] = $file;
                $part++;
            });
    }

    private function generateTagsSitemaps(string $baseUrl, string $targetDir): void
    {
        $part = 1;
        Tag::query()
            ->orderBy('id')
            ->chunkById(self::MAX_LINKS_PER_FILE, function ($tags) use (&$part, $baseUrl, $targetDir) {
                $sitemap = Sitemap::create();

                foreach ($tags as $tagModel) {
                    $loc = $baseUrl . '/tags/' . ltrim((string) $tagModel->slug, '/');
                    $tag = SitemapUrl::create($loc);
                    if ($tagModel->updated_at) {
                        $tag->setLastModificationDate($tagModel->updated_at);
                    }
                    $sitemap->add($tag);
                }

                $file = $part === 1 && count($tags) < self::MAX_LINKS_PER_FILE
                    ? 'tags-sitemap.xml'
                    : "tags-sitemap-{$part}.xml";

                $sitemap->writeToFile($targetDir . DIRECTORY_SEPARATOR . $file);
                $this->generatedFiles[] = $file;
                $part++;
            });
    }

    private function generateAuthorsSitemaps(string $baseUrl, string $targetDir): void
    {
        $part = 1;
        User::query()
            ->orderBy('id')
            ->chunkById(self::MAX_LINKS_PER_FILE, function ($users) use (&$part, $baseUrl, $targetDir) {
                $sitemap = Sitemap::create();

                foreach ($users as $user) {
                    $loc = $baseUrl . '/authors/' . (string) $user->id;
                    $tag = SitemapUrl::create($loc);
                    if ($user->updated_at) {
                        $tag->setLastModificationDate($user->updated_at);
                    }
                    $sitemap->add($tag);
                }

                $file = $part === 1 && count($users) < self::MAX_LINKS_PER_FILE
                    ? 'authors-sitemap.xml'
                    : "authors-sitemap-{$part}.xml";

                $sitemap->writeToFile($targetDir . DIRECTORY_SEPARATOR . $file);
                $this->generatedFiles[] = $file;
                $part++;
            });
    }
}


