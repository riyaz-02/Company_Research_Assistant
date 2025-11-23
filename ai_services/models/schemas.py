"""
Pydantic Models for Request/Response Validation
"""
from typing import List, Optional, Dict, Any, Literal
from pydantic import BaseModel, Field


# ============================================================================
# SHARED MODELS
# ============================================================================

class Evidence(BaseModel):
    """Evidence/source citation"""
    source: str = Field(..., description="Source name or domain")
    url: str = Field(..., description="Source URL")
    snippet: str = Field(..., description="Text snippet from source")


class Button(BaseModel):
    """UI button for user interaction"""
    text: str = Field(..., description="Button label")
    value: str = Field(..., description="Button action value")


# ============================================================================
# REQUEST MODELS
# ============================================================================

class SynthesizeSectionRequest(BaseModel):
    """Request to synthesize a research section"""
    session_id: str = Field(..., description="User session ID")
    step: str = Field(..., description="Step name (e.g., 'company_basics', 'financial')")
    raw_search_results: List[Dict[str, Any]] = Field(..., description="Raw SerpAPI/Bing results")
    previous_sections: Optional[Dict[str, str]] = Field(default=None, description="Previously completed sections")
    company_name: str = Field(..., description="Company being researched")


class DetectConflictsRequest(BaseModel):
    """Request to detect data conflicts"""
    session_id: str = Field(..., description="User session ID")
    step: str = Field(..., description="Current step name")
    current_data: Dict[str, Any] = Field(..., description="Current step data")
    previous_data: Dict[str, Any] = Field(..., description="Previous step data")


class ProcessStepRequest(BaseModel):
    """Request to process a complete research step"""
    session_id: str = Field(..., description="User session ID")
    step: str = Field(..., description="Step name")
    company_name: str = Field(..., description="Company name")
    search_results: List[Dict[str, Any]] = Field(..., description="Search results")
    previous_sections: Optional[Dict[str, str]] = Field(default=None, description="Previous sections")
    user_selections: Optional[Dict[str, Any]] = Field(default=None, description="User conflict resolutions")


class GenerateFinalPlanRequest(BaseModel):
    """Request to generate final account plan"""
    session_id: str = Field(..., description="User session ID")
    company_name: str = Field(..., description="Company name")
    all_sections: Dict[str, str] = Field(..., description="All completed sections")


class CleanTextRequest(BaseModel):
    """Request to clean and format text"""
    text: str = Field(..., description="Text to clean")
    max_length: Optional[int] = Field(default=None, description="Max length after cleaning")


class ProcessMessagesRequest(BaseModel):
    """Request to process raw messages (for agent calls)"""
    messages: List[Dict[str, str]] = Field(..., description="Array of message objects with role and content")
    session_id: str = Field(default="default", description="Session ID for caching")


class AnalyzeDataRequest(BaseModel):
    """Request to analyze data with AI"""
    prompt: str = Field(..., description="Analysis prompt")
    context: str = Field(default="", description="Additional context")
    session_id: str = Field(default="default", description="Session ID for caching")


# ============================================================================
# RESPONSE MODELS
# ============================================================================

class SynthesizeSectionResponse(BaseModel):
    """Response from section synthesis"""
    action: Literal["update_plan", "ask_user", "error"] = Field(..., description="Action type")
    section: str = Field(..., description="Section name (e.g., 'company_overview')")
    content: str = Field(..., description="Synthesized content")
    evidence: List[Evidence] = Field(default_factory=list, description="Evidence sources")
    needs_retry: bool = Field(default=False, description="Whether synthesis failed and needs retry")
    progress_message: Optional[str] = Field(default=None, description="Progress update")


class ConflictValue(BaseModel):
    """A conflicting value"""
    label: str = Field(..., description="Human-readable label")
    value: Any = Field(..., description="Actual value")
    source: Optional[str] = Field(default=None, description="Source of this value")


