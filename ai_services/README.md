# Company Research AI Service

A production-ready Python FastAPI microservice that handles all Gemini AI operations for the Company Research Assistant. This service extracts all LLM logic from Laravel and provides a clean HTTP API for synthesis, conflict detection, and content generation.

## Features

- **Content Synthesis**: Transform raw search results into professional, formatted content
- **Conflict Detection**: Identify and resolve conflicting information (revenue, employees, headquarters)
- **Rate Limit Protection**: Multi-key rotation, throttling (2-4s jitter), exponential backoff retries
- **Caching**: Redis or in-memory cache with 2-hour TTL to minimize API calls
- **Token Optimization**: Automatic snippet cleaning and deduplication to reduce token usage by 50-70%
- **Production-Ready**: Structured logging, health checks, CORS support

## Architecture

```
python_ai_service/
├── app.py                      # FastAPI application with endpoints
├── requirements.txt            # Python dependencies
├── .env.example               # Environment configuration template
├── config/
│   └── settings.py            # Pydantic settings with env var parsing
├── models/
│   └── schemas.py             # Request/response Pydantic models
├── services/
│   ├── gemini_client.py       # Async Gemini API client with retries
│   ├── synthesizer.py         # Content synthesis orchestration
│   ├── conflict_detector.py   # Field-specific conflict detection
│   ├── cache.py               # Redis/in-memory caching layer
│   ├── throttler.py           # Request rate limiting
│   └── key_manager.py         # API key rotation
└── utils/
    ├── text_cleaner.py        # Token optimization utilities
    └── token_utils.py         # Token estimation
```

## Setup

### Prerequisites

- Python 3.10+
- Redis (optional, falls back to in-memory cache)
- Google Cloud API keys for Gemini

### Installation

1. **Clone and navigate to service directory**:
```bash
cd python_ai_service
```

2. **Create virtual environment**:
```bash
python -m venv venv
.\venv\Scripts\Activate.ps1  # Windows PowerShell
```

3. **Install dependencies**:
```bash
pip install -r requirements.txt
```

4. **Configure environment**:
```bash
cp .env.example .env
# Edit .env with your configuration
```

5. **Set Gemini API keys**:
```env
GEMINI_API_KEYS=key1,key2,key3
```

### Running the Service

**Development**:
```bash
uvicorn app:app --reload --host 0.0.0.0 --port 8000
```

**Production**:
```bash
uvicorn app:app --host 0.0.0.0 --port 8000 --workers 4
```

**With custom settings**:
```bash
GEMINI_API_KEYS=key1,key2 THROTTLE_MIN_DELAY=3.0 MAX_RETRIES=5 uvicorn app:app
```

## API Endpoints

### POST /synthesize-section

Synthesize a research section from raw search results.

**Request**:
```json
{
  "session_id": "abc123",
  "step": "company_basics",
  "raw_search_results": [
    {
      "title": "Company Name - Wikipedia",
      "snippet": "Founded in 2010...",
      "link": "https://example.com"
    }
  ],
  "company_name": "Acme Corp"
}
```

**Response**:
```json
{
  "action": "update_plan",
  "section": "company_overview",
  "content": "Acme Corp, founded in 2010...",
  "evidence": ["Source 1", "Source 2"],
  "needs_retry": false,
  "progress_message": "Completed company_basics"
}
```

### POST /detect-conflicts

Detect conflicts between current and previous data.

**Request**:
```json
{
  "session_id": "abc123",
  "step": "financial",
  "current_data": {"content": "Revenue: $5B"},
  "previous_data": {"content": "Revenue: $3B"}
}
```

**Response**:
```json
{
  "action": "ask_user",
  "conflicts": [
    {
      "field": "revenue",
      "old_value": "$3B",
      "new_value": "$5B",
      "context": "Financial data"
    }
  ],
  "question": "Which revenue figure is correct?",
  "buttons": [
    {"id": "keep_new", "text": "Use $5B", "value": "$5B"},
    {"id": "keep_old", "text": "Use $3B", "value": "$3B"}
  ]
}
```

### POST /process-step

Process a complete research step (synthesis + conflict detection).

**Request**:
```json
{
  "session_id": "abc123",
  "step": "products_tech",
  "company_name": "Acme Corp",
  "search_results": [...],
  "previous_content": null
}
```

### POST /generate-final-plan

Generate executive summary from all sections.

