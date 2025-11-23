# Company Research Assistant

A full-stack Laravel + MongoDB AI agent that researches companies through web search and generates structured account plans via natural conversation.

## Project Information

- **Project Name**: Company Research Assistant
- **Author**: SkRiyaz
- **Company**: EightFold AI
- **Purpose**: Recruitment Assignment

## Features

- 🤖 **AI-Powered Agent**: Natural conversational interface with LLM integration
- 🔍 **Web Search Integration**: Research companies using SerpAPI or Bing Search API
- 📊 **Account Plan Generation**: Multi-section account plans (overview, products, competitors, opportunities, recommendations, etc.)
- 💬 **Session Memory**: Maintains conversation history using MongoDB
- ✏️ **Interactive Editing**: Edit and regenerate specific plan sections
- 📱 **Modern UI**: Split-screen chat interface with live-updating account plan panel

## Requirements

- PHP >= 8.2
- Composer
- Node.js and NPM
- MongoDB (Atlas or local instance)
- LLM API Key (OpenAI, Anthropic, or custom endpoint)
- Search API Key (SerpAPI or Bing Search API)

## Installation

1. Clone the repository
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Copy environment file:
   ```bash
   cp .env.example .env
   ```
4. Generate application key:
   ```bash
   php artisan key:generate
   ```
5. Configure MongoDB in `.env`:
   ```
   DB_CONNECTION=mongodb
   MONGODB_URI=your_mongodb_connection_string
   MONGODB_DATABASE=company_research_assistant
   ```
6. Configure LLM API in `.env` (choose one):
   ```
   # For Gemini (already configured)
   LLM_PROVIDER=gemini
   LLM_API_KEY=your_gemini_api_key
   LLM_MODEL=gemini-1.5-pro
   
   # OR for OpenAI
   LLM_PROVIDER=openai
   LLM_API_KEY=your_openai_api_key
   LLM_MODEL=gpt-4o-mini
   
   # OR for Anthropic
   LLM_PROVIDER=anthropic
   LLM_API_KEY=your_anthropic_api_key
   LLM_MODEL=claude-3-5-sonnet-20241022
   ```
7. Configure Search API in `.env` (at least one):
   ```
   SERPAPI_KEY=your_serpapi_key
   # OR
   BING_API_KEY=your_bing_api_key
   ```
8. Install Node dependencies:
   ```bash
   npm install
   ```
9. Build frontend assets:
   ```bash
   npm run build
   ```

## Development

Run the development server:
```bash
php artisan serve
```

Then visit `http://localhost:8000` in your browser.

## API Endpoints

- `POST /api/agent/message` - Send message to AI agent
- `GET /api/agent/plan` - Get current account plan
- `POST /api/agent/plan/section` - Update a plan section
- `POST /api/agent/plan/regenerate` - Regenerate a plan section
- `GET /api/agent/history` - Get conversation history
- `DELETE /api/agent/history` - Clear conversation history

## Architecture

### Backend Services

- **AgentService**: Main agent loop with LLM integration and action execution
- **ResearchService**: Web search integration (SerpAPI/Bing)
- **PlanService**: MongoDB CRUD operations for account plans
- **MemoryService**: Conversation history management

### MongoDB Collections

- `account_plans`: Stores account plan data
- `conversations`: Stores conversation history
- `search_cache`: Caches search results (optional)

### Agent Actions

The AI agent uses a JSON action format:
- `search`: Perform web search
- `fetch`: Fetch article text (optional)
- `update_plan`: Update a plan section
- `ask_user`: Ask for clarification
- `finish`: Return final response

## Framework

This project is built using [Laravel](https://laravel.com) framework with MongoDB integration via [mongodb/laravel-mongodb](https://github.com/mongodb/laravel-mongodb).

## License

This project is created as part of a recruitment assignment for EightFold AI.