class Conflict(BaseModel):
    """Detected conflict"""
    field: str = Field(..., description="Field with conflict (e.g., 'revenue', 'employees')")
    question: str = Field(..., description="User-facing question")
    values: List[ConflictValue] = Field(..., description="Conflicting values")
    current: str = Field(..., description="Current full content")
    previous: str = Field(..., description="Previous full content")


class DetectConflictsResponse(BaseModel):
    """Response from conflict detection"""
    action: Literal["ask_user", "continue", "error"] = Field(..., description="Action type")
    conflicts: List[Conflict] = Field(default_factory=list, description="Detected conflicts")
    question: Optional[str] = Field(default=None, description="Formatted conflict question")
    buttons: List[Button] = Field(default_factory=list, description="Action buttons")


class ProcessStepResponse(BaseModel):
    """Response from full step processing"""
    action: Literal["update_plan", "ask_user", "show_progress", "error"] = Field(..., description="Action type")
    section: Optional[str] = Field(default=None, description="Section updated")
    content: Optional[str] = Field(default=None, description="Section content")
    evidence: List[Evidence] = Field(default_factory=list, description="Evidence sources")
    conflicts: List[Conflict] = Field(default_factory=list, description="Any detected conflicts")
    question: Optional[str] = Field(default=None, description="Question for user")
    buttons: List[Button] = Field(default_factory=list, description="Action buttons")
    progress_message: Optional[str] = Field(default=None, description="Progress message")
    needs_retry: bool = Field(default=False, description="Whether to show retry button")


class GenerateFinalPlanResponse(BaseModel):
    """Response from final plan generation"""
    action: Literal["finish", "error"] = Field(..., description="Action type")
    section: str = Field(default="executive_summary", description="Section name")
    content: str = Field(..., description="Executive summary")
    progress_message: str = Field(default="Account plan complete!", description="Completion message")


class CleanTextResponse(BaseModel):
    """Response from text cleaning"""
    cleaned_text: str = Field(..., description="Cleaned text")
    original_length: int = Field(..., description="Original text length")
    cleaned_length: int = Field(..., description="Cleaned text length")


class ProcessMessagesResponse(BaseModel):
    """Response from processing messages"""
    action: str = Field(..., description="Action to take")
    content: Optional[str] = Field(default=None, description="Response content")
    question: Optional[str] = Field(default=None, description="Question for user")
    params: Optional[Dict[str, Any]] = Field(default=None, description="Action parameters")


class InterpretIntentRequest(BaseModel):
    """Request to interpret user intent using AI"""
    user_message: str = Field(..., description="User's natural language message")
    context: Dict[str, Any] = Field(default_factory=dict, description="Context about current conversation state")
    session_id: str = Field(default="default", description="Session ID for caching")


class InterpretIntentResponse(BaseModel):
    """Response from intent interpretation"""
    intent: str = Field(..., description="Detected intent: yes, no, next_step, deep_research, retry_synthesis, or unclear")
    confidence: float = Field(..., description="Confidence score 0-1")
    explanation: str = Field(..., description="Human-readable explanation of what AI understood")
    suggested_response: Optional[str] = Field(default=None, description="Suggested conversational response to user")


class AnalyzeDataResponse(BaseModel):
    """Response from data analysis"""
    content: str = Field(..., description="Analysis result")
    sources: List[str] = Field(default_factory=lambda: ["AI Analysis"], description="Data sources")


class ErrorResponse(BaseModel):
    """Error response"""
    action: Literal["error"] = "error"
    error: str = Field(..., description="Error message")
    detail: Optional[str] = Field(default=None, description="Error details")
    retry_available: bool = Field(default=False, description="Whether retry is possible")


# ============================================================================
# INTERNAL MODELS
# ============================================================================

class GeminiRequest(BaseModel):
    """Internal Gemini API request"""
    prompt: str
    temperature: float = 0.7
    max_output_tokens: int = 2048


class GeminiResponse(BaseModel):
    """Internal Gemini API response"""
    text: str
    cached: bool = False
