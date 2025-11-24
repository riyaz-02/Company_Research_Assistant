"""
AI Company Research Agent
Handles all research logic, intent classification, and data processing
"""
import os
import json
import requests
from typing import Dict, List, Optional, Any
from loguru import logger
import pandas as pd
import plotly.graph_objects as go
import plotly.express as px
from datetime import datetime
from bs4 import BeautifulSoup
from services.pdf_generator import pdf_generator

class ResearchAgent:
    """Main research agent using Gemini for all decisions"""
    
    SYSTEM_PROMPT = """You are an intelligent Company Research Assistant.
Your job is to guide the user through natural conversation, understand their intent, detect company names, avoid wrong assumptions, and respond in a clean structured JSON format.

Always behave human-like, empathetic, and helpful.
Do NOT assume a company unless the user explicitly provides one.

### INTENT TYPES
Classify the message into ONE of the following intents:
- greeting
- off_topic_personal
- off_topic_general
- provide_company
- continue_research (includes: "next", "continue", "proceed", "next section", "go ahead")
- deeper_analysis (includes: "deeper analysis", "deep search", "more details", "elaborate")
- regenerate_section (includes: "regenerate", "redo", "refresh section")
- regenerate_content (includes: "shorter", "longer", "brief", "concise", "summarize", "expand", "rewrite")
- custom_research (includes: "latest news", "recent news", "current news", "news about", "updates on", "what's new with")
- end_research (includes: "end research", "stop", "that's enough", "finish")
- generate_pdf (includes: "yes, generate pdf", "create pdf", "generate report", "make pdf", "export pdf")
- confirm
- reject
- change_company
- ask_for_help
- unclear

Always extract the company name if present.
If none is present, return: "detected_company": "".

### RESEARCH STEPS
When starting research:
1. overview
2. financials
3. products
4. competitors
5. pain_points
6. opportunities
7. recommendations

### OUTPUT FORMAT (STRICT JSON)
{
  "intent": "",
  "detected_company": "",
  "next_step": "",
  "custom_topic": "",
  "response": "",
  "data": ""
}

### RESPONSE RULES
• Emotional messages → respond empathetically AND ask for company name.
• Off-topic general → answer briefly AND guide back.
• "yes", "okay" etc (without company) → ask which company.
• When company provided → begin research with overview.
• "next", "continue", "go ahead" → move to next research step.
• "latest news", "recent updates", "current developments" → use custom_research intent and extract the topic.
• "change company" → ask for new company.
• If unclear → ask a clarifying question.

### IMPORTANT
NEVER hallucinate companies.
NEVER create research unless the intent is provide_company or continue_research or custom_research.
"""

    RESEARCH_STEPS = [
        'overview',
        'financials',
        'products',
        'competitors',
        'pain_points',
        'opportunities',
        'recommendations'
    ]

    def __init__(self):
        from dotenv import load_dotenv
        load_dotenv()
        # Support both GEMINI_API_KEY and GEMINI_API_KEYS
        self.gemini_key = os.getenv('GEMINI_API_KEY', '')
        if not self.gemini_key:
            # Fall back to GEMINI_API_KEYS (get first key)
            keys = os.getenv('GEMINI_API_KEYS', '')
            if keys:
                self.gemini_key = keys.split(',')[0].strip()
        
        self.serp_key = os.getenv('SERPAPI_API_KEY', '')
        self.sessions = {}
        
        if not self.gemini_key:
            logger.warning("GEMINI_API_KEY not found in environment variables")
        if not self.serp_key:
            logger.warning("SERPAPI_API_KEY not found in environment variables")

    def handle_message(self, session_id: str, user_message: str) -> Dict[str, Any]:
        """Main entry point for processing user messages"""
        try:
            session = self._get_session(session_id)
            
            # Analyze user profile and sentiment
            self._update_user_profile(session_id, user_message, session)
            
            # Get intent from Gemini with user profile context
            gemini_response = self._call_gemini(user_message, session)
            
            if not gemini_response:
                return {
                    'success': False,
                    'response': 'I apologize, but I encountered an error. Please try again.',
                    'data': None
                }

            intent = gemini_response.get('intent', 'unclear')
            detected_company = gemini_response.get('detected_company', '')
            next_step = gemini_response.get('next_step', '')
            response = gemini_response.get('response', '')

            # Handle preference gathering state
            if session.get('pending_company') and not session.get('preferences_gathered'):
                return self._process_preferences(session_id, user_message, response)

            # Route based on intent
            if intent == 'provide_company' and detected_company:
                # Check if we should gather preferences first
                if not session.get('preferences_gathered'):
                    return self._gather_research_preferences(session_id, detected_company, response)
                return self._start_research(session_id, detected_company, response)
            
            elif intent == 'continue_research' or intent == 'confirm':
                return self._continue_research(session_id, response)
            
            elif intent == 'regenerate_section':
                # Extract step name from user message
                return self._regenerate_specific_section(session_id, user_message, response)
            
            elif intent == 'regenerate_content':
                # Extract the modification request (shorter, longer, etc.)
                return self._regenerate_content(session_id, user_message, response)
            
            elif intent == 'deeper_analysis':
                return self._deeper_analysis(session_id, response)
            
            elif intent == 'custom_research':
                custom_topic = gemini_response.get('custom_topic', '')
                return self._custom_research(session_id, custom_topic, response)
            
            elif intent == 'end_research':
                return self._reset_session(session_id, 'Research ended. Which company would you like to research next?')
            
            elif intent == 'generate_pdf':
                return self._generate_pdf_report(session_id, response)
            
            elif intent == 'change_company':
                return self._reset_session(session_id, response)
            
            # Handle "no thanks" after PDF prompt
            elif session.get('research_stopped') and intent in ['greeting', 'unclear', 'off_topic']:
                # User declined PDF, clean up session
                self.sessions[session_id] = {
                    'current_company': None,
                    'last_step': None,
                    'research_data': {}
                }
                return {
                    'success': True,
                    'response': 'No problem! Which company would you like to research next?',
                    'data': None,
                    'chart': None
                }
            
            # Handle conflict resolution
            elif session.get('pending_conflict'):
                return self._resolve_conflict(session_id, user_message, response)
            
            else:
                # greeting, off_topic, unclear, etc.
                # Check if user is confused and needs help
                user_type = session.get('user_profile', {}).get('type', 'normal')
                if user_type == 'confused':
                    helpful_response = """I understand! Let me help you get started. 

Here are some popular companies you could research:

📊 **Tech Companies**  
Google, Apple, Microsoft, Tesla, Amazon

💰 **Finance**  
JPMorgan Chase, Goldman Sachs, Visa

🛍️ **Retail**  
Walmart, Target, Costco

⚕️ **Healthcare**  
Johnson & Johnson, Pfizer, UnitedHealth

🍔 **Food & Beverage**  
McDonald's, Starbucks, Coca-Cola

---

Just tell me which one interests you, or mention any other company you'd like to explore. I'll help you understand everything about them!

**For example:** 'Tell me about Apple' or just 'Tesla'"""
                    return {
                        'success': True,
                        'response': helpful_response,
                        'data': None,
                        'chart': None
                    }
                
                return {
                    'success': True,
                    'response': response or "Hello! Which company would you like me to research?",
                    'data': None,
                    'chart': None
                }

        except Exception as e:
            logger.error(f"Error handling message: {e}", exc_info=True)
            return {
                'success': False,
                'response': 'An error occurred. Please try again.',
                'data': None
            }

    def _call_gemini(self, user_message: str, session: Dict) -> Optional[Dict]:
        """Call Gemini API with system prompt"""
        try:
            # Get user profile for adaptive responses
            user_profile = session.get('user_profile', {})
            user_type = user_profile.get('type', 'normal')
            
            context = ""
            if session.get('current_company'):
                context = f"\nCurrent context: Researching {session['current_company']}, last completed step: {session.get('last_step', 'none')}"
            
            # Add user profile context for adaptive responses
            if user_type != 'normal':
                context += f"\nUser Profile: {user_type} user - {user_profile.get('description', '')}"
                context += f"\nAdaptive Instruction: {self._get_adaptive_instruction(user_type)}"

            prompt = f"{self.SYSTEM_PROMPT}\n\nUser message: \"{user_message}\"{context}\n\nRespond with ONLY valid JSON, no markdown formatting."

            url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={self.gemini_key}"
            
            response = requests.post(
                url,
                json={
                    'contents': [{
                        'parts': [{'text': prompt}]
                    }],
                    'generationConfig': {
                        'temperature': 0.7,
                        'maxOutputTokens': 2048
                    }
                },
                timeout=30
            )

            if response.status_code == 200:
                data = response.json()
                text = data['candidates'][0]['content']['parts'][0]['text']
                
                # Clean JSON
                text = text.strip()
                if text.startswith('```json'):
                    text = text[7:]
                if text.startswith('```'):
                    text = text[3:]
                if text.endswith('```'):
                    text = text[:-3]
                text = text.strip()

                parsed = json.loads(text)
                return parsed
            
            logger.error(f"Gemini API error: {response.status_code} - {response.text}")
            return None

        except Exception as e:
            logger.error(f"Gemini call error: {e}", exc_info=True)
            return None

    def _start_research(self, session_id: str, company: str, response: str) -> Dict[str, Any]:
        """Start research on a new company"""
        session = self._get_session(session_id)
        session['current_company'] = company
        session['last_step'] = None
        session['research_data'] = {}
        
        # Perform overview research
        research_result = self._perform_research(company, 'overview')
        session['research_data']['overview'] = research_result
        session['last_step'] = 'overview'
        
        self._save_session(session_id, session)
        
        # Check for conflicts in overview
        conflict = research_result.get('conflict')
        if conflict:
            conflict_intro = f"🔍 I'm finding conflicting information about {conflict.get('field_name', 'this data')} for {company}.\n\n"
            
            sources_text = ""
            for source in conflict.get('sources', []):
                source_type = " 📄 (Official Source)" if source.get('is_official') else ""
                sources_text += f"• Source {source['source_id']}{source_type}: {source['display_text']}\n"
                if source.get('context'):
                    sources_text += f"  Context: {source['context']}\n"
            
            recommendation = f"\n💡 {conflict.get('recommendation', 'Official sources tend to be more reliable.')}"
            conflict_message = conflict_intro + sources_text + "\n" + conflict['question'] + recommendation
            
            session['pending_conflict'] = {
                'step': 'overview',
                'conflict': conflict,
                'research_result': research_result
            }
            self._save_session(session_id, session)
            
            return {
                'success': True,
                'messages': [
                    {'type': 'text', 'content': f"Great! I'll research {company} for you. Starting with an overview..."},
                    {'type': 'text', 'content': conflict_message},
                    {'type': 'conflict', 'data': conflict, 'step': 'overview'}
                ],
                'step': 'overview',
                'company': company
            }
        
        return {
            'success': True,
            'messages': [
                {'type': 'text', 'content': f"Great! I'll research {company} for you. Starting with an overview..."},
                {'type': 'research', 'step': 'overview', 'data': research_result.get('text'), 'chart': research_result.get('chart'), 'company': company},
                {'type': 'prompt', 'content': f"Should I research the financials of {company}?"}
            ],
            'step': 'overview',
            'company': company
        }

    def _continue_research(self, session_id: str, response: str) -> Dict[str, Any]:
        """Continue to next research step"""
        session = self._get_session(session_id)
        
        if not session.get('current_company'):
            return {
                'success': True,
                'response': response or 'Which company would you like me to research?',
                'messages': None,
                'data': None,
                'chart': None
            }

        next_step = self._get_next_step(session.get('last_step'))
        
        if not next_step:
            return {
                'success': True,
                'response': 'Research complete! Would you like to research another company?',
                'messages': None,
                'data': None,
                'chart': None
            }

        # Perform research
        research_result = self._perform_research(session['current_company'], next_step)
        session['research_data'][next_step] = research_result
        session['last_step'] = next_step
        
        self._save_session(session_id, session)
        
        # Check if there's a conflict
        conflict = research_result.get('conflict')
        if conflict:
            # Format conflict message
            conflict_intro = f"🔍 I'm finding conflicting information about {conflict.get('field_name', 'this data')} for {session['current_company']}.\n\n"
            
            sources_text = ""
            for source in conflict.get('sources', []):
                source_type = " 📄 (Official Source)" if source.get('is_official') else ""
                sources_text += f"• Source {source['source_id']}{source_type}: {source['display_text']}\n"
                if source.get('context'):
                    sources_text += f"  Context: {source['context']}\n"
            
            recommendation = f"\n💡 {conflict.get('recommendation', 'Official sources tend to be more reliable.')}"
            conflict_message = conflict_intro + sources_text + "\n" + conflict['question'] + recommendation
            
            # Store conflict data for resolution
            session['pending_conflict'] = {
                'step': next_step,
                'conflict': conflict,
                'research_result': research_result
            }
            self._save_session(session_id, session)
            
            return {
                'success': True,
                'messages': [
                    {'type': 'text', 'content': conflict_message},
                    {'type': 'conflict', 'data': conflict, 'step': next_step}
                ],
                'step': next_step,
                'company': session['current_company']
            }
        
        # No conflict - proceed normally
        # Get the NEXT step to ask about (step after the one we just researched)
        step_after_current = self._get_next_step(next_step)
        
        # Get step names for messaging
        step_names = {
            'overview': 'Overview',
            'financials': 'Financials',
            'products': 'Products & Services',
            'competitors': 'Competitors',
            'pain_points': 'Pain Points',
            'opportunities': 'Opportunities',
            'recommendations': 'Recommendations'
        }
        
        next_topic_questions = {
            'financials': f"Should I research the financials of {session['current_company']}?",
            'products': f"Should I research the products and services of {session['current_company']}?",
            'competitors': f"Should I research the competitors of {session['current_company']}?",
            'pain_points': f"Should I analyze the pain points and challenges of {session['current_company']}?",
            'opportunities': f"Should I identify opportunities for {session['current_company']}?",
            'recommendations': f"Should I generate strategic recommendations for {session['current_company']}?"
        }
        
        # If there's a next step, ask about it. Otherwise, we're done.
        if step_after_current:
            next_question = next_topic_questions.get(step_after_current, f"Should I continue with {step_names.get(step_after_current, step_after_current)}?")
        else:
            next_question = f"Research complete! Would you like to research another company?"
        
        return {
            'success': True,
            'messages': [
                {'type': 'research', 'step': next_step, 'data': research_result.get('text'), 'chart': research_result.get('chart'), 'company': session['current_company']},
                {'type': 'prompt', 'content': next_question}
            ],
            'step': next_step,
            'company': session['current_company']
        }

    def _perform_research(self, company: str, step: str) -> Dict[str, Any]:
        """Perform research for a specific step"""
        try:
            # Search web
            search_results = self._search_web(company, step)
            
            # Check for conflicts before synthesis
            conflict = self._detect_conflicts_in_results(company, step, search_results)
            
            # Synthesize results
            synthesized = self._synthesize_results(company, step, search_results)
            
            # Generate chart if applicable
            chart = None
            if step == 'financials':
                chart = self._generate_financial_chart(company, synthesized)
            elif step == 'competitors':
                chart = self._generate_competitor_chart(company, synthesized)
            
            return {
                'text': synthesized,
                'chart': chart,
                'raw_results': search_results,  # Store raw results for regeneration
                'conflict': conflict,  # Store detected conflicts
                'timestamp': datetime.now().isoformat()
            }

        except Exception as e:
            logger.error(f"Research error: {e}", exc_info=True)
            return {
                'text': f"I couldn't find detailed information about {company} for {step}.",
                'chart': None,
                'timestamp': datetime.now().isoformat()
            }

    def _fetch_page_content(self, url: str) -> str:
        """Fetch and extract text content from a webpage"""
        try:
            headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
            response = requests.get(url, headers=headers, timeout=10)
            
            if response.status_code == 200:
                soup = BeautifulSoup(response.content, 'html.parser')
                
                # Remove script and style elements
                for script in soup(["script", "style", "nav", "footer", "header"]):
                    script.decompose()
                
                # Get text
                text = soup.get_text()
                
                # Clean up whitespace
                lines = (line.strip() for line in text.splitlines())
                chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
                text = ' '.join(chunk for chunk in chunks if chunk)
                
                # Return first 2000 characters
                return text[:2000]
            
            return ""
        except Exception as e:
            logger.debug(f"Failed to fetch {url}: {e}")
            return ""
    
    def _search_web(self, company: str, step: str) -> List[Dict]:
        """Search web using SerpAPI and enrich with full content"""
        try:
            queries = {
                'overview': f"{company} company overview headquarters founded",
                'financials': f"{company} revenue earnings financial results",
                'products': f"{company} products services offerings",
                'competitors': f"{company} competitors competitive landscape",
                'pain_points': f"{company} challenges problems pain points",
                'opportunities': f"{company} opportunities growth market expansion",
                'recommendations': f"{company} strategic recommendations analysis"
            }

            query = queries.get(step, f"{company} {step}")

            response = requests.get(
                'https://serpapi.com/search',
                params={
                    'q': query,
                    'api_key': self.serp_key,
                    'num': 10  # Increased from 5 to 10 results
                },
                timeout=15
            )

            if response.status_code == 200:
                data = response.json()
                results = data.get('organic_results', [])
                
                # Enrich results with full page content for top 3
                for i, result in enumerate(results[:3]):
                    if 'link' in result:
                        full_content = self._fetch_page_content(result['link'])
                        if full_content:
                            result['full_content'] = full_content
                
                return results
            
            return []

        except Exception as e:
            logger.error(f"Search error: {e}")
            return []

    def _deeper_analysis(self, session_id: str, response: str) -> Dict[str, Any]:
        """Perform deeper analysis on current step"""
        session = self._get_session(session_id)
        
        if not session.get('current_company') or not session.get('last_step'):
            return {
                'success': True,
                'response': 'Please start a company research first.',
                'data': None,
                'chart': None
            }
        
        company = session['current_company']
        step = session['last_step']
        
        # Perform new search with more results
        logger.info(f"Performing deeper analysis for {company} - {step}")
        research_result = self._perform_research(company, step)
        session['research_data'][step] = research_result
        
        self._save_session(session_id, session)
        
        return {
            'success': True,
            'response': response or f"Here's a more detailed analysis of {step}...",
            'data': research_result.get('text'),
            'chart': research_result.get('chart'),
            'step': step,
            'company': company,
            'prompt_user': True,
            'prompt_message': f"Is this detailed enough? Should I continue with the next topic for {company}?"
        }
    
    def _custom_research(self, session_id: str, custom_topic: str, response: str) -> Dict[str, Any]:
        """Perform custom research on a specific topic for the current company"""
        session = self._get_session(session_id)
        
        if not session.get('current_company'):
            return {
                'success': True,
                'response': 'Please start a company research first.',
                'messages': None,
                'data': None,
                'chart': None
            }
        
        company = session['current_company']
        
        # Use the custom topic or default to "latest news"
        topic = custom_topic if custom_topic else "latest news and updates"
        
        logger.info(f"Performing custom research for {company} - {topic}")
        
        # Search for the custom topic
        search_results = self._search_web(company, topic)
        
        # Synthesize results
        synthesized = self._synthesize_with_instruction(
            company, 
            topic, 
            search_results,
            f"Provide comprehensive information about {topic} for {company}. Focus on recent developments and current information."
        )
        
        # Store in session as a custom research entry
        if 'custom_research' not in session:
            session['custom_research'] = []
        
        session['custom_research'].append({
            'topic': topic,
            'text': synthesized,
            'timestamp': datetime.now().isoformat()
        })
        
        self._save_session(session_id, session)
        
        return {
            'success': True,
            'messages': [
                {'type': 'research', 'step': topic, 'data': synthesized, 'chart': None, 'company': company},
                {'type': 'prompt', 'content': f"Would you like to continue with the structured research workflow, or explore another custom topic for {company}?"}
            ],
            'step': 'custom',
            'company': company
        }

    def _regenerate_content(self, session_id: str, user_request: str, response: str) -> Dict[str, Any]:
        """Regenerate content with different instructions (shorter, longer, etc.)"""
        session = self._get_session(session_id)
        
        if not session.get('current_company') or not session.get('last_step'):
            return {
                'success': True,
                'response': 'Please start a company research first.',
                'data': None,
                'chart': None
            }
        
        company = session['current_company']
        step = session['last_step']
        last_research = session.get('research_data', {}).get(step, {})
        raw_results = last_research.get('raw_results', [])
        
        if not raw_results:
            return {
                'success': True,
                'response': 'I need to fetch the data first. Let me search again...',
                'data': None,
                'chart': None
            }
        
        # Regenerate with user's specific request
        logger.info(f"Regenerating {step} for {company} with request: {user_request}")
        synthesized = self._synthesize_with_instruction(company, step, raw_results, user_request)
        
        # Keep same chart
        chart = last_research.get('chart')
        
        # Update session
        session['research_data'][step] = {
            'text': synthesized,
            'chart': chart,
            'raw_results': raw_results,
            'timestamp': datetime.now().isoformat()
        }
        
        self._save_session(session_id, session)
        
        return {
            'success': True,
            'response': response or f"Here's the revised {step} overview...",
            'data': synthesized,
            'chart': chart,
            'step': step,
            'company': company,
            'prompt_user': True,
            'prompt_message': f"Is this better? Should I continue researching {company}?"
        }
    
    def _detect_conflicts_in_results(self, company: str, step: str, results: List[Dict]) -> Optional[Dict]:
        """Detect conflicts in search results using AI analysis"""
        if len(results) < 2:
            return None
            
        # Prepare sources data for AI analysis
        sources_info = []
        for i, result in enumerate(results[:5], 1):  # Analyze top 5 sources
            content = result.get('snippet', '') + ' ' + result.get('content', '')
            source_name = result.get('title', 'Unknown Source')
            link = result.get('link', '')
            
            # Check if it's a reliable source
            is_official = any(keyword in source_name.lower() or keyword in link.lower() 
                            for keyword in ['annual report', 'investor relations', 'official', 'sec filing', 'quarterly report'])
            
            sources_info.append({
                'source_id': i,
                'source': source_name,
                'link': link,
                'content': content[:500],  # Limit content for analysis
                'is_official': is_official
            })
        
        # Use Gemini to detect conflicts
        conflict_analysis = self._analyze_conflicts_with_ai(company, step, sources_info)
        
        return conflict_analysis
    
    def _analyze_conflicts_with_ai(self, company: str, step: str, sources_info: List[Dict]) -> Optional[Dict]:
        """Use Gemini AI to analyze conflicts across all data types"""
        try:
            # Build analysis prompt
            sources_text = ""
            for source in sources_info:
                official_marker = " [OFFICIAL SOURCE]" if source['is_official'] else ""
                sources_text += f"\n\nSource {source['source_id']}{official_marker}:\nTitle: {source['source']}\nContent: {source['content']}\n"
            
            prompt = f"""You are analyzing search results about {company} for the '{step}' research section.

Your task: Detect ANY conflicting or inconsistent information across these sources.

Sources:
{sources_text}

Analyze for conflicts in:
- Financial data (revenue, profit, valuation, funding)
- Company facts (employee count, headquarters, founding year, CEO)
- Product information (number of products, key offerings)
- Market data (market share, customers, regions)
- Any other factual data where sources disagree

IMPORTANT:
- Only flag SIGNIFICANT conflicts (>5% difference for numbers, clear factual disagreements for text)
- Minor variations in wording are NOT conflicts
- If one source is marked [OFFICIAL SOURCE], note that in your response
- Be specific about what data conflicts and between which sources

RESPOND IN VALID JSON ONLY:
{{
  "has_conflict": true/false,
  "conflict_type": "revenue" or "employee_count" or "headquarters" or "product_count" or "founding_year" or "other",
  "field_name": "descriptive name of conflicting field",
  "question": "user-friendly question about the conflict",
  "sources": [
    {{
      "source_id": 1,
      "value": "the conflicting value from this source",
      "display_text": "formatted display (e.g., '₹43,279 Cr' or '23,652 employees')",
      "is_official": true/false,
      "context": "brief context explaining this value"
    }}
  ],
  "recommendation": "which source seems more reliable and why"
}}

If NO significant conflicts exist, return: {{"has_conflict": false}}"""

            response = requests.post(
                f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={self.gemini_key}",
                json={
                    'contents': [{
                        'parts': [{'text': prompt}]
                    }],
                    'generationConfig': {
                        'temperature': 0.1,
                        'maxOutputTokens': 1000
                    }
                },
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                text = result['candidates'][0]['content']['parts'][0]['text']
                
                # Clean and parse JSON
                text = text.strip()
                if text.startswith('```json'):
                    text = text[7:]
                if text.startswith('```'):
                    text = text[3:]
                if text.endswith('```'):
                    text = text[:-3]
                text = text.strip()
                
                conflict_data = json.loads(text)
                
                if conflict_data.get('has_conflict'):
                    logger.info(f"Conflict detected in {step}: {conflict_data.get('field_name')}")
                    return conflict_data
                else:
                    logger.info(f"No conflicts detected in {step}")
                    return None
            
            return None
            
        except Exception as e:
            logger.error(f"Error analyzing conflicts: {e}")
            return None
    
    def _regenerate_specific_section(self, session_id: str, user_message: str, response: str) -> Dict[str, Any]:
        """Regenerate a specific section based on user request"""
        session = self._get_session(session_id)
        company = session.get('current_company')
        
        if not company:
            return {
                'success': True,
                'response': 'Please start a research first before regenerating sections.',
                'data': None,
                'chart': None
            }
        
        # Extract step name from message (e.g., "regenerate overview", "regenerate financials for Apple")
        step_keywords = {
            'overview': ['overview', 'introduction', 'about'],
            'financials': ['financial', 'financials', 'revenue', 'profit'],
            'products': ['product', 'products', 'service', 'services'],
            'competitors': ['competitor', 'competitors', 'competition'],
            'pain_points': ['pain', 'pain_points', 'challenge', 'challenges'],
            'opportunities': ['opportunit', 'opportunities', 'growth'],
            'recommendations': ['recommend', 'recommendations', 'advice']
        }
        
        detected_step = None
        message_lower = user_message.lower()
        
        for step, keywords in step_keywords.items():
            if any(keyword in message_lower for keyword in keywords):
                detected_step = step
                break
        
        if not detected_step:
            return {
                'success': True,
                'response': 'Please specify which section you want to regenerate (overview, financials, products, etc.)',
                'data': None,
                'chart': None
            }
        
        # Perform fresh research for this section
        logger.info(f"Regenerating {detected_step} for {company}")
        research_result = self._perform_research(company, detected_step)
        
        # Update session
        session['research_data'][detected_step] = research_result
        self._save_session(session_id, session)
        
        # Check for conflicts
        conflict = research_result.get('conflict')
        if conflict:
            conflict_intro = f"🔍 I found some conflicting information while regenerating {detected_step}.\n\n"
            
            sources_text = ""
            for source in conflict.get('sources', []):
                source_type = " 📄 (Official Source)" if source.get('is_official') else ""
                sources_text += f"• Source {source['source_id']}{source_type}: {source['display_text']}\n"
                if source.get('context'):
                    sources_text += f"  Context: {source['context']}\n"
            
            recommendation = f"\n💡 {conflict.get('recommendation', 'Please select the most reliable source.')}"
            conflict_message = conflict_intro + sources_text + "\n" + conflict['question'] + recommendation
            
            session['pending_conflict'] = {
                'step': detected_step,
                'conflict': conflict,
                'research_result': research_result
            }
            self._save_session(session_id, session)
            
            return {
                'success': True,
                'messages': [
                    {'type': 'text', 'content': f"Regenerating {detected_step} section..."},
                    {'type': 'text', 'content': conflict_message},
                    {'type': 'conflict', 'data': conflict, 'step': detected_step}
                ],
                'step': detected_step,
                'company': company
            }
        
        step_names = {
            'overview': 'Overview',
            'financials': 'Financial Analysis',
            'products': 'Products & Services',
            'competitors': 'Competitive Analysis',
            'pain_points': 'Pain Points',
            'opportunities': 'Opportunities',
            'recommendations': 'Strategic Recommendations'
        }
        
        return {
            'success': True,
            'messages': [
                {'type': 'research', 'step': detected_step, 'data': research_result.get('text'), 'chart': research_result.get('chart'), 'company': company}
            ],
            'step': detected_step,
            'company': company
        }
    
    def _extract_revenue_from_text(self, text: str) -> Optional[float]:
        """Extract revenue as a normalized number"""
        import re
        patterns = [
            r'([₹\$€£]?\s*\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(billion|million|crore|cr|b|m|thousand|k)',
            r'revenue[:\s]+([₹\$€£]?\s*\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(billion|million|crore|cr|b|m|thousand|k)',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                amount_str = re.sub(r'[^\d.]', '', match.group(1))
                try:
                    amount = float(amount_str)
                    unit = match.group(2).lower()
                    
                    multiplier = {
                        'billion': 1_000_000_000, 'b': 1_000_000_000,
                        'million': 1_000_000, 'm': 1_000_000,
                        'crore': 10_000_000, 'cr': 10_000_000,
                        'thousand': 1_000, 'k': 1_000
                    }.get(unit, 1)
                    
                    return amount * multiplier
                except ValueError:
                    continue
        return None
    
    def _synthesize_results(self, company: str, step: str, results: List[Dict]) -> str:
        """Synthesize search results using Gemini"""
        return self._synthesize_with_instruction(company, step, results, "comprehensive, detailed analysis")
    
    def _synthesize_with_instruction(self, company: str, step: str, results: List[Dict], user_instruction: str) -> str:
        """Synthesize search results with specific user instruction"""
        if not results:
            return f"I couldn't find detailed information about {company} for {step}. Please try another company or step."

        snippets = []
        # Prioritize full content over snippets
        for result in results[:10]:
            if 'full_content' in result and result['full_content']:
                snippets.append(f"Source: {result.get('title', 'Unknown')}\n{result['full_content']}")
            elif 'snippet' in result:
                snippets.append(result['snippet'])

        context = "\n\n".join(snippets)

        try:
            # Determine the style based on user instruction
            if 'short' in user_instruction.lower() or 'brief' in user_instruction.lower() or 'concise' in user_instruction.lower():
                style_instruction = "Create a BRIEF, CONCISE summary (maximum 150 words)"
            elif 'long' in user_instruction.lower() or 'detail' in user_instruction.lower() or 'elaborate' in user_instruction.lower():
                style_instruction = "Create a COMPREHENSIVE, DETAILED analysis with extensive information"
            else:
                style_instruction = "Create a professional, well-structured analysis"
            
            prompt = f"Synthesize the following information about {company} for the '{step}' section into a professional report format:\n\n{context}\n\nIMPORTANT INSTRUCTIONS:\n- {style_instruction}\n- Write ONLY the factual content - NO introductory phrases like 'Here is...', 'The following...', 'Based on...'\n- Start directly with the substantive information\n- Do not truncate or use ellipsis (...)\n- Use clear paragraphs and structure\n- Write in a professional, report-ready style"

            response = requests.post(
                f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={self.gemini_key}",
                json={
                    'contents': [{
                        'parts': [{'text': prompt}]
                    }],
                    'generationConfig': {
                        'temperature': 0.7,
                        'maxOutputTokens': 4096
                    }
                },
                timeout=30
            )

            if response.status_code == 200:
                data = response.json()
                return data['candidates'][0]['content']['parts'][0]['text']

        except Exception as e:
            logger.error(f"Synthesis error: {e}")

        return context

    def _generate_financial_chart(self, company: str, data: str) -> Optional[str]:
        """Generate financial chart using plotly"""
        try:
            # Extract financial data (simplified example)
            # In production, parse the synthesized data properly
            df = pd.DataFrame({
                'Year': ['2021', '2022', '2023', '2024'],
                'Revenue': [100, 120, 145, 170]  # Example data
            })

            fig = go.Figure(data=[
                go.Bar(x=df['Year'], y=df['Revenue'], marker_color='rgb(102, 126, 234)')
            ])

            fig.update_layout(
                title=f'{company} Revenue Trend',
                xaxis_title='Year',
                yaxis_title='Revenue (Millions)',
                template='plotly_white',
                height=400
            )

            return fig.to_json()

        except Exception as e:
            logger.error(f"Chart generation error: {e}")
            return None

    def _generate_competitor_chart(self, company: str, data: str) -> Optional[str]:
        """Generate competitor comparison chart"""
        try:
            # Extract competitor data (simplified example)
            df = pd.DataFrame({
                'Company': [company, 'Competitor A', 'Competitor B', 'Competitor C'],
                'Market Share': [35, 28, 22, 15]
            })

            fig = px.pie(df, values='Market Share', names='Company', 
                        title=f'{company} Market Position',
                        color_discrete_sequence=px.colors.sequential.Purples)

            fig.update_layout(height=400)

            return fig.to_json()

        except Exception as e:
            logger.error(f"Chart generation error: {e}")
            return None

    def _format_revenue(self, amount: float) -> str:
        """Format revenue amount for display"""
        if amount >= 10_000_000:  # Crores
            return f"₹{amount / 10_000_000:,.2f} Cr"
        elif amount >= 1_000_000:  # Millions
            return f"${amount / 1_000_000:,.2f} M"
        elif amount >= 1_000:
            return f"${amount / 1_000:,.2f} K"
        return f"${amount:,.2f}"
    
    def _get_next_step(self, last_step: Optional[str]) -> Optional[str]:
        """Get next research step"""
        if last_step is None:
            return 'overview'

        try:
            index = self.RESEARCH_STEPS.index(last_step)
            if index < len(self.RESEARCH_STEPS) - 1:
                return self.RESEARCH_STEPS[index + 1]
        except ValueError:
            pass

        return None

    def _resolve_conflict(self, session_id: str, user_message: str, response: str) -> Dict[str, Any]:
        """Resolve data conflict based on user choice"""
        session = self._get_session(session_id)
        pending = session.get('pending_conflict')
        
        if not pending:
            return {'success': True, 'response': 'No pending conflicts.', 'data': None, 'chart': None}
        
        conflict = pending['conflict']
        step = pending['step']
        research_result = pending['research_result']
        
        # Determine which source user chose
        user_choice_lower = user_message.lower()
        chosen_source = None
        
        # Check for keywords
        if 'official' in user_choice_lower or 'annual report' in user_choice_lower or 'source 1' in user_choice_lower:
            # Find official source
            for source in conflict.get('sources', []):
                if source.get('is_official'):
                    chosen_source = source
                    break
            if not chosen_source:
                chosen_source = conflict['sources'][0]
        elif 'source 2' in user_choice_lower:
            chosen_source = conflict['sources'][1] if len(conflict['sources']) > 1 else conflict['sources'][0]
        elif 'source 3' in user_choice_lower:
            chosen_source = conflict['sources'][2] if len(conflict['sources']) > 2 else conflict['sources'][0]
        elif any(keyword in user_choice_lower for keyword in ['first', '1']):
            chosen_source = conflict['sources'][0]
        elif any(keyword in user_choice_lower for keyword in ['second', '2']):
            chosen_source = conflict['sources'][1] if len(conflict['sources']) > 1 else conflict['sources'][0]
        
        if not chosen_source:
            # Default to official source if available
            for source in conflict.get('sources', []):
                if source.get('is_official'):
                    chosen_source = source
                    break
            if not chosen_source:
                chosen_source = conflict['sources'][0]
        
        # Update research result with chosen value
        field_name = conflict.get('field_name', 'data')
        chosen_value = chosen_source.get('display_text', chosen_source.get('value', 'N/A'))
        
        # Add note about chosen source to research data
        source_note = f"\n\n--- Verified Data (Source: {chosen_source.get('source_id')}) ---\n{field_name}: {chosen_value}\n" + (f"Note: {chosen_source.get('context')}\n" if chosen_source.get('context') else "")
        updated_text = research_result.get('text', '') + source_note
        
        research_result['text'] = updated_text
        research_result['chosen_source'] = chosen_source
        session['research_data'][step] = research_result
        
        # Clear pending conflict
        del session['pending_conflict']
        self._save_session(session_id, session)
        
        # Get next step
        step_after_current = self._get_next_step(step)
        step_names = {
            'overview': 'Overview',
            'financials': 'Financials',
            'products': 'Products & Services',
            'competitors': 'Competitors',
            'pain_points': 'Pain Points',
            'opportunities': 'Opportunities',
            'recommendations': 'Recommendations'
        }
        
        next_topic_questions = {
            'products': f"Should I research the products and services of {session['current_company']}?",
            'competitors': f"Should I research the competitors of {session['current_company']}?",
            'pain_points': f"Should I analyze the pain points and challenges of {session['current_company']}?",
            'opportunities': f"Should I identify opportunities for {session['current_company']}?",
            'recommendations': f"Should I generate strategic recommendations for {session['current_company']}?"
        }
        
        if step_after_current:
            next_question = next_topic_questions.get(step_after_current, f"Should I continue with {step_names.get(step_after_current, step_after_current)}?")
        else:
            next_question = f"Research complete! Would you like to research another company?"
        
        # Create confirmation message
        field_name = conflict.get('field_name', 'value')
        confirmation = f"✓ Done. I'll use {chosen_value} as the official {field_name}."
        
        return {
            'success': True,
            'messages': [
                {'type': 'text', 'content': confirmation},
                {'type': 'research', 'step': step, 'data': updated_text, 'chart': research_result.get('chart'), 'company': session['current_company']},
                {'type': 'prompt', 'content': next_question}
            ],
            'step': step,
            'company': session['current_company']
        }
    
    def _generate_pdf_report(self, session_id: str, response: str) -> Dict[str, Any]:
        """Generate PDF report from research data"""
        session = self._get_session(session_id)
        company = session.get('current_company')
        research_data = session.get('research_data', {})
        
        logger.info(f"PDF Generation Request - Session: {session_id}")
        logger.info(f"Company: {company}")
        logger.info(f"Research data keys: {list(research_data.keys()) if research_data else 'None'}")
        
        if not company or not research_data:
            logger.warning("No company or research data available for PDF generation")
            return {
                'success': True,
                'response': 'No research data available to generate PDF. Please start a research first.',
                'data': None,
                'chart': None
            }
        
        try:
            # Generate PDF filename
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            filename = f"{company.replace(' ', '_')}_{timestamp}.pdf"
            output_path = os.path.join('storage', 'reports', filename)
            
            # Ensure directory exists
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            
            logger.info(f"Generating PDF report for {company} at {output_path}")
            logger.info(f"Full research data: {research_data}")
            
            # Generate PDF
            success = pdf_generator.create_pdf(company, research_data, output_path)
            
            if success:
                # Clean up session after PDF generation
                self.sessions[session_id] = {
                    'current_company': None,
                    'last_step': None,
                    'research_data': {}
                }
                
                return {
                    'success': True,
                    'messages': [
                        {'type': 'text', 'content': f"✅ PDF report generated successfully for {company}!"},
                        {'type': 'pdf_ready', 'filename': filename, 'path': output_path},
                        {'type': 'text', 'content': 'Which company would you like to research next?'}
                    ],
                    'pdf_filename': filename,
                    'pdf_path': output_path
                }
            else:
                return {
                    'success': False,
                    'response': 'Sorry, I encountered an error while generating the PDF. Please try again.',
                    'data': None
                }
                
        except Exception as e:
            logger.error(f"Error in PDF generation: {e}", exc_info=True)
            return {
                'success': False,
                'response': 'An error occurred while generating the PDF report.',
                'data': None
            }
    
    def _reset_session(self, session_id: str, response: str) -> Dict[str, Any]:
        """Reset session for new company - offer PDF generation"""
        session = self._get_session(session_id)
        company = session.get('current_company', 'the company')
        
        # Check if there's research data to export
        has_data = bool(session.get('research_data'))
        
        # DON'T reset session yet - keep data for PDF generation
        # Only mark research as stopped
        session['research_stopped'] = True
        self._save_session(session_id, session)
        
        if has_data:
            return {
                'success': True,
                'messages': [
                    {'type': 'text', 'content': f"Research on {company} has been stopped. Would you like to export your research summary as a PDF report?"},
                    {'type': 'prompt', 'content': "Would you like to generate a PDF report?"}
                ],
                'data': None,
                'chart': None
            }
        
        # If no data, reset session now
        self.sessions[session_id] = {
            'current_company': None,
            'last_step': None,
            'research_data': {}
        }
        
        return {
            'success': True,
            'response': response or 'Sure! Which company would you like to research?',
            'data': None,
            'chart': None
        }

    def _update_user_profile(self, session_id: str, user_message: str, session: Dict):
        """Analyze user behavior and update profile"""
        try:
            profile = session.get('user_profile', {})
            profile['message_count'] = profile.get('message_count', 0) + 1
            
            # Add to conversation history
            history = session.get('conversation_history', [])
            history.append(user_message)
            session['conversation_history'] = history[-10:]  # Keep last 10 messages
            
            # Check for immediate confusion signals
            msg_lower = user_message.lower()
            confusion_keywords = ['help', 'confused', "don't know", "can't think", "not sure", "dont know", "cant think", "what do", "how do", "stuck", "overwhelmed"]
            
            if any(keyword in msg_lower for keyword in confusion_keywords):
                profile['type'] = 'confused'
                profile['confusion_signals'] = profile.get('confusion_signals', 0) + 1
                profile['description'] = 'User is seeking help and guidance'
                profile['sentiment'] = 'confused'
            # Analyze user type after 2+ messages for more accurate classification
            elif profile['message_count'] >= 2:
                user_analysis = self._analyze_user_type(history)
                profile['type'] = user_analysis.get('type', 'normal')
                profile['description'] = user_analysis.get('description', '')
                profile['sentiment'] = user_analysis.get('sentiment', 'neutral')
            
            session['user_profile'] = profile
            self._save_session(session_id, session)
            
        except Exception as e:
            logger.error(f"Error updating user profile: {e}")
    
    def _analyze_user_type(self, conversation_history: list) -> Dict:
        """Use Gemini to analyze user type and sentiment"""
        try:
            conversation_text = "\n".join(conversation_history)
            
            prompt = f"""Analyze this conversation and classify the user type:

Conversation:
{conversation_text}

User Types:
1. CONFUSED: User is unsure, asks many clarifying questions, uncertain what they want
2. EFFICIENT: User is direct, wants quick results, minimal conversation, gives short responses
3. CHATTY: User goes off-topic frequently, adds extra context, conversational style
4. EDGE_CASE: User provides invalid inputs, goes off-topic, requests beyond capabilities

Analyze the user's behavior and respond with JSON:
{{
    "type": "confused|efficient|chatty|edge_case|normal",
    "description": "Brief description of user behavior",
    "sentiment": "positive|neutral|negative|confused",
    "confidence": 0.0-1.0
}}"""

            url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={self.gemini_key}"
            
            response = requests.post(
                url,
                json={
                    'contents': [{'parts': [{'text': prompt}]}],
                    'generationConfig': {'temperature': 0.3, 'maxOutputTokens': 500}
                },
                timeout=15
            )
            
            if response.status_code == 200:
                data = response.json()
                text = data['candidates'][0]['content']['parts'][0]['text'].strip()
                
                # Clean JSON
                if text.startswith('```json'):
                    text = text[7:]
                if text.startswith('```'):
                    text = text[3:]
                if text.endswith('```'):
                    text = text[:-3]
                text = text.strip()
                
                result = json.loads(text)
                logger.info(f"User analysis: {result}")
                return result
            
            return {'type': 'normal', 'description': 'Standard user', 'sentiment': 'neutral'}
            
        except Exception as e:
            logger.error(f"Error analyzing user type: {e}")
            return {'type': 'normal', 'description': 'Standard user', 'sentiment': 'neutral'}
    
    def _get_adaptive_instruction(self, user_type: str) -> str:
        """Get adaptive instructions based on user type"""
        instructions = {
            'confused': "Be EXTRA patient and supportive. Offer concrete examples and suggestions. Provide clear step-by-step guidance. If they can't decide, suggest popular companies. Make it easy for them.",
            'efficient': "Be concise and direct. Skip pleasantries. Provide quick, actionable responses. Minimize back-and-forth.",
            'chatty': "Be conversational but stay on task. Acknowledge their context. Gently guide back to research when they drift.",
            'edge_case': "Be helpful and understanding. Validate their input. Explain limitations clearly. Offer alternative solutions."
        }
        return instructions.get(user_type, "Maintain professional, helpful tone.")
    
    def _process_preferences(self, session_id: str, user_message: str, response: str) -> Dict[str, Any]:
        """Process user's research preferences"""
        session = self._get_session(session_id)
        company = session.get('pending_company')
        
        if not company:
            return {
                'success': False,
                'response': 'I apologize, but I lost track of which company you wanted to research. Which company would you like me to research?',
                'data': None
            }
        
        user_message_lower = user_message.lower()
        
        # Determine research focus
        focus_areas = []
        if 'all' in user_message_lower or 'comprehensive' in user_message_lower or 'everything' in user_message_lower:
            focus_areas = ['overview', 'financials', 'products', 'market', 'opportunities']
        elif 'financial' in user_message_lower or 'revenue' in user_message_lower or 'profit' in user_message_lower:
            focus_areas = ['financials', 'overview']
        elif 'product' in user_message_lower or 'service' in user_message_lower:
            focus_areas = ['products', 'overview']
        elif 'market' in user_message_lower or 'competitor' in user_message_lower:
            focus_areas = ['market', 'overview']
        elif 'opportunit' in user_message_lower:
            focus_areas = ['opportunities', 'market', 'overview']
        else:
            # Default to comprehensive if unclear
            focus_areas = ['overview', 'financials', 'products', 'market']
        
        # Save preferences
        session['research_focus'] = focus_areas
        session['preferences_gathered'] = True
        session.pop('pending_company', None)
        self._save_session(session_id, session)
        
        # Start research
        return self._start_research(session_id, company, f"Perfect! I'll focus on {', '.join(focus_areas)} for {company}. Let's begin!")
    
    def _gather_research_preferences(self, session_id: str, company: str, response: str) -> Dict[str, Any]:
        """Gather research preferences before starting research"""
        session = self._get_session(session_id)
        session['pending_company'] = company
        self._save_session(session_id, session)
        
        user_type = session.get('user_profile', {}).get('type', 'normal')
        
        # Efficient users skip preferences
        if user_type == 'efficient':
            session['preferences_gathered'] = True
            session['research_focus'] = ['overview', 'financials', 'products']  # Quick essentials
            self._save_session(session_id, session)
            return self._start_research(session_id, company, response)
        
        # For other users, ask about preferences
        preference_message = f"""Great! I'll research **{company}** for you. 

To provide the most relevant insights, I'd like to know:

**What aspects of {company} are you most interested in?**

• Financial performance and metrics  
• Products and services  
• Market position and competitors  
• Business opportunities  
• All of the above (comprehensive research)

---

Please let me know your focus area, or say **'all'** for complete research."""
        
        return {
            'success': True,
            'messages': [
                {'type': 'text', 'content': preference_message}
            ],
            'awaiting_preferences': True
        }

    def _get_session(self, session_id: str) -> Dict:
        """Get or create session"""
        if session_id not in self.sessions:
            self.sessions[session_id] = {
                'current_company': None,
                'last_step': None,
                'research_data': {},
                'user_profile': {
                    'type': 'normal',
                    'message_count': 0,
                    'off_topic_count': 0,
                    'question_count': 0,
                    'confusion_signals': 0,
                    'efficiency_signals': 0,
                    'sentiment_history': []
                },
                'conversation_history': [],
                'preferences_gathered': False
            }
        return self.sessions[session_id]

    def _save_session(self, session_id: str, session: Dict):
        """Save session"""
        self.sessions[session_id] = session


# Global instance
research_agent = ResearchAgent()
