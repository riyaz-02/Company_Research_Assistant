"""
FastAPI Main Application - AI Service for Company Research
"""
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
from loguru import logger
import sys

from config.settings import settings
from services.research_agent import research_agent

# Configure logging
logger.remove()
logger.add(
    sys.stderr,
    format="<green>{time:YYYY-MM-DD HH:mm:ss}</green> | <level>{level: <8}</level> | <cyan>{name}</cyan>:<cyan>{function}</cyan> | <level>{message}</level>",
    level=settings.log_level
)
logger.add(
    settings.log_file,
    rotation="100 MB",
    retention="7 days",
    level=settings.log_level
)

# Initialize FastAPI app
app = FastAPI(
    title="Company Research AI Service",
    description="AI-powered company research with charts and data visualization",
    version="2.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class MessageRequest(BaseModel):
    session_id: str
    user_message: str


class MessageResponse(BaseModel):
    success: bool
    response: str
    data: Optional[str] = None
    chart: Optional[str] = None
    step: Optional[str] = None
    company: Optional[str] = None


@app.on_event("startup")
async def startup_event():
    """Initialize services on startup"""
    logger.info("AI Research Service starting up...")


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    logger.info("AI Research Service shutting down...")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "company-research-ai",
        "version": "2.0.0"
    }


@app.post("/research/message", response_model=MessageResponse)
async def handle_message(request: MessageRequest):
    """
    Handle user message and perform research
    """
    try:
        logger.info(f"Processing message for session {request.session_id}")
        
        result = research_agent.handle_message(request.session_id, request.user_message)
        
        return MessageResponse(
            success=result.get('success', True),
            response=result.get('response', ''),
            data=result.get('data'),
            chart=result.get('chart'),
            step=result.get('step'),
            company=result.get('company')
        )
    
    except Exception as e:
        logger.error(f"Message handling error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))




if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app:app",
        host=settings.host,
        port=settings.port,
        workers=settings.workers,
        reload=False
    )


@app.post("/synthesize-section", response_model=SynthesizeSectionResponse)
async def synthesize_section(request: SynthesizeSectionRequest):
    """
    Synthesize a research section from raw search results
    
    This endpoint takes raw SerpAPI/Bing results and synthesizes them into
    clean, professional content using Gemini AI.
    """
    try:
        logger.info(f"Synthesizing section: {request.step} for session {request.session_id}")
        
        # Check cache first
        cached = cache.get_synthesis(request.session_id, request.step)
        if cached:
            logger.info("Using cached synthesis")
            return SynthesizeSectionResponse(**cached)
        
        # Perform synthesis
        result = await synthesizer.synthesize_section(
            step=request.step,
            search_results=request.raw_search_results,
            company_name=request.company_name
        )
        
        # Map step to section name
        section_map = {
            'company_basics': 'company_overview',
            'financial': 'financial_overview',
            'products_tech': 'products_services',
            'competitors': 'competitive_landscape',
            'pain_points': 'pain_points',
            'recommendations': 'recommendations',
            'final_plan': 'executive_summary'
        }
        
        response = SynthesizeSectionResponse(
            action="update_plan",
            section=section_map.get(request.step, request.step),
            content=result['content'],
            evidence=result['evidence'],
            needs_retry=result['needs_retry'],
            progress_message=f"Completed {request.step}"
        )
        
        # Cache the result
        cache.set_synthesis(request.session_id, request.step, response.model_dump())
        
        return response
    
    except Exception as e:
        logger.error(f"Synthesis error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/detect-conflicts", response_model=DetectConflictsResponse)