**Request**:
```json
{
  "session_id": "abc123",
  "company_name": "Acme Corp",
  "all_sections": {
    "company_overview": "...",
    "financial_overview": "...",
    "products_services": "..."
  }
}
```

### POST /clean-text

Clean and format text.

**Request**:
```json
{
  "text": "Raw   text\n\nwith   extra   spaces",
  "max_length": 200
}
```

### GET /health

Health check endpoint.

**Response**:
```json
{
  "status": "healthy",
  "service": "company-research-ai",
  "version": "1.0.0"
}
```

## Laravel Integration

### 1. Create AI Service Client in Laravel

Create `app/Services/AIServiceClient.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIServiceClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_service.url', 'http://localhost:8000');
        $this->timeout = config('services.ai_service.timeout', 120);
    }

    /**
     * Synthesize a research section
     */
    public function synthesizeSection(
        string $sessionId,
        string $step,
        array $searchResults,
        string $companyName
    ): array {
        return $this->makeRequest('/synthesize-section', [
            'session_id' => $sessionId,
            'step' => $step,
            'raw_search_results' => $searchResults,
            'company_name' => $companyName,
        ]);
    }

    /**
     * Detect conflicts between data
     */
    public function detectConflicts(
        string $sessionId,
        string $step,
        array $currentData,
        ?array $previousData
    ): array {
        return $this->makeRequest('/detect-conflicts', [
            'session_id' => $sessionId,
            'step' => $step,
            'current_data' => $currentData,
            'previous_data' => $previousData ?? [],
        ]);
    }

    /**
     * Process a complete research step
     */
    public function processStep(
        string $sessionId,
        string $step,
        string $companyName,
        array $searchResults,
        ?array $previousContent = null
    ): array {
        return $this->makeRequest('/process-step', [
            'session_id' => $sessionId,
            'step' => $step,
            'company_name' => $companyName,
            'search_results' => $searchResults,
            'previous_content' => $previousContent,
        ]);
    }

    /**
     * Generate final account plan
     */
    public function generateFinalPlan(
        string $sessionId,
        string $companyName,
        array $allSections
    ): array {
        return $this->makeRequest('/generate-final-plan', [
            'session_id' => $sessionId,
            'company_name' => $companyName,
            'all_sections' => $allSections,
        ]);
    }

    /**
     * Clean text
     */
    public function cleanText(string $text, ?int $maxLength = null): array
    {
        return $this->makeRequest('/clean-text', [
            'text' => $text,
            'max_length' => $maxLength,
        ]);
    }

    /**
     * Clear session cache
     */
    public function clearSessionCache(string $sessionId): array
    {
        return $this->makeRequest("/clear-cache/{$sessionId}", [], 'POST');
    }

    /**
     * Make HTTP request to AI service
     */
    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        try {
            $url = $this->baseUrl . $endpoint;
            
            Log::info("AI Service Request: {$method} {$url}", ['data' => $data]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->{strtolower($method)}($url, $data);

            if ($response->failed()) {
                Log::error("AI Service Error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception("AI Service returned {$response->status()}: {$response->body()}");
            }

            $result = $response->json();
            Log::info("AI Service Response", ['result' => $result]);

            return $result;

        } catch (\Exception $e) {
            Log::error("AI Service Exception: {$e->getMessage()}");
            throw $e;
        }
    }
}
```

### 2. Configure Service in config/services.php

```php
return [
    // ... existing services ...
    
    'ai_service' => [
        'url' => env('AI_SERVICE_URL', 'http://localhost:8000'),
        'timeout' => env('AI_SERVICE_TIMEOUT', 120),
    ],
];
```

### 3. Update .env

```env
AI_SERVICE_URL=http://localhost:8000
AI_SERVICE_TIMEOUT=120
```

### 4. Replace AgentService.php Calls

**Before** (in AgentService.php):
```php
$synthesis = $this->synthesizeWithGemini($step, $searchResults, $companyName);
```

**After**:
```php
use App\Services\AIServiceClient;

class AgentService
{
    private AIServiceClient $aiService;

    public function __construct(AIServiceClient $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processResearchStep($step, $companyName, $searchResults)
    {
        $sessionId = session()->getId();
        
        // Call Python AI service instead of Gemini directly
        $result = $this->aiService->synthesizeSection(
            $sessionId,
            $step,
            $searchResults,
            $companyName
        );
        
        return $result;
    }
}
```

### 5. Service Provider Registration

In `app/Providers/AppServiceProvider.php`:

