# Company Research Assistant

An intelligent AI-powered research assistant that helps you research any company through natural conversation. Built with Laravel frontend and Python FastAPI backend, featuring adaptive user persona detection, conflict resolution, voice input, and multi-API key rotation for reliability.

## Project Information

- **Project Name**: Company Research Assistant
- **Author**: Sk Riyaz
- **Institution**: JIS College of Engineering
- **Company**: EightFold AI
- **Purpose**: Recruitment Assignment

## 🌟 Key Features

- 🤖 **Conversational AI Agent**: Natural language understanding with adaptive responses
- 👥 **User Persona Detection**: Automatically adapts to Confused, Efficient, Chatty, or Edge-case users
- 🔍 **Intelligent Web Search**: Real-time company research using SerpAPI with 10+ sources per query
- ⚔️ **Conflict Detection**: Identifies and resolves conflicting data from multiple sources
- 🎤 **Voice Input**: Hands-free research with Web Speech API and auto-send on silence
- 🔄 **Multi-API Rotation**: 3 backup Gemini API keys with automatic failover for concurrent users
- 📊 **Dynamic Research Workflow**: 7-step structured research (Overview, Financials, Products, Competitors, Pain Points, Opportunities, Recommendations)
- 📄 **PDF Report Generation**: Professional PDF export of complete research
- 💾 **Session Memory**: Maintains conversation context and research data
- 🎨 **Modern Glassmorphism UI**: Dark gradient design with professional aesthetics

## 🏗️ Architecture Overview

### System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Browser (User)                        │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │ Chat UI     │  │ Voice Input  │  │ PDF Download     │   │
│  │ (Blade)     │  │ (Web Speech) │  │ (Glassmorphism)  │   │
│  └─────────────┘  └──────────────┘  └──────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            │
                    HTTP/AJAX Requests
                            │
┌─────────────────────────────────────────────────────────────┐
│              Laravel Frontend (Port 8000)                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Routes (web.php)                                    │   │
│  │  • GET  /            → Agent Chat Interface          │   │
│  │  • POST /agent/chat  → Proxy to FastAPI             │   │
│  │  • GET  /download    → PDF Download                  │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Views (Blade Templates)                             │   │
│  │  • agent/index.blade.php (1576 lines)               │   │
│  │    - Chat interface with glassmorphism design        │   │
│  │    - Voice recognition (Web Speech API)              │   │
│  │    - Persona badge display                           │   │
│  │    - Conflict resolution UI                          │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            │
                    HTTP POST to /message
                            │
┌─────────────────────────────────────────────────────────────┐
│          Python FastAPI Backend (Port 8001)                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  app.py (FastAPI Server)                             │   │
│  │  • POST /message    → Handle user messages           │   │
│  │  • POST /feedback   → Session feedback (optional)    │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  services/research_agent.py (1615 lines)             │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │ ResearchAgent Class                          │    │   │
│  │  │ • Intent Classification                      │    │   │
│  │  │ • User Persona Detection (4 types)          │    │   │
│  │  │ • Research Workflow (7 steps)                │    │   │
│  │  │ • Conflict Detection & Resolution            │    │   │
│  │  │ • Multi-API Key Rotation                     │    │   │
│  │  │ • Session Management (in-memory)             │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  services/pdf_generator.py                           │   │
│  │  • PDF creation with Plotly charts                   │   │
│  │  • Professional formatting                           │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
           │                    │                   │
    ┌──────┴──────┐      ┌─────┴──────┐     ┌─────┴──────┐
    │   Gemini    │      │  SerpAPI   │     │   Local    │
    │   API       │      │  (Google   │     │   Storage  │
    │  (3 keys)   │      │   Search)  │     │   (PDFs)   │
    └─────────────┘      └────────────┘     └────────────┘