async def detect_conflicts(request: DetectConflictsRequest):
    """
    Detect conflicts between current and previous data
    
    Analyzes numeric and textual data to identify conflicting information
    that requires user clarification.
    """
    try:
        logger.info(f"Detecting conflicts for session {request.session_id}, step {request.step}")
        
        current_content = request.current_data.get('content', '')
        previous_content = request.previous_data.get('content', '')
        
        if not current_content or not previous_content:
            return DetectConflictsResponse(
                action="continue",
                conflicts=[],
                question=None,
                buttons=[]
            )
        
        # Detect conflicts
        conflicts = conflict_detector.detect_conflicts(
            current_content=current_content,
            previous_content=previous_content,
            step_name=request.step
        )
        
        if conflicts:
            question = conflict_detector.format_conflict_question(conflicts)
            buttons = conflict_detector.create_conflict_buttons(conflicts)
            
            return DetectConflictsResponse(
                action="ask_user",
                conflicts=conflicts,
                question=question,
                buttons=buttons
            )
        
        return DetectConflictsResponse(
            action="continue",
            conflicts=[],
            question=None,
            buttons=[]
        )
    
    except Exception as e:
        logger.error(f"Conflict detection error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/process-step", response_model=ProcessStepResponse)
async def process_step(request: ProcessStepRequest):
    """
    Process a complete research step: synthesize + detect conflicts
    
    This is a convenience endpoint that combines synthesis and conflict detection.
    """
    try:
        logger.info(f"Processing step {request.step} for session {request.session_id}")
        
        # Synthesize content
        synthesis_result = await synthesizer.synthesize_section(
            step=request.step,
            search_results=request.search_results,
            company_name=request.company_name
        )
        
        # Map section name
        section_map = {
            'company_basics': 'company_overview',
            'financial': 'financial_overview',
            'products_tech': 'products_services',
            'competitors': 'competitive_landscape',
            'pain_points': 'pain_points',
            'recommendations': 'recommendations'
        }
        
        response = ProcessStepResponse(
            action="update_plan",
            section=section_map.get(request.step, request.step),
            content=synthesis_result['content'],
            evidence=synthesis_result['evidence'],
            needs_retry=synthesis_result['needs_retry'],
            conflicts=[],
            progress_message=f"Completed {request.step}"
        )
        
        return response
    
    except Exception as e:
        logger.error(f"Step processing error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/generate-final-plan", response_model=GenerateFinalPlanResponse)
async def generate_final_plan(request: GenerateFinalPlanRequest):
    """
    Generate final executive summary from all sections
    
    Creates a comprehensive account plan by synthesizing all completed sections.
    """
    try:
        logger.info(f"Generating final plan for session {request.session_id}")
        
        summary = await synthesizer.generate_final_plan(
            company_name=request.company_name,
            all_sections=request.all_sections
        )
        
        return GenerateFinalPlanResponse(
            action="finish",
            section="executive_summary",
            content=summary,
            progress_message="Account plan complete!"
        )
    
    except Exception as e:
        logger.error(f"Final plan generation error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/clean-text", response_model=CleanTextResponse)
async def clean_text_endpoint(request: CleanTextRequest):
    """
    Clean and format text
    
    Utility endpoint for text cleaning, whitespace normalization, and formatting.
    """
    try:
        cleaned = normalize_whitespace(request.text)
        
        if request.max_length and len(cleaned) > request.max_length:
            cleaned = clean_snippet(cleaned, request.max_length)
        
        return CleanTextResponse(
            cleaned_text=cleaned,
            original_length=len(request.text),
            cleaned_length=len(cleaned)
        )
    
    except Exception as e:
        logger.error(f"Text cleaning error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/clear-cache/{session_id}")
async def clear_session_cache(session_id: str):
    """Clear all cache for a specific session"""
    try:
        cache.clear_session(session_id)
        return {"status": "success", "message": f"Cache cleared for session {session_id}"}
    except Exception as e:
        logger.error(f"Cache clear error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/process-messages", response_model=ProcessMessagesResponse)
