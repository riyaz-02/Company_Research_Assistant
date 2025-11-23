"""
Fully Agentic Conversation Agent
Handles all user interactions naturally using Gemini AI
No hardcoded patterns, no forced buttons - pure human-like conversation
"""

import logging
from typing import Dict, Any, Optional, List
from services.gemini_client import GeminiClient
from services.cache import cache
import hashlib
import json

logger = logging.getLogger(__name__)


class ConversationAgent:
    """
    Fully autonomous AI agent that handles ALL user messages naturally.
    Makes decisions about research flow, handles off-topic conversations,
    and responds with empathy and understanding.
    """
    
    def __init__(self, gemini_client: GeminiClient):
        self.gemini = gemini_client
    
    async def process_message(
        self,
        user_message: str,
        session_id: str,
        context: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Process ANY user message and decide what to do naturally.
        
        Returns:
            {
                "action": "continue|stop|deep_research|next_step|retry|chat|provide_info|ask_question",
                "response": "Natural conversational response",
                "reasoning": "Why the agent made this decision",
                "should_proceed": bool,  # Should the system take action?
                "metadata": {}  # Any additional data for PHP to use
            }
        """
        
        # Build comprehensive context for the AI agent
        prompt = self._build_agent_prompt(user_message, context)
        
        # Check cache first
        cache_key = self._get_cache_key(user_message, context)
        cached_str = cache.get(cache_key)
        if cached_str:
            try:
                cached = json.loads(cached_str)
                if cached and isinstance(cached, dict):
                    logger.info(f"Using cached agent decision for session {session_id}")
                    return cached
            except (json.JSONDecodeError, TypeError):
                pass  # Invalid cache, continue to generate new response
        
        try:
            # Let Gemini make ALL decisions autonomously
            response = await self.gemini.call_with_retry(
                prompt,
                temperature=0.7,  # Higher temp for more human-like responses
                use_cache=True
            )
            
            # Parse the agent's decision
            decision = self._parse_agent_response(response.text)

            # Cache the decision if valid
            if decision and isinstance(decision, dict):
                try:
                    cache.set(cache_key, json.dumps(decision), ttl=300)  # 5 min cache
                except (TypeError, ValueError):
                    pass  # Failed to cache, but continue

                logger.info(f"Agent decision for session {session_id}: action={decision.get('action')}, reasoning={decision.get('reasoning')}")

                return decision
            
            # If parsing failed, return error response
            logger.warning(f"Failed to parse agent response for session {session_id}")
            return {
                "action": "chat",
                "response": "I apologize, I'm having trouble understanding my own thoughts right now. Could you rephrase that?",
                "reasoning": "Failed to parse AI response",
                "should_proceed": False,
                "metadata": {}
            }            # If parsing failed, return error response
            logger.warning(f"Failed to parse agent response for session {session_id}")
            return {
                "action": "chat",
                "response": "I apologize, I'm having trouble understanding my own thoughts right now. Could you rephrase that?",
                "reasoning": "Failed to parse AI response",
                "should_proceed": False,
                "metadata": {}
            }
            
        except Exception as e:
            logger.error(f"Agent processing failed: {str(e)}")
            # Even in error, respond naturally
            return {
                "action": "chat",
                "response": "I apologize, I'm having a moment of difficulty processing that. Could you rephrase what you'd like me to do?",
                "reasoning": "System error - requesting clarification",
                "should_proceed": False,
                "metadata": {"error": str(e)}
            }
    
    def _build_agent_prompt(self, user_message: str, context: Dict[str, Any]) -> str:
        """Build the comprehensive prompt for the autonomous agent"""
        
        # Extract context
        current_step = context.get('current_step', 'not started')
        step_name = current_step.replace('_', ' ').title()
        last_content = context.get('last_content', 'No data yet')[:500]
        conversation_history = context.get('conversation_history', [])
        company_name = context.get('company_name', 'the company')
        research_progress = context.get('research_progress', {})
        
        # Build conversation context
        conv_context = ""
        for msg in conversation_history[-5:]:  # Last 5 messages
            role = msg.get('role', 'unknown').title()
            content = msg.get('content', '')[:200]
            conv_context += f"{role}: {content}\n"
        
        # Build the agent prompt
        prompt = f"""You are an autonomous AI research assistant named ResearchBot. You're having a natural, empathetic conversation with a user about researching {company_name}.

CURRENT SITUATION:
- Research Stage: {step_name}
- Last Generated Content Summary: {last_content}
- Progress: {json.dumps(research_progress, indent=2)}

RECENT CONVERSATION:
{conv_context}

USER'S LATEST MESSAGE:
"{user_message}"

YOUR ROLE AS AN AUTONOMOUS AGENT:
You need to understand what the user wants and respond naturally like a helpful human colleague would. The user might:

1. **Want to continue** with the next research step
   - Examples: "yes", "continue", "go ahead", "yes please", "let's move on", "sure thing"
   
2. **Want to stop or pause** the research
   - Examples: "no", "stop", "not now", "halt", "let me think", "pause"
   
3. **Want deeper research** on current topic
   - Examples: "tell me more", "dig deeper", "I need more details", "deep research"
   
4. **Want to skip** to next section
   - Examples: "skip this", "next", "move to next section", "skip ahead"
   
5. **Want to retry/regenerate** current analysis
   - Examples: "try again", "redo this", "regenerate", "that's not good enough"
   
6. **Going off-topic or sharing personal context**
   - Examples: "I'm not feeling well, can you give me the summary first?"
   - Examples: "I need to show this to my boss before continuing"
   - Examples: "Let me check with my team"
   - Examples: "What sources did you use?"
   - Examples: "Can you explain how this works?"
   
7. **Asking questions** about the process, data, or methodology
   - Examples: "Why did you conclude that?", "What sources?", "How reliable is this?"

YOUR RESPONSE MUST BE IN THIS EXACT JSON FORMAT:
{{
  "action": "<continue|stop|deep_research|next_step|retry|chat|provide_info|ask_question>",
  "response": "<Your natural, warm, conversational response to the user>",
  "reasoning": "<Brief internal explanation of what you understood>",
  "should_proceed": <true|false>,
  "metadata": {{
    "user_intent_confidence": "<high|medium|low>",
    "emotional_tone": "<neutral|concerned|excited|frustrated|curious>",
    "needs_clarification": <true|false>
  }}
}}

ACTION MEANINGS:
- "continue": User wants to proceed to next research step → should_proceed: true
- "stop": User wants to pause/stop → should_proceed: false
- "deep_research": User wants more detailed analysis of current topic → should_proceed: true
- "next_step": User wants to skip current and move to next → should_proceed: true
- "retry": User wants to regenerate current analysis → should_proceed: true
- "chat": Pure conversation, no action yet (off-topic, questions, etc.) → should_proceed: false
- "provide_info": User asking for information about process/data → should_proceed: false
- "ask_question": Agent needs clarification from user → should_proceed: false

CRITICAL GUIDELINES FOR HUMAN-LIKE RESPONSES:
1. **Be genuinely empathetic and warm** - respond like a caring colleague, not a robot
2. **Acknowledge emotions and context** - if user says they're unwell, show genuine concern
3. **Don't rush the user** - if they need time or information first, support that
4. **Be conversational** - use contractions, natural language, show personality
5. **Adapt your tone** - match the user's emotional state
6. **Provide value in every response** - give summaries, insights, or helpful context
7. **Never force choices** - understand natural human language and intent
8. **BE BRIEF AND DIRECT** - Keep responses to 1-2 sentences maximum unless providing a summary
9. **Avoid repetition** - Don't repeat information the user already knows

EXAMPLE RESPONSES:

User: "I'm not feeling well, just give me what you have so far"
Response:
{{
  "action": "provide_info",
  "response": "I'm sorry you're not feeling well! Here's what we found so far: [brief 1-2 sentence summary of last_content]. Let me know when you're ready to continue - take care!",
  "reasoning": "User is unwell but needs current information before deciding next steps",
  "should_proceed": false,
  "metadata": {{
    "user_intent_confidence": "high",
    "emotional_tone": "concerned",
    "needs_clarification": false
  }}
}}

User: "yes please continue"
Response:
{{
  "action": "continue",
  "response": "Got it! Moving to the next research step now.",
  "reasoning": "User clearly wants to proceed to next research phase",
  "should_proceed": true,
  "metadata": {{
    "user_intent_confidence": "high",
    "emotional_tone": "neutral",
    "needs_clarification": false
  }}
}}

User: "hmm not sure, what do you think?"
Response:
{{
  "action": "chat",
  "response": "Based on what we have, we could either continue to the next topic or dive deeper here. What would you prefer?",
  "reasoning": "User is uncertain and seeking guidance",
  "should_proceed": false,
  "metadata": {{
    "user_intent_confidence": "low",
    "emotional_tone": "curious",
    "needs_clarification": true
  }}
}}

Now, respond to the user's message naturally and authentically. Be the best AI research assistant they've ever worked with!"""
        
        return prompt
    
    def _parse_agent_response(self, response: str) -> Dict[str, Any]:
        """Parse the agent's JSON response"""
        
        # Clean up the response
        cleaned = response.strip()
        
        # Remove markdown code blocks if present
        if "```json" in cleaned:
            cleaned = cleaned.split("```json")[1].split("```")[0].strip()
        elif "```" in cleaned:
            cleaned = cleaned.split("```")[1].split("```")[0].strip()
        
        # Extract JSON
        if "{" in cleaned and "}" in cleaned:
            start = cleaned.index("{")
            end = cleaned.rindex("}") + 1
            json_str = cleaned[start:end]
            
            try:
                parsed = json.loads(json_str)
                
                # Validate required fields
                if not all(k in parsed for k in ["action", "response", "reasoning"]):
                    raise ValueError("Missing required fields in agent response")
                
                # Set defaults
                parsed.setdefault("should_proceed", parsed["action"] in ["continue", "deep_research", "next_step", "retry"])
                parsed.setdefault("metadata", {})
                
                return parsed
                
            except json.JSONDecodeError as e:
                logger.error(f"Failed to parse agent JSON: {e}\nContent: {json_str[:200]}")
                raise
        
        raise ValueError(f"No valid JSON found in agent response: {cleaned[:200]}")
    
    def _get_cache_key(self, user_message: str, context: Dict[str, Any]) -> str:
        """Generate cache key for agent decisions"""
        # Include message + current step + last bit of content
        cache_input = f"{user_message}:{context.get('current_step')}:{context.get('last_content', '')[:100]}"
        return f"agent_decision:{hashlib.md5(cache_input.encode()).hexdigest()}"
