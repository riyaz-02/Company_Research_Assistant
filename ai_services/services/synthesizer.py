"""
Content Synthesis Service
"""
from typing import List, Dict, Any
from loguru import logger

from services.gemini_client import gemini_client
from utils.text_cleaner import clean_search_results, remove_duplicates
from models.schemas import Evidence
from config.settings import settings


class Synthesizer:
    """Handles content synthesis and formatting"""
    
    STEP_PROMPTS = {
        'company_basics': (
            "Synthesize the following search results into a clean, professional company overview.\n"
            "Include: headquarters location, industry, employee count, founding year, and brief description.\n"
            "Remove duplicate information. Use bullet points for key facts. Maximum 200 words.\n"
            "Write complete sentences - do NOT truncate with '...' or ellipsis.\n"
            "Format with single line breaks between bullets. Be factual and complete."
        ),
        'financial': (
            "Synthesize the following financial data into a clear financial overview.\n"
            "Include: revenue figures, growth rates, funding rounds, valuation, investors.\n"
            "Use bullet points for key metrics. Remove duplicate numbers. Maximum 200 words.\n"
            "Write complete information - do NOT use '...' or truncate.\n"
            "Format currency consistently. Single line breaks between bullets."
        ),
        'products_tech': (
            "Synthesize the following information into a products and services overview.\n"
            "Include: main product lines, key services, technology stack, target customers.\n"
            "Group related products together. Use bullet points. Maximum 200 words.\n"
            "Write complete descriptions - do NOT truncate with '...'.\n"
            "Single line breaks between bullets. Be concise but complete."
        ),
        'competitors': (
            "Synthesize the following into a competitive landscape analysis.\n"
            "List main competitors with brief descriptions. Identify market positioning.\n"
            "Use bullet points for each competitor. Maximum 200 words.\n"
            "Write complete information - do NOT use ellipsis or '...'.\n"
            "Single line breaks between bullets. Be factual and complete."
        ),
        'pain_points': (
            "Based on all the research data, identify and synthesize key pain points, challenges, and obstacles.\n"
            "Identify 3-5 main pain points or challenges.\n"
            "Be specific and actionable.\n"
            "Use bullet points with single line breaks between items.\n"
            "Write complete descriptions - do NOT use '...'.\n"
            "Maximum 250 words.\n"
            "Do not speculate; base insights on provided data."
        ),
        'recommendations': (
            "Generate strategic recommendations based on all research data.\n"
            "Provide 4-6 actionable recommendations.\n"
            "Base recommendations on identified pain points and competitive landscape.\n"
            "Be specific about potential solutions or engagement strategies.\n"
            "Use bullet points for each recommendation with single line breaks.\n"
            "Write complete information - do NOT truncate with '...'.\n"
            "Maximum 300 words.\n"
            "Focus on value proposition and strategic fit."
        )
    }
    
    async def synthesize_section(
        self,
        step: str,
        search_results: List[Dict[str, Any]],
        company_name: str
    ) -> Dict[str, Any]:
        """
        Synthesize a research section from search results
        
        Args:
            step: Step name
            search_results: Raw search results
            company_name: Company name
            
        Returns:
            Dict with content, evidence, needs_retry
        """
        logger.info(f"Synthesizing section: {step} for {company_name}")
        
        # Clean and optimize search results
        cleaned_results = clean_search_results(
            search_results,
            max_snippets=settings.max_snippets_per_step,
            max_length=settings.max_snippet_length
        )
        
        if not cleaned_results:
            logger.warning("No valid search results after cleaning")
            return {
                'content': "Insufficient data available for this section.",
                'evidence': [],
                'needs_retry': False
            }
        
        # Extract evidence
        evidence = []
        snippets = []
        for result in cleaned_results:
            snippet = result.get('snippet', '')
            if snippet:
                snippets.append(snippet)
                evidence.append(Evidence(
                    source=result.get('title', 'Unknown'),
                    url=result.get('url', result.get('link', '')),
                    snippet=snippet
                ))
        
        # Remove duplicate snippets
        unique_snippets = remove_duplicates(snippets)
        raw_data = "\n\n".join(unique_snippets)
        
        # Get synthesis prompt
        synthesis_prompt = self.STEP_PROMPTS.get(step, self._get_default_prompt())
        
        # Call Gemini for synthesis
        try:
            synthesized_content = await gemini_client.synthesize_text(
                instruction=synthesis_prompt,
                raw_data=raw_data,
                use_cache=True
            )
            
            # Check if synthesis failed
            is_error = (
                not synthesized_content or
                'Unable to synthesize' in synthesized_content or
                'Analysis temporarily unavailable' in synthesized_content or
                'API Status: 429' in synthesized_content
            )
            
            if is_error:
                logger.warning("Synthesis failed, using fallback")
                # Return formatted raw data
                fallback_content = "\n".join([f"• {s}" for s in unique_snippets[:3]])
                return {
                    'content': fallback_content,
                    'evidence': [e.model_dump() for e in evidence],
                    'needs_retry': True
                }
            
            return {
                'content': synthesized_content.strip(),
                'evidence': [e.model_dump() for e in evidence],
                'needs_retry': False
            }
        
        except Exception as e:
            logger.error(f"Synthesis error: {e}")
            fallback_content = "\n".join([f"• {s}" for s in unique_snippets[:3]])
            return {
                'content': fallback_content,
                'evidence': [e.model_dump() for e in evidence],
                'needs_retry': True
            }
    
    async def generate_final_plan(
        self,
        company_name: str,
        all_sections: Dict[str, str]
    ) -> str:
        """
        Generate final executive summary
        
        Args:
            company_name: Company name
            all_sections: All completed sections
            
        Returns:
            Executive summary text
        """
        logger.info(f"Generating final plan for {company_name}")
        
        # Build context from all sections
        context = f"Company: {company_name}\n\n"
        for section, content in all_sections.items():
            context += f"{section.replace('_', ' ').title()}:\n{content}\n\n"
        
        prompt = (
            f"Create a comprehensive executive summary that synthesizes all research for {company_name}.\n\n"
            "Instructions:\n"
            "- Summarize company overview, financial health, products, and competitive position\n"
            "- Highlight key pain points and opportunities\n"
            "- Conclude with top 3 strategic recommendations\n"
            "- Use clear structure with section headings\n"
            "- Write complete sentences - do NOT use ellipsis or '...'\n"
            "- Single line breaks between sections\n"
            "- Maximum 400 words\n"
            "- Professional, concise, actionable tone"
        )
        
        try:
            summary = await gemini_client.synthesize_text(
                instruction=prompt,
                raw_data=context,
                use_cache=True
            )
            return summary.strip()
        except Exception as e:
            logger.error(f"Final plan generation error: {e}")
            return f"Executive Summary for {company_name}\n\nUnable to generate complete summary at this time."
    
    def _get_default_prompt(self) -> str:
        """Get default synthesis prompt"""
        return (
            "Summarize the following information concisely.\n"
            "Remove duplicate content. Use clear structure. Maximum 200 words.\n"
            "Write complete sentences - do NOT truncate. Single line breaks between bullets."
        )


# Global synthesizer instance
synthesizer = Synthesizer()