```php
use App\Services\AIServiceClient;

public function register()
{
    $this->app->singleton(AIServiceClient::class, function ($app) {
        return new AIServiceClient();
    });
}
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `GEMINI_API_KEYS` | - | Comma-separated Gemini API keys |
| `GEMINI_MODEL` | gemini-2.0-flash-exp | Model name |
| `THROTTLE_MIN_DELAY` | 2.0 | Minimum delay between requests (seconds) |
| `THROTTLE_MAX_DELAY` | 4.0 | Maximum delay between requests (seconds) |
| `MAX_RETRIES` | 4 | Maximum retry attempts |
| `RETRY_DELAYS` | 3,8,20,45 | Retry delays in seconds |
| `REDIS_HOST` | localhost | Redis host (optional) |
| `REDIS_PORT` | 6379 | Redis port |
| `CACHE_TTL` | 7200 | Cache time-to-live (seconds) |
| `MAX_OUTPUT_TOKENS` | 2048 | Maximum output tokens per request |
| `HOST` | 0.0.0.0 | Server host |
| `PORT` | 8000 | Server port |
| `WORKERS` | 1 | Number of worker processes |
| `LOG_LEVEL` | INFO | Logging level |

## Deployment

### Option 1: Systemd Service

Create `/etc/systemd/system/ai-service.service`:

```ini
[Unit]
Description=Company Research AI Service
After=network.target

[Service]
Type=notify
User=www-data
Group=www-data
WorkingDirectory=/var/www/python_ai_service
Environment="PATH=/var/www/python_ai_service/venv/bin"
EnvironmentFile=/var/www/python_ai_service/.env
ExecStart=/var/www/python_ai_service/venv/bin/uvicorn app:app --host 0.0.0.0 --port 8000 --workers 4
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable ai-service
sudo systemctl start ai-service
sudo systemctl status ai-service
```

### Option 2: Docker

Create `Dockerfile`:

```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 8000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "4"]
```

Build and run:
```bash
docker build -t ai-service .
docker run -d -p 8000:8000 --env-file .env ai-service
```

### Option 3: Docker Compose

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  ai-service:
    build: .
    ports:
      - "8000:8000"
    env_file:
      - .env
    restart: unless-stopped
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    restart: unless-stopped
```

Run:
```bash
docker-compose up -d
```

## Monitoring

### Health Checks

```bash
curl http://localhost:8000/health
```

### Logs

Logs are written to `logs/ai_service.log` with rotation (100MB, 7 days retention).

View live logs:
```bash
tail -f logs/ai_service.log
```

### Performance Metrics

Monitor key metrics:
- Request latency (check logs for timing)
- Cache hit rate (Redis INFO or logs)
- Gemini API errors (check 429 rate limits)
- Token usage (estimated in logs)

## Troubleshooting

### 429 Rate Limit Errors

1. **Add more API keys**: Update `GEMINI_API_KEYS` with additional keys
2. **Increase throttling**: Set `THROTTLE_MIN_DELAY=3.0` and `THROTTLE_MAX_DELAY=6.0`
3. **Check cache**: Verify Redis is running and accessible
4. **Review retry delays**: Extend `RETRY_DELAYS=5,10,30,60`

### Connection Refused from Laravel

1. **Check service is running**: `curl http://localhost:8000/health`
2. **Verify firewall**: Ensure port 8000 is open
3. **Update Laravel .env**: Set correct `AI_SERVICE_URL`
4. **Check logs**: Review `logs/ai_service.log` for errors

### Redis Connection Issues

Service automatically falls back to in-memory cache if Redis is unavailable. To force Redis:

1. Verify Redis is running: `redis-cli ping`
2. Check connection: `REDIS_HOST=localhost REDIS_PORT=6379`
3. Test connectivity: `telnet localhost 6379`

### High Memory Usage

1. **Reduce workers**: Set `WORKERS=1` or `WORKERS=2`
2. **Lower cache TTL**: Set `CACHE_TTL=3600` (1 hour)
3. **Use Redis**: Offload cache to Redis instead of in-memory

## Development

### Running Tests

```bash
pytest tests/ -v
```

### Code Quality

```bash
# Format code
black .

# Lint
flake8 .

# Type checking
mypy .
```

### Adding New Endpoints

1. Define request/response models in `models/schemas.py`
2. Implement logic in appropriate service module
3. Add endpoint to `app.py`
4. Update Laravel client in `AIServiceClient.php`
5. Add tests

## License

Proprietary - EightFold AI

## Support

For issues or questions, contact the development team.
