"""
Request Throttler to Prevent Rate Limiting
"""
import asyncio
import random
import time
from typing import Optional
from loguru import logger


class Throttler:
    """Enforces minimum spacing between API requests"""
    
    def __init__(self, min_delay: float = 2.0, max_delay: float = 4.0):
        """
        Initialize throttler
        
        Args:
            min_delay: Minimum delay in seconds
            max_delay: Maximum delay in seconds
        """
        self.min_delay = min_delay
        self.max_delay = max_delay
        self.last_request_time: Optional[float] = None
        self._lock = asyncio.Lock()
        
        logger.info(f"Throttler initialized: {min_delay}s - {max_delay}s delay")
    
    async def wait(self):
        """
        Wait appropriate time before allowing next request
        Implements random jitter between min and max delay
        """
        async with self._lock:
            now = time.time()
            
            if self.last_request_time is not None:
                elapsed = now - self.last_request_time
                
                # Calculate required delay with jitter
                required_delay = random.uniform(self.min_delay, self.max_delay)
                
                if elapsed < required_delay:
                    wait_time = required_delay - elapsed
                    logger.debug(f"Throttling: waiting {wait_time:.2f}s")
                    await asyncio.sleep(wait_time)
            
            self.last_request_time = time.time()
    
    def wait_sync(self):
        """Synchronous version of wait() for non-async code"""
        now = time.time()
        
        if self.last_request_time is not None:
            elapsed = now - self.last_request_time
            required_delay = random.uniform(self.min_delay, self.max_delay)
            
            if elapsed < required_delay:
                wait_time = required_delay - elapsed
                logger.debug(f"Throttling (sync): waiting {wait_time:.2f}s")
                time.sleep(wait_time)
        
        self.last_request_time = time.time()
    
    def reset(self):
        """Reset throttler state"""
        self.last_request_time = None
        logger.debug("Throttler reset")
