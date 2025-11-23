<?php

namespace App\Services;

use App\Models\SearchCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ResearchService
{
    private string $serpApiKey;
    private string $bingApiKey;
    private string $bingEndpoint;

    public function __construct()
    {
        $this->serpApiKey = env('SERPAPI_KEY', '');
        $this->bingApiKey = env('BING_API_KEY', '');
        $this->bingEndpoint = env('BING_ENDPOINT', 'https://api.bing.microsoft.com/v7.0/search');
    }

    /**
     * Perform web search using available APIs
     */
    public function search(string $query, int $numResults = 10): array
    {
        // Check cache first
        $cacheKey = 'search_' . md5($query);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $results = [];

        // Try SerpAPI first
        if ($this->serpApiKey) {
            $results = $this->searchSerpAPI($query, $numResults);
        }

        // Fallback to Bing if SerpAPI fails or not configured
        if (empty($results) && $this->bingApiKey) {
            $results = $this->searchBing($query, $numResults);
        }

        // Cache results for 1 hour
        if (!empty($results)) {
            Cache::put($cacheKey, $results, 3600);
            
            // Also store in MongoDB cache
            SearchCache::updateOrCreate(
                ['query' => $query],
                [
                    'results' => $results,
                    'expires_at' => now()->addHour(),
                ]
            );
        }

        return $results;
    }

    /**
     * Search using SerpAPI
     */
    private function searchSerpAPI(string $query, int $numResults): array
    {
        try {
            $response = Http::get('https://serpapi.com/search', [
                'api_key' => $this->serpApiKey,
                'engine' => 'google',
                'q' => $query,
                'num' => $numResults,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatSerpResults($data);
            }
        } catch (\Exception $e) {
            Log::error('SerpAPI search failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Search using Bing API
     */
    private function searchBing(string $query, int $numResults): array
    {
        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->bingApiKey,
            ])->get($this->bingEndpoint, [
                'q' => $query,
                'count' => $numResults,
                'mkt' => 'en-US',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatBingResults($data);
            }
        } catch (\Exception $e) {
            Log::error('Bing search failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Format SerpAPI results - returns structured format
     */
    private function formatSerpResults(array $data): array
    {
        $results = [];

        if (isset($data['organic_results'])) {
            foreach ($data['organic_results'] as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'source' => 'serpapi',
                ];
            }
        }

        return $results;
    }

    /**
     * Format Bing results - returns structured format
     */
    private function formatBingResults(array $data): array
    {
        $results = [];

        if (isset($data['webPages']['value'])) {
            foreach ($data['webPages']['value'] as $item) {
                $results[] = [
                    'title' => $item['name'] ?? '',
                    'url' => $item['url'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'source' => 'bing',
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch full article text from URL (optional enhancement)
     */
    public function fetchArticle(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);
            if ($response->successful()) {
                // Basic HTML extraction - in production, use a proper HTML parser
                $html = $response->body();
                // Remove scripts and styles
                $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
                $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
                // Extract text
                $text = strip_tags($html);
                return substr($text, 0, 5000); // Limit to 5000 chars
            }
        } catch (\Exception $e) {
            Log::error('Article fetch failed: ' . $e->getMessage());
        }

        return null;
    }
}

