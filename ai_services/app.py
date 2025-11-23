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
    response: Optional[str] = None
    messages: Optional[list] = None
    data: Optional[str] = None
    chart: Optional[str] = None
    step: Optional[str] = None
    company: Optional[str] = None
    prompt_user: Optional[bool] = False
    prompt_message: Optional[str] = None


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
            messages=result.get('messages'),
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