async def process_messages(request: ProcessMessagesRequest):
    """
    Process raw messages for agent LLM calls
    
    Accepts standard message format and returns action/response suitable
    for the conversational agent.
    """
    try:
        logger.info(f"Processing {len(request.messages)} messages for session {request.session_id}")
        
        # Check cache
        import hashlib
        cache_key = hashlib.md5(str(request.messages).encode()).hexdigest()
        cached = cache.get_analysis(request.session_id, cache_key)
        if cached:
            logger.info("Using cached message response")
            return ProcessMessagesResponse(**cached)
        
        # Build prompt from messages
        prompt_parts = []
        for msg in request.messages:
            role = msg.get('role', 'user')
            content = msg.get('content', '')
            if role == 'system':
                prompt_parts.append(f"SYSTEM: {content}")
            elif role == 'assistant':
                prompt_parts.append(f"ASSISTANT: {content}")
            elif role == 'tool':
                prompt_parts.append(f"TOOL OUTPUT: {content}")
            else:
                prompt_parts.append(f"USER: {content}")
        
        full_prompt = "\n\n".join(prompt_parts)
        full_prompt += "\n\nRespond with a JSON object containing an 'action' field and any relevant data."
        
        # Call Gemini using call_with_retry
        from services.gemini_client import gemini_client
        gemini_response = await gemini_client.call_with_retry(full_prompt, temperature=0.7)
        response_text = gemini_response.text
        
        # Try to parse as JSON
        import json
        try:
            # Clean markdown code blocks
            cleaned = response_text.strip()
            if cleaned.startswith('```'):
                cleaned = cleaned.split('```')[1]
                if cleaned.startswith('json'):
                    cleaned = cleaned[4:]
                cleaned = cleaned.strip()
            
            result = json.loads(cleaned)
            
            response = ProcessMessagesResponse(
                action=result.get('action', 'error'),
                content=result.get('content'),
                question=result.get('question'),
                params=result.get('params')
            )
            
            # Cache result
            cache.store_analysis(request.session_id, cache_key, response.model_dump())
            
            return response
            
        except json.JSONDecodeError:
            logger.warning(f"Could not parse response as JSON: {response_text[:200]}")
            # Return as content
            return ProcessMessagesResponse(
                action='respond',
                content=response_text
            )
        
    except Exception as e:
        logger.error(f"Process messages error: {e}", exc_info=True)
        
        # Return user-friendly error for rate limits
        if "rate limit" in str(e).lower() or "429" in str(e):
            return ProcessMessagesResponse(
                action='ask_user',
                question='The AI service is currently experiencing high demand. Please wait about 60 seconds before trying again.',
                content=None,
                params=None
            )
        
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/interpret-user-intent", response_model=InterpretIntentResponse)
async def interpret_user_intent(request: InterpretIntentRequest):
    """
    Interpret user intent BEFORE taking any action
    
    CRITICAL: This prevents the system from treating every message as a company name.
    Uses Gemini to classify user intent with proper context awareness.
    """
    try:
        logger.info(f"Interpreting intent for message: {request.user_message[:50]}...")
        
        # Check cache
        import hashlib
        cache_key = hashlib.md5(f"{request.user_message}_{request.context}".encode()).hexdigest()
        cached = cache.get_analysis(request.session_id, cache_key)
        if cached:
            logger.info("Using cached intent interpretation")
            return InterpretIntentResponse(**cached)
        
        # Build context
        step_mode = request.context.get('step_mode', False)
        current_step = request.context.get('current_step', 'none')
        company_name = request.context.get('company_name', '')
        
        # Build intent classification prompt
        prompt = f"""You are an intelligent intent classifier for a company research assistant.

CURRENT CONTEXT:
- Step mode active: {step_mode}
- Current step: {current_step}
- Company being researched: {company_name if company_name else "None yet"}

USER MESSAGE: "{request.user_message}"

CRITICAL TASK: Classify the user's intent BEFORE doing anything else. DO NOT assume every message is a company name!

INTENT TYPES (choose ONE):

1. **start_research** - User wants to start NEW research on a company
   - Examples: "tcs", "research Microsoft", "analyze Apple", "tell me about Google"
   - Extract company name if present
   - If message is just 1-5 words and looks like a company, assume start_research

2. **change_company** - User wants to CHANGE to different company (while already researching)
   - Examples: "let's do IBM instead", "change to Oracle", "switch company"
   - Only applies when already in step_mode with existing company

3. **continue_research** - User wants to CONTINUE current research (affirmative)
   - Examples: "yes", "ok", "continue", "proceed", "sure", "go ahead", "yes continue"
   - Only applies in step_mode

4. **off_topic_personal** - User talking about PERSONAL matters (health, feelings, emotions)
   - Examples: "i am not feeling well", "i'm sad", "i have a headache", "i'm tired"
   - DO NOT treat these as company names!
   - Requires empathetic response

5. **off_topic_general** - General questions NOT about research
   - Examples: "what's the weather?", "tell me a joke", "how are you?", "what can you do?"
   - DO NOT treat these as company names!

6. **request_help** - User is confused or needs guidance
   - Examples: "help", "what should i do?", "i don't understand", "explain"

7. **reject** - User wants to STOP or say NO
   - Examples: "no", "stop", "cancel", "quit", "no thanks", "don't proceed"

8. **unclear** - Cannot determine intent with confidence
   - Use when message is ambiguous

9. **other** - Any other intent not covered above

RESPONSE FORMAT (JSON):
{{
  "intent": "<one of: start_research, change_company, continue_research, off_topic_personal, off_topic_general, request_help, reject, unclear, other>",
  "company_extracted": "<company name if intent is start_research or change_company, otherwise empty string>",
  "confidence": <0.0 to 1.0>,
  "explanation": "<brief explanation of what you understood>"
}}

CRITICAL RULES:
- If message mentions health/feelings/emotions → off_topic_personal (NOT a company!)
- If message is general question → off_topic_general (NOT a company!)
- Only extract company_name if intent is start_research or change_company
- Short phrases (1-5 words) that look like company names → start_research
- When in doubt between company name and personal message, check for emotional words

EXAMPLES:
Input: "i am not feeling well"
Output: {{"intent": "off_topic_personal", "company_extracted": "", "confidence": 0.95, "explanation": "User expressing personal health concern"}}

Input: "tcs"
Output: {{"intent": "start_research", "company_extracted": "tcs", "confidence": 0.9, "explanation": "Short phrase likely a company name"}}

Input: "ok continue"
Output: {{"intent": "continue_research", "company_extracted": "", "confidence": 0.95, "explanation": "User wants to proceed with current research"}}

Now classify this message: "{request.user_message}"
"""
        
        # Call Gemini with LOW temperature for consistent classification
        from services.gemini_client import gemini_client
        response = await gemini_client.call_with_retry(prompt, temperature=0.3, use_cache=False)
        
        # Parse JSON response
        import json
        response_text = response.text.strip()
        
        # Clean markdown code blocks if present
        if response_text.startswith('```'):
            response_text = response_text.split('```')[1]
            if response_text.startswith('json'):
                response_text = response_text[4:]
            response_text = response_text.strip()
        
        try:
            parsed = json.loads(response_text)
            result = InterpretIntentResponse(
                intent=parsed.get('intent', 'unclear'),
                confidence=float(parsed.get('confidence', 0.5)),
                explanation=parsed.get('explanation', 'Could not determine intent'),
                suggested_response=parsed.get('company_extracted', '')  # Store company in suggested_response field
            )
            
            # Cache result
            cache.store_analysis(request.session_id, cache_key, result.model_dump())
            
            logger.info(f"Intent classified: {result.intent} (company: {parsed.get('company_extracted', 'none')}, confidence: {result.confidence})")
            return result
            
        except json.JSONDecodeError:
            logger.warning(f"Could not parse JSON response: {response_text[:200]}")
            
            # Fallback: Basic classification
            lower = request.user_message.lower().strip()
            
            # Check for personal/emotional keywords
            if any(word in lower for word in ['feeling', 'sick', 'ill', 'tired', 'sad', 'headache', 'pain', 'unwell']):
                return InterpretIntentResponse(
                    intent='off_topic_personal',
                    confidence=0.7,
                    explanation="Detected personal/health topic",
                    suggested_response=""
                )
            
            # Check for affirmative
            if any(word in lower for word in ['yes', 'ok', 'continue', 'proceed', 'sure']):
                return InterpretIntentResponse(
                    intent='continue_research',
                    confidence=0.6,
                    explanation="Affirmative response detected",
                    suggested_response=""
                )
            
            # Check for negative
            if any(word in lower for word in ['no', 'stop', 'cancel', 'quit']):
                return InterpretIntentResponse(
                    intent='reject',
                    confidence=0.6,
                    explanation="Negative response detected",
                    suggested_response=""
                )
            
            # Short phrase might be company
            words = request.user_message.split()
            if len(words) <= 5 and not any(word in lower for word in ['the', 'what', 'how', 'why', 'when']):
                return InterpretIntentResponse(
                    intent='start_research',
                    confidence=0.5,
                    explanation="Short phrase, possibly company name",
                    suggested_response=request.user_message.strip()
                )
            
            return InterpretIntentResponse(
                intent='unclear',
                confidence=0.3,
                explanation="Could not parse AI response",
                suggested_response=""
            )
        
    except Exception as e:
        logger.error(f"Intent interpretation error: {e}", exc_info=True)
        # Return unclear intent on error
        return InterpretIntentResponse(
            intent='unclear',
            confidence=0.0,
            explanation=f"Error occurred: {str(e)}",
            suggested_response=""
        )