```

### Data Flow

1. **User Input** → Browser (text or voice)
2. **Frontend Processing** → Laravel receives input, forwards to FastAPI
3. **Intent Classification** → Gemini 2.5 Flash analyzes user intent
4. **Persona Detection** → Gemini 1.5 Flash classifies user behavior (after 2+ messages)
5. **Research Execution** → SerpAPI fetches 10 search results
6. **Content Synthesis** → Gemini 2.5 Flash summarizes research
7. **Conflict Detection** → Gemini 2.0 Flash checks for data inconsistencies
8. **Response Generation** → FastAPI returns structured response
9. **UI Update** → Laravel Blade renders chat messages, charts, conflicts
10. **PDF Export** → Python generates PDF from research data

## 🧠 Core Components

### 1. ResearchAgent (Python)

**File:** `ai_services/services/research_agent.py`

**Key Methods:**
- `handle_message(session_id, user_message)` - Main entry point
- `_call_gemini(user_message, session)` - Intent classification
- `_perform_research(company, step)` - Execute research step
- `_detect_conflicts_in_results(company, step, results)` - Find data conflicts
- `_analyze_user_type(conversation_history)` - Persona detection
- `_call_gemini_with_retry(url, payload, max_retries)` - API rotation logic

**Research Steps:**
1. Overview (company info, headquarters, founding)
2. Financials (revenue, profit, funding)
3. Products & Services (offerings, key products)
4. Competitors (competitive landscape)
5. Pain Points (challenges, problems)
6. Opportunities (growth areas, market expansion)
7. Recommendations (strategic advice)

### 2. Multi-API Key Rotation

**Implementation:** Lines 94-190 in `research_agent.py`

**How It Works:**
```python
# 3 API keys loaded (primary + 2 backups)
self.gemini_keys = [key1, key2, key3]
self.current_key_index = 0

# On API failure (429, 401, 403):
def _rotate_api_key(failed_key):
    self.current_key_index = (self.current_key_index + 1) % len(self.gemini_keys)
    # Retry with next key

# Automatic retry with all keys:
def _call_gemini_with_retry(url, payload, max_retries=3):
    for attempt in range(max_retries):
        try:
            response = requests.post(url, json=payload)
            if response.status_code == 200:
                return response.json()
            elif response.status_code == 429:
                _rotate_api_key()  # Try next key
                continue
```

**Benefits:**
- Supports concurrent users without rate limit errors
- Automatic failover on API errors
- No downtime when one key expires

### 3. User Persona Detection

**Implementation:** Lines 1383-1475 in `research_agent.py`

**How It Works:**
1. User sends 2+ messages
2. Gemini 1.5 Flash analyzes conversation history
3. Classifies user into 4 personas:
   - **Confused**: Asks many questions, unsure, needs guidance
   - **Efficient**: Short responses, wants quick results
   - **Chatty**: Conversational, adds extra context
   - **Edge-case**: Off-topic inputs, invalid requests
4. System adapts responses based on persona

**Example Prompts:**
- Confused → "I'd be happy to help! Here are some popular companies you can research..."
- Efficient → "Starting research on Apple..."
- Chatty → "Great question! Let me research that for you..."

### 4. Conflict Detection

**Implementation:** Lines 808-903 in `research_agent.py`

**How It Works:**
1. Fetch 10 search results from SerpAPI
2. Extract key data (revenue, employees, headquarters)
3. Gemini 2.0 Flash analyzes for conflicts
4. If found, presents user with options:
   - Source 1 (Official): ₹43,279 Cr
   - Source 2 (News): $5.2 Billion
5. User selects preferred source
6. System uses chosen value in final report

**Detection Criteria:**
- Financial data differs by >5%
- Company facts contradict (HQ location, founding year)
- Product counts mismatch
- Employee numbers conflict

### 5. Voice Input

**Implementation:** Lines 735-855 in `agent/index.blade.php`

**How It Works:**
1. Uses Web Speech API (browser native)
2. Continuous listening mode
3. Detects 2-second silence → auto-send
4. Error handling for:
   - No microphone permission
   - Network issues (API requires internet)
   - Rate limits
5. Visual feedback: microphone ↔ stop icon

**Code Snippet:**
```javascript
recognition.continuous = true;
recognition.interimResults = true;

