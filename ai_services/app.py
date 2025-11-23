"""
FastAPI Main Application - AI Service for Company Research
"""
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from loguru import logger
import sys

from config.settings import settings
from models.schemas import (
    SynthesizeSectionRequest, SynthesizeSectionResponse,
    DetectConflictsRequest, DetectConflictsResponse,
    ProcessStepRequest, ProcessStepResponse,
    GenerateFinalPlanRequest, GenerateFinalPlanResponse,
    CleanTextRequest, CleanTextResponse,
    ProcessMessagesRequest, ProcessMessagesResponse,
    AnalyzeDataRequest, AnalyzeDataResponse,
    ErrorResponse
)
from services.synthesizer import synthesizer
from services.conflict_detector import conflict_detector
from services.cache import cache
from utils.text_cleaner import clean_snippet, normalize_whitespace

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
    description="AI-powered synthesis, conflict detection, and content generation for company research",
    version="1.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure appropriately for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.on_event("startup")
async def startup_event():
    """Initialize services on startup"""
    logger.info("AI Service starting up...")
    logger.info(f"Settings: {settings.model_dump()}")


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    logger.info("AI Service shutting down...")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "company-research-ai",
        "version": "1.0.0"
    }


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


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app:app",
        host=settings.host,
        port=settings.port,
        workers=settings.workers,
        reload=False
    )
