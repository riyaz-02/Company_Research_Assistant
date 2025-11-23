"""
Gemini API Client with Retry Logic and Load Balancing
"""
import httpx
from tenacity import (
    retry,
    stop_after_attempt,
    wait_fixed,
    retry_if_exception_type,
    before_sleep_log
)
from typing import Optional, Dict, Any
from loguru import logger

from config.settings import settings
from services.key_manager import KeyManager
from services.throttler import Throttler
from services.cache import cache
from models.schemas import GeminiRequest, GeminiResponse


class RateLimitError(Exception):
    """Raised when API rate limit is hit"""
    pass


class GeminiClient:
    """Async Gemini API client with advanced retry and throttling"""
    
    def __init__(self):
        """Initialize Gemini client"""
        self.key_manager = KeyManager(settings.get_api_keys())
        self.throttler = Throttler(
            min_delay=settings.throttle_min_delay,
            max_delay=settings.throttle_max_delay
        )
        self.endpoint_base = settings.gemini_api_url
        self.model = settings.gemini_model

        logger.info(f"GeminiClient initialized with model: {self.model}")

    def _build_endpoint(self, api_key: str) -> str:
        """Build full Gemini API endpoint with key"""
        # endpoint_base already includes v1beta/models
        return f"{self.endpoint_base}/{self.model}:generateContent?key={api_key}"
    
    async def call_with_retry(
        self,
        prompt: str,
        temperature: float = 0.7,
        max_output_tokens: Optional[int] = None,
        use_cache: bool = True
    ) -> GeminiResponse:
        """
        Call Gemini API with automatic retry and throttling
        
        Args:
            prompt: Prompt text
            temperature: Generation temperature
            max_output_tokens: Max tokens in response
            use_cache: Whether to use cache
            
        Returns:
            GeminiResponse with text and cached flag
            
        Raises:
            RateLimitError: If all retries exhausted
            Exception: For other API errors
        """
        max_output_tokens = max_output_tokens or settings.max_output_tokens
        
        # Check cache first
        if use_cache:
            cached = cache.get_gemini_response(prompt)
            if cached:
                logger.info("Using cached Gemini response")
                return GeminiResponse(text=cached['text'], cached=True)
        
        # Throttle before API call
        await self.throttler.wait()
        
        # Try with custom retry schedule
        response_text = await self._call_with_custom_retry(
            prompt=prompt,
            temperature=temperature,
            max_output_tokens=max_output_tokens
        )
        
        # Cache successful response
        if use_cache and response_text:
            cache.set_gemini_response(prompt, {'text': response_text})
            logger.debug("Cached Gemini response")
        
        return GeminiResponse(text=response_text, cached=False)
    
    async def _call_with_custom_retry(
        self,
        prompt: str,
        temperature: float,
        max_output_tokens: int,
        attempt: int = 0
    ) -> str:
        """
        Internal method with custom retry schedule
        
        Args:
            prompt: Prompt text
            temperature: Temperature
            max_output_tokens: Max output tokens
            attempt: Current attempt number
            
        Returns:
            Response text
        """
        retry_delays = settings.get_retry_delays()
        max_attempts = len(retry_delays) + 1
        
        while attempt < max_attempts:
            try:
                api_key = self.key_manager.get_key()
                if not api_key:
                    raise Exception("No API keys available")
                
                endpoint = self._build_endpoint(api_key)
                
                async with httpx.AsyncClient(timeout=60.0) as client:
                    response = await client.post(
                        endpoint,
                        json={
                            'contents': [
                                {
                                    'parts': [
                                        {'text': prompt}
                                    ]
                                }
                            ],
                            'generationConfig': {
                                'temperature': temperature,
                                'maxOutputTokens': max_output_tokens
                            }
                        }
                    )
                    
                    if response.status_code == 200:
                        result = response.json()
                        text = result.get('candidates', [{}])[0].get('content', {}).get('parts', [{}])[0].get('text', '')
                        
                        if not text:
                            logger.warning("Empty response from Gemini")
                            return "Unable to generate response at this time."
                        
                        logger.info(f"Gemini response received: {len(text)} chars")
                        return text
                    
                    elif response.status_code == 429:
                        # Rate limit hit
                        if attempt < max_attempts - 1:
                            delay = retry_delays[attempt]
                            logger.warning(f"Rate limit hit, retrying in {delay}s (attempt {attempt + 1}/{max_attempts})")
                            import asyncio
                            await asyncio.sleep(delay)
                            attempt += 1
                            continue
                        else:
                            logger.error("Rate limit: all retries exhausted")
                            raise RateLimitError("Gemini API rate limit exceeded after all retries")
                    
                    else:
                        logger.error(f"Gemini API error: {response.status_code} - {response.text[:200]}")
                        raise Exception(f"API error: {response.status_code}")
            
            except RateLimitError:
                raise
            except Exception as e:
                if attempt < max_attempts - 1:
                    delay = retry_delays[attempt]
                    logger.warning(f"API call failed: {e}, retrying in {delay}s")
                    import asyncio
                    await asyncio.sleep(delay)
                    attempt += 1
                else:
                    logger.error(f"API call failed after all retries: {e}")
                    raise
        
        raise Exception("Maximum retries exceeded")
    
    async def synthesize_text(
        self,
        instruction: str,
        raw_data: str,
        use_cache: bool = True
    ) -> str:
        """
        Synthesize raw data into clean text
        
        Args:
            instruction: Synthesis instructions
            raw_data: Raw text to synthesize
            use_cache: Whether to use cache
            
        Returns:
            Synthesized text
        """
        prompt = f"{instruction}\n\nRaw Data:\n{raw_data}\n\nProvide synthesized output as plain text."
        
        try:
            response = await self.call_with_retry(prompt, use_cache=use_cache)
            return response.text
        except RateLimitError:
            logger.error("Rate limit during synthesis")
            return "Analysis temporarily unavailable. Please try again in a moment. (API Status: 429)"
        except Exception as e:
            logger.error(f"Synthesis error: {e}")
            return f"Unable to synthesize data at this time. ({str(e)})"


# Global client instance
gemini_client = GeminiClient()