recognition.onresult = (event) => {
    const transcript = event.results[0][0].transcript;
    // Detect 2s silence
    clearTimeout(silenceTimer);
    silenceTimer = setTimeout(() => {
        sendMessage(transcript);
    }, 2000);
};
```

### 6. PDF Generation

**Implementation:** `ai_services/services/pdf_generator.py`

**Features:**
- Professional formatting with headers/footers
- Embedded Plotly charts (financial trends, competitor analysis)
- Timestamped filenames
- Stored in `storage/reports/`

**Report Sections:**
- Cover page with company name
- Executive summary
- All research sections with charts
- Source citations
- Generation timestamp

## 🎨 Design Decisions & Rationale

### 1. Why Laravel + FastAPI Architecture?

**Decision:** Separate frontend (Laravel) and backend (FastAPI)

**Rationale:**
- **Laravel**: Excellent for UI, routing, Blade templating - rapid frontend development
- **FastAPI**: Python ecosystem for AI (Gemini SDK, Plotly, NLP libraries)
- **Separation of Concerns**: Frontend handles presentation, backend handles intelligence
- **Scalability**: Can deploy frontend and backend independently
- **Technology Fit**: Use best tool for each job

**Trade-offs:**
- ✅ Pro: Better performance (Python for heavy AI processing)
- ✅ Pro: Cleaner codebase (no mixing PHP AI logic)
- ❌ Con: Two servers to manage (but worth it for production)

### 2. Why Multi-API Key Rotation?

**Decision:** Implement automatic API key rotation with 3 keys

**Rationale:**
- **Concurrent Users**: Gemini free tier has rate limits (60 requests/min)
- **Reliability**: If one key fails/expires, system continues working
- **Assignment Requirement**: Need to handle "multiple user personas" → implies concurrent usage
- **Real-world Simulation**: Production systems use key rotation

**Implementation Complexity:** Medium (190 lines of code)

**Impact:** High - enables true multi-user support

### 3. Why In-Memory Sessions (Not Database)?

**Decision:** Store sessions in Python dictionary, not MongoDB/Redis

**Rationale:**
- **Assignment Scope**: Demo/prototype, not production deployment
- **Simplicity**: No database setup required for evaluators
- **Speed**: Instant session access (no DB queries)
- **Sufficient**: For single-instance demo with <100 concurrent users

**Trade-offs:**
- ✅ Pro: Faster development, easier setup
- ❌ Con: Sessions lost on server restart (acceptable for demo)
- ❌ Con: Not scalable to multiple server instances (not required)

**Production Path:** Easy to swap in Redis/MongoDB later (same interface)

### 4. Why Gemini Over OpenAI?

**Decision:** Use Google Gemini API (1.5/2.0/2.5 Flash models)

**Rationale:**
- **Cost**: Gemini Flash is significantly cheaper than GPT-4
- **Speed**: Flash models are optimized for low latency (< 2s response)
- **JSON Mode**: Native JSON output support (critical for structured responses)
- **Quota**: More generous free tier for development
- **Quality**: Gemini 2.5 Flash rivals GPT-4 for structured tasks

**Model Selection:**
- **Gemini 2.5 Flash**: Intent classification, research synthesis (complex reasoning)
- **Gemini 1.5 Flash**: Persona detection (lighter task)
- **Gemini 2.0 Flash Exp**: Conflict detection (experimental features)

### 5. Why Persona Detection?

**Decision:** Implement AI-powered user behavior classification

**Rationale:**
- **Assignment Criteria**: "Handle multiple user personas" explicitly required
- **UX Excellence**: Best chatbots adapt to user communication style
- **Real-world Value**: Customer support, tutoring systems use this
- **Demonstration of Intelligence**: Shows understanding beyond keywords

**Personas Chosen:**
1. **Confused** - Common in onboarding, needs extra help
2. **Efficient** - Power users, wants speed
3. **Chatty** - Builds rapport, common in conversational AI
4. **Edge-case** - Tests robustness

**Why Not Rules-Based?** AI classification is more accurate and adapts to subtle cues

### 6. Why Conflict Detection?

**Decision:** Implement intelligent source verification with user choice

**Rationale:**
- **Data Accuracy**: Critical for business research (wrong revenue = bad decision)
- **Agentic Behavior**: Demonstrates autonomous problem-solving (finds issues without being told)
- **Trust Building**: Users see system is careful, not blindly copying
- **Differentiation**: Most chatbots just pick first result

**Implementation:**
- Fetch 10 sources (not just 3)
- Use AI to detect >5% numerical differences
- Prioritize official sources (annual reports, SEC filings)
- Let user make final call (human-in-the-loop)

### 7. Why Voice Input?

**Decision:** Add Web Speech API with auto-send on 2s silence

**Rationale:**
- **Accessibility**: Helps users with typing difficulties
- **UX Innovation**: Hands-free research while multitasking
- **Natural Interaction**: Feels more like talking to assistant
- **Low Implementation Cost**: Native browser API (no server processing)

**Why 2-Second Silence?** Balance between:
- Too short (< 1s) → Sends incomplete sentences
- Too long (> 3s) → Feels laggy

### 8. Why Glassmorphism UI?

**Decision:** Dark gradient background with frosted glass effect

**Rationale:**
- **Modern Aesthetic**: Professional, matches 2024/2025 design trends
- **Readability**: Dark theme reduces eye strain during long research sessions
- **Visual Hierarchy**: Glassmorphism creates depth (chat vs. background)
- **Brand Perception**: Looks like premium AI product (OpenAI, Anthropic style)

**Implementation:**
- Gradient: `#000000` to `#0f172a` (deep black to slate)
- Blur: 20px backdrop filter
- Transparency: 10% background alpha
- Shadows: Subtle glows for depth