@app.post("/analyze", response_model=AnalyzeDataResponse)
async def analyze_data(request: AnalyzeDataRequest):
    """
    Analyze data with AI (for synthesis and analysis calls)
    
    General-purpose analysis endpoint that returns plain text responses.
    """
    try:
        logger.info(f"Analyzing data for session {request.session_id}")
        
        # Check cache
        import hashlib
        cache_key = hashlib.md5(f"{request.prompt}:{request.context}".encode()).hexdigest()
        cached = cache.get_analysis(request.session_id, cache_key)
        if cached:
            logger.info("Using cached analysis")
            return AnalyzeDataResponse(**cached)
        
        # Build full prompt
        full_prompt = request.prompt
        if request.context:
            full_prompt = f"{request.prompt}\n\nContext:\n{request.context}"
        
        # Call Gemini using call_with_retry
        from services.gemini_client import gemini_client
        gemini_response = await gemini_client.call_with_retry(full_prompt, temperature=0.7)
        
        response = AnalyzeDataResponse(
            content=gemini_response.text,
            sources=["AI Analysis"]
        )
        
        # Cache result
        cache.store_analysis(request.session_id, cache_key, response.model_dump())
        
        return response
        
    except Exception as e:
        logger.error(f"Analyze data error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/agentic-conversation")
