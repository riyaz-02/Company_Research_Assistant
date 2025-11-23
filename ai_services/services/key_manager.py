"""
API Key Manager with Rotation and Load Balancing
"""
import random
from typing import List, Optional
from loguru import logger


class KeyManager:
    """Manages multiple Gemini API keys with rotation"""
    
    def __init__(self, api_keys: List[str]):
        """
        Initialize key manager
        
        Args:
            api_keys: List of Gemini API keys
        """
        self.keys = [key for key in api_keys if key and key.strip()]
        self.current_index = 0
        self.failed_keys = set()
        
        if not self.keys:
            logger.warning("No API keys provided - service will fail")
        else:
            logger.info(f"KeyManager initialized with {len(self.keys)} keys")
    
    def get_key(self) -> Optional[str]:
        """
        Get next available API key using round-robin
        
        Returns:
            API key or None if all failed
        """
        if not self.keys:
            logger.error("No API keys available")
            return None
        
        # Get available keys (not in failed set)
        available = [k for k in self.keys if k not in self.failed_keys]
        
        if not available:
            logger.warning("All keys marked as failed, resetting...")
            self.failed_keys.clear()
            available = self.keys
        
        # Round-robin selection
        key = available[self.current_index % len(available)]
        self.current_index = (self.current_index + 1) % len(available)
        
        return key
    
    def get_random_key(self) -> Optional[str]:
        """
        Get random available API key
        
        Returns:
            API key or None
        """
        available = [k for k in self.keys if k not in self.failed_keys]
        
        if not available:
            self.failed_keys.clear()
            available = self.keys
        
        return random.choice(available) if available else None
    
    def mark_failed(self, key: str):
        """
        Mark a key as failed (temporarily unusable)
        
        Args:
            key: API key that failed
        """
        self.failed_keys.add(key)
        logger.warning(f"Marked key as failed: {key[:10]}... ({len(self.failed_keys)}/{len(self.keys)} failed)")
    
    def reset_failures(self):
        """Reset all failed key markers"""
        count = len(self.failed_keys)
        self.failed_keys.clear()
        if count > 0:
            logger.info(f"Reset {count} failed keys")
    
    def get_stats(self) -> dict:
        """
        Get key manager statistics
        
        Returns:
            Dict with total_keys, available_keys, failed_keys
        """
        return {
            'total_keys': len(self.keys),
            'available_keys': len([k for k in self.keys if k not in self.failed_keys]),
            'failed_keys': len(self.failed_keys)
        }