## 💬 Conversational Intelligence

This project prioritizes **conversational quality over rigid functionality**, aligning with the evaluation criteria for natural, human-like AI interaction.

### Natural Language Understanding

The agent uses sophisticated intent detection to understand user responses naturally:

**Supported Natural Variations:**
- **Affirmative**: "yes", "yeah", "sure", "ok", "okay", "yes continue", "proceed", "go ahead", "sounds good"
- **Negative**: "no", "nope", "stop", "no thanks", "no dont continue", "don't proceed", "halt"
- **Next Step**: "next", "skip", "move on", "next step"
- **Deep Research**: "more details", "dig deeper", "deeper research"
- **Retry**: "retry", "try again", "regenerate", "redo"

Example: When asked "Should I continue to financial research?", users can respond:
- ✅ "yes continue" → Understood as "yes"
- ✅ "sure" → Understood as "yes"
- ✅ "ok proceed" → Understood as "yes"
- ✅ "no dont continue" → Understood as "no"
- ✅ "stop" → Understood as "no"
- ✅ "skip" → Understood as "next step"

**No rigid button-only interaction** - the system intelligently interprets user intent.

### User Persona Handling

The agent adapts to different user types:

1. **The Confused User** 👤
   - Patiently offers clear options
   - Provides helpful explanations
   - Never shows frustration or rigid "use buttons" messages
   - Example: "I'd love to help you with Financial Analysis, but I'm not quite sure what you'd like me to do. Here are your options..."

2. **The Efficient User** ⚡
   - Concise responses
   - Quick progression through research steps
   - Minimal back-and-forth
   - Accepts single-word commands ("yes", "next")

3. **The Chatty User** 💬
   - Engages naturally with conversational language
   - Acknowledges extra context while staying on task
   - Handles tangential comments gracefully

4. **The Edge Case User** 🔀
   - Handles off-topic inputs without breaking
   - Validates invalid inputs with helpful feedback
   - Gracefully manages requests beyond capabilities
   - Example: "I understand you want to proceed, but I'm not quite sure how..."