async def agentic_conversation(request: dict):
    """
    Fully autonomous agentic AI conversation endpoint.
    Handles ANY user message naturally without hardcoded logic.
    
    Request body:
    {
        "user_message": "What the user said",
        "session_id": "session123",
        "context": {
            "current_step": "company_basics",
            "last_content": "...",
            "conversation_history": [...],
            "company_name": "...",
            "research_progress": {...}
        }
    }
    
    Returns:
    {
        "action": "continue|stop|deep_research|next_step|retry|chat|provide_info|ask_question",
        "response": "Natural AI response",
        "reasoning": "Why this decision was made",
        "should_proceed": bool,
        "metadata": {...}
    }
    """
    try:
        user_message = request.get("user_message", "")
        session_id = request.get("session_id", "")
        context = request.get("context", {})
        
        if not user_message or not session_id:
            raise HTTPException(status_code=400, detail="user_message and session_id are required")
        
        # Import here to avoid circular dependencies
        from services.gemini_client import gemini_client
        
        # Create the autonomous agent
        agent = ConversationAgent(gemini_client)
        
        # Let the agent process the message and make ALL decisions
        decision = await agent.process_message(user_message, session_id, context)
        
        return decision
        
    except Exception as e:
        print(f"Agentic conversation error: {str(e)}")
        import traceback
        traceback.print_exc()
        # Even in error, respond naturally
        return {
            "action": "chat",
            "response": "I apologize, I'm having a bit of trouble processing that right now. Could you rephrase what you'd like me to do?",
            "reasoning": "System error occurred",
            "should_proceed": False,
            "metadata": {"error": str(e)}
        }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app:app",
        host=settings.host,
        port=settings.port,
        workers=settings.workers,
        reload=False
    )
