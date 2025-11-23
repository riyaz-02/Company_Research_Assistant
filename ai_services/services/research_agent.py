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
- regenerate_content (includes: "shorter", "longer", "brief", "concise", "summarize", "expand", "rewrite")
- custom_research (includes: "latest news", "recent news", "current news", "news about", "updates on", "what's new with")
- end_research (includes: "end research", "stop", "that's enough", "finish")
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
            
            # Get intent from Gemini
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

            # Route based on intent
            if intent == 'provide_company' and detected_company:
                return self._start_research(session_id, detected_company, response)
            
            elif intent == 'continue_research' or intent == 'confirm':
                return self._continue_research(session_id, response)
            
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
            
            elif intent == 'change_company':
                return self._reset_session(session_id, response)
            
            else:
                # greeting, off_topic, unclear, etc.
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
            context = ""
            if session.get('current_company'):
                context = f"\nCurrent context: Researching {session['current_company']}, last completed step: {session.get('last_step', 'none')}"

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

    def _reset_session(self, session_id: str, response: str) -> Dict[str, Any]:
        """Reset session for new company - offer PDF generation"""
        session = self._get_session(session_id)
        company = session.get('current_company', 'the company')
        
        # Check if there's research data to export
        has_data = bool(session.get('research_data'))
        
        # Reset session
        self.sessions[session_id] = {
            'current_company': None,
            'last_step': None,
            'research_data': {}
        }
        
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
        
        return {
            'success': True,
            'response': response or 'Sure! Which company would you like to research?',
            'data': None,
            'chart': None
        }

    def _get_session(self, session_id: str) -> Dict:
        """Get or create session"""
        if session_id not in self.sessions:
            self.sessions[session_id] = {
                'current_company': None,
                'last_step': None,
                'research_data': {}
            }
        return self.sessions[session_id]

    def _save_session(self, session_id: str, session: Dict):
        """Save session"""
        self.sessions[session_id] = session


# Global instance
research_agent = ResearchAgent()