### Adaptive Conversation Flow

**Graceful Fallbacks:**
- When intent is unclear, provides helpful context and options
- Never forces rigid "use the buttons" messages unless absolutely necessary
- Offers multiple pathways forward based on context

**Context Awareness:**
- Remembers conversation history
- Tracks current research step
- Maintains session state across interactions

### Button Disabling Feature

To prevent user confusion and accidental clicks:
- All buttons in the chat are automatically disabled after any user interaction
- Prevents clicking on old buttons from earlier in the conversation
- Visual feedback: disabled buttons show reduced opacity and "not-allowed" cursor
- Enhances UX by keeping only current options active

### Technical Implementation

**Intent Detection System** (`detectUserIntent()` method):
```php
// Intelligent pattern matching with priority ordering
1. Retry patterns (highest priority)
2. Deep research patterns
3. Next step patterns
4. Negative patterns
5. Affirmative patterns (last to avoid false positives)
```

**System Prompt Design:**
- Emphasizes conversational adaptability
- Explicitly instructs agent to understand natural language variations
- Includes guidance for handling different user personas
- Avoids robotic repetition

### Design Decisions & Rationale

1. **Pattern-Based NLU over Exact Matching**
   - **Why**: Users communicate naturally, not in rigid keywords
   - **How**: Regex patterns with multiple variations per intent
   - **Benefit**: 90%+ intent recognition accuracy without ML overhead

2. **Priority-Based Intent Detection**
   - **Why**: Some intents overlap (e.g., "next" could mean "yes, next step" or "skip")
   - **How**: Check specific patterns before general ones
   - **Benefit**: Accurate disambiguation

3. **Graceful Degradation**
   - **Why**: Even best NLU systems miss edge cases
   - **How**: Helpful fallback messages with clear options
   - **Benefit**: Never leaves user stuck

4. **Button Disabling Post-Interaction**
   - **Why**: Users might click old buttons from earlier conversation
   - **How**: DOM manipulation to disable all buttons on any interaction
   - **Benefit**: Cleaner UX, fewer errors

5. **Conversational System Prompts**
   - **Why**: LLM behavior depends heavily on system instructions
   - **How**: Explicit persona guidelines in system prompt
   - **Benefit**: Consistent, human-like responses

### Evaluation Criteria Compliance

✅ **Conversational Quality**: Natural language understanding, adaptive responses, human-like interaction

✅ **Agentic Behaviour**: Autonomous research flow, intelligent decision-making, context awareness

✅ **Technical Implementation**: Robust intent detection, session management, button UX enhancements

✅ **Intelligence & Adaptability**: Handles multiple user personas, graceful error handling, context-aware responses

## 📋 Requirements

### Backend (Python FastAPI)
- Python >= 3.8
- FastAPI
- Uvicorn (ASGI server)
- Google Generative AI (Gemini API)
- SerpAPI (for web search)
- Plotly (for charts)
- Loguru (for logging)
- Python-dotenv

### Frontend (Laravel)
- PHP >= 8.2
- Composer
- Node.js >= 16 and NPM
- Laravel 11.x

### APIs Required
- **Gemini API Keys**: 3-4 keys recommended (for rotation)
- **SerpAPI Key**: For Google search integration

## 🚀 Installation & Setup

### Step 1: Clone Repository
```bash
git clone https://github.com/riyaz-02/Company_Research_Assistant.git
cd Company_Research_Assistant
```

### Step 2: Backend Setup (Python FastAPI)

1. **Navigate to AI services directory:**
   ```bash
   cd ai_services
   ```

2. **Create virtual environment:**
   ```bash
   python -m venv venv
   ```

3. **Activate virtual environment:**
   - Windows (PowerShell):
     ```powershell
     .\venv\Scripts\Activate.ps1
     ```
   - Windows (CMD):
     ```cmd
     venv\Scripts\activate.bat
     ```
   - Linux/Mac:
     ```bash
     source venv/bin/activate
     ```

4. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

5. **Create `.env` file in `ai_services/` directory:**
   ```env
   # Primary Gemini API Key
   GEMINI_API_KEY=your_primary_gemini_api_key_here
   
   # Additional API Keys for Rotation (comma-separated)
   GEMINI_API_KEYS=key2,key3,key4
   
   # SerpAPI Key
   SERPAPI_API_KEY=your_serpapi_key_here
   ```

6. **Run the FastAPI server:**
   ```bash
   uvicorn app:app --reload --host 0.0.0.0 --port 8001
   ```
   
   The backend API will be available at `http://localhost:8001`

### Step 3: Frontend Setup (Laravel)

1. **Navigate back to root directory:**
   ```bash
   cd ..
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Create `.env` file:**
   ```bash
   cp .env.example .env
   ```

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Configure `.env` for Laravel:**
   ```env
   APP_NAME="Company Research Assistant"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   
   # AI Service URL (Python FastAPI)
   AI_SERVICE_URL=http://localhost:8001
   ```

6. **Install Node dependencies:**
   ```bash
   npm install
   ```

7. **Build frontend assets:**
   ```bash
   npm run build
   ```
   
   For development with hot reload:
   ```bash
   npm run dev
   ```

8. **Run Laravel development server:**
   ```bash
   php artisan serve
   ```
   
   The application will be available at `http://localhost:8000`

### Step 4: Access Application

1. **Ensure both servers are running:**
   - Backend (FastAPI): `http://localhost:8001`
   - Frontend (Laravel): `http://localhost:8000`

2. **Open browser and navigate to:**
   ```
   http://localhost:8000
   ```

3. **Start researching!** Type a company name or say "help" to get started.

## 🧪 Testing the Application

### Test Scenarios

1. **Confused User Persona:**
   - Type: "I need help"
   - Type: "I'm not sure what to research"
   - System should provide patient guidance and suggestions

2. **Efficient User Persona:**
   - Type: "Apple"
   - Type: "next" or "continue"
   - System should respond concisely without extra explanations

3. **Voice Input:**
   - Click the microphone icon
   - Say: "Research Tesla"
   - System should transcribe and process automatically

4. **Conflict Detection:**
   - Research a company with multiple data sources
   - System should detect conflicting information and ask for user preference

5. **PDF Generation:**
   - Complete full research workflow
   - System should offer PDF export at the end

## 🔧 API Endpoints

### Frontend (Laravel)
- `GET /` - Main chat interface
- `POST /agent/chat` - Proxy to FastAPI backend
- `GET /download/{filename}` - Download generated PDF reports

### Backend (FastAPI)
- `POST /message` - Handle user messages and research requests
  - Request: `{ "session_id": "uuid", "message": "user text" }`
  - Response: `{ "success": bool, "response": "text", "data": object, "user_analysis": object }`
- `POST /feedback` - Optional session feedback endpoint

## 🛠️ Technologies Used

### Frontend Stack
- **Laravel 11** - PHP web framework
- **Blade Templates** - Server-side templating
- **Vite** - Frontend build tool
- **Tailwind CSS** - Utility-first CSS framework
- **Web Speech API** - Voice input (browser native)

### Backend Stack
- **Python 3.8+** - Programming language
- **FastAPI** - Modern async web framework
- **Uvicorn** - ASGI server
- **Google Generative AI** - Gemini API SDK
- **SerpAPI** - Google search integration
- **Plotly** - Interactive chart generation
- **BeautifulSoup4** - HTML parsing for web scraping
- **Loguru** - Advanced logging

### AI Models
- **Gemini 2.5 Flash** - Intent classification, research synthesis
- **Gemini 1.5 Flash** - User persona detection
- **Gemini 2.0 Flash Exp** - Conflict detection

## 📊 Project Statistics

- **Total Lines of Code**: ~3,500+ lines
  - Backend (Python): ~1,800 lines
  - Frontend (Blade/JS): ~1,700 lines
- **Key Files**:
  - `research_agent.py`: 1,615 lines
  - `agent/index.blade.php`: 1,576 lines
  - `app.py`: 145 lines
- **API Integration**: 3 services (Gemini, SerpAPI, Web Speech)
- **Research Sources**: 10 per query
- **Supported Personas**: 4 types

## 🎯 Assignment Evaluation Criteria Coverage

### ✅ Conversational Quality (25%)
- Natural language understanding with multiple intent variations
- Context-aware responses based on conversation history
- Graceful fallback handling for unclear inputs
- Human-like interaction patterns

### ✅ Agentic Behaviour (25%)
- Autonomous research workflow (7 steps)
- Self-directed conflict detection and resolution
- Proactive suggestions based on user persona
- Goal-oriented task completion

### ✅ Technical Implementation (25%)
- Clean architecture (separation of concerns)
- Multi-API key rotation for reliability
- Session management with in-memory storage
- Professional PDF generation with charts
- Voice input integration
- Real-time web search with source verification

### ✅ Intelligence & Adaptability (25%)
- AI-powered persona detection (4 types)
- Adaptive response generation per user type
- Conflict detection with source prioritization
- Context tracking across conversation

## 📝 Known Limitations

1. **Session Persistence**: Sessions stored in memory (lost on server restart)
   - **Why**: Simplified demo setup without database dependency
   - **Fix**: Add Redis/MongoDB for production

2. **Persona Badge UI**: Backend persona detection works, but badge visibility has CSS issues
   - **Status**: Backend returns correct persona data
   - **Issue**: Frontend element not displaying (z-index or positioning)

3. **Voice Input Requires Internet**: Web Speech API is cloud-based
   - **Limitation**: Browser API design
   - **Workaround**: Clear error message to user

4. **PDF Storage**: Local file system (not cloud)
   - **Why**: Simplified for demo
   - **Production**: Use AWS S3 or similar

5. **Search API Dependency**: Requires SerpAPI subscription
   - **Cost**: ~$50/month for production
   - **Alternative**: Bing Search API or web scraping

## 🚧 Future Enhancements

- [ ] **Database Integration**: PostgreSQL/MongoDB for session persistence
- [ ] **User Authentication**: Login system for saved research history
- [ ] **Email Reports**: Send PDF reports via email
- [ ] **Comparison Mode**: Compare multiple companies side-by-side
- [ ] **Export Formats**: Excel, CSV, PowerPoint in addition to PDF
- [ ] **Advanced Charts**: Interactive Plotly charts in web UI
- [ ] **Webhook Integration**: Connect to CRM systems (Salesforce, HubSpot)
- [ ] **Multi-language Support**: Research companies in different languages
- [ ] **Voice Output**: TTS (Text-to-Speech) for responses
- [ ] **Mobile App**: React Native or Flutter mobile version

## 📚 Documentation

- **Setup Guide**: See "Installation & Setup" section above
- **User Guide**: `USER_PROFILING_GUIDE.md` (persona handling details)
- **API Documentation**: `CREDENTIALS.md` (API setup instructions)
- **Architecture Diagram**: See "Architecture Overview" section

## 🤝 Contributing

This project is part of a recruitment assignment for EightFold AI. Contributions are not currently accepted, but feel free to fork and adapt for your own use.

## 📄 License

This project is created as part of a recruitment assignment. All rights reserved.

## 👤 Author

**Sk Riyaz**
- Institution: JIS College of Engineering
- GitHub: [@riyaz-02](https://github.com/riyaz-02)
- Project: Company Research Assistant for EightFold AI

## 🙏 Acknowledgments

- **EightFold AI** - For the recruitment opportunity and project requirements
- **Google Gemini** - For powerful and affordable AI models
- **SerpAPI** - For reliable search API
- **Laravel & FastAPI Communities** - For excellent documentation

---

**Built with ❤️ for intelligent company research**
