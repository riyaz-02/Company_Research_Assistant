"""
Caching layer with Redis or in-memory fallback
"""
import hashlib
from typing import Optional, Any, TYPE_CHECKING
from loguru import logger

if TYPE_CHECKING:
    import redis

REDIS_AVAILABLE = False
try:
    import redis
    REDIS_AVAILABLE = True
except ImportError:
    logger.warning("Redis not available, using in-memory cache")

from config.settings import settings


class Cache:
    """Cache implementation with Redis or in-memory fallback"""
    
    def __init__(self):
        self.redis_client: Optional[Any] = None
        self.memory_cache: dict = {}
        
        if REDIS_AVAILABLE:
            try:
                self.redis_client = redis.Redis(  # type: ignore
                    host=settings.redis_host,
                    port=settings.redis_port,
                    db=settings.redis_db,
                    password=settings.redis_password,
                    decode_responses=True,
                    socket_connect_timeout=2
                )
                # Test connection
                self.redis_client.ping()  # type: ignore
                logger.info(f"Connected to Redis at {settings.redis_host}:{settings.redis_port}")
            except Exception as e:
                logger.warning(f"Redis connection failed: {e}. Using in-memory cache.")
                self.redis_client = None
    
    def get(self, key: str) -> Optional[str]:
        """Get value from cache"""
        try:
            if self.redis_client:
                result = self.redis_client.get(key)  # type: ignore
                return str(result) if result is not None else None
            else:
                return self.memory_cache.get(key)
        except Exception as e:
            logger.error(f"Cache get error: {e}")
            return None
    
    def set(self, key: str, value: str, ttl: Optional[int] = None) -> bool:
        """Set value in cache with optional TTL"""
        try:
            if ttl is None:
                ttl = settings.cache_ttl
            
            if self.redis_client:
                self.redis_client.setex(key, ttl, value)  # type: ignore
            else:
                self.memory_cache[key] = value
            return True
        except Exception as e:
            logger.error(f"Cache set error: {e}")
            return False
    
    def delete(self, key: str) -> bool:
        """Delete key from cache"""
        try:
            if self.redis_client:
                self.redis_client.delete(key)  # type: ignore
            else:
                self.memory_cache.pop(key, None)
            return True
        except Exception as e:
            logger.error(f"Cache delete error: {e}")
            return False
    
    def clear_pattern(self, pattern: str) -> int:
        """Clear all keys matching pattern"""
        try:
            if self.redis_client:
                keys = self.redis_client.keys(pattern)  # type: ignore
                if keys:
                    deleted = self.redis_client.delete(*keys)  # type: ignore
                    return int(deleted) if deleted else 0
                return 0
            else:
                # For memory cache, simple prefix matching
                keys_to_delete = [k for k in self.memory_cache.keys() if pattern.replace('*', '') in k]
                for k in keys_to_delete:
                    del self.memory_cache[k]
                return len(keys_to_delete)
        except Exception as e:
            logger.error(f"Cache clear pattern error: {e}")
            return 0
    
    def _make_cache_key(self, prefix: str, *args) -> str:
        """Create a cache key from prefix and arguments"""
        key_parts = [str(arg) for arg in args]
        key_string = ':'.join([prefix] + key_parts)
        return key_string
    
    def _hash_text(self, text: str) -> str:
        """Create MD5 hash of text for cache key"""
        return hashlib.md5(text.encode()).hexdigest()
    
    # Gemini-specific cache methods
    
    def get_gemini_response(self, prompt: str) -> Optional[str]:
        """Get cached Gemini response"""
        key = f"gemini:{self._hash_text(prompt)}"
        return self.get(key)
    
    def set_gemini_response(self, prompt: str, response: str, ttl: Optional[int] = None) -> bool:
        """Cache Gemini response"""
        key = f"gemini:{self._hash_text(prompt)}"
        return self.set(key, response, ttl)
    
    # Session-specific cache methods
    
    def get_synthesis(self, session_id: str, step: str) -> Optional[dict]:
        """Get cached synthesis result"""
        import json
        key = self._make_cache_key('synthesis', session_id, step)
        cached = self.get(key)
        if cached:
            try:
                return json.loads(cached)
            except json.JSONDecodeError:
                return None
        return None
    
    def set_synthesis(self, session_id: str, step: str, data: dict, ttl: Optional[int] = None) -> bool:
        """Cache synthesis result"""
        import json
        key = self._make_cache_key('synthesis', session_id, step)
        return self.set(key, json.dumps(data), ttl)
    
    def clear_session(self, session_id: str) -> int:
        """Clear all cache for a session"""
        pattern = f"synthesis:{session_id}:*"
        return self.clear_pattern(pattern)

    def get_analysis(self, session_id: str, cache_key: str) -> Optional[dict]:
        """Get cached analysis result"""
        import json
        key = self._make_cache_key('analysis', session_id, cache_key)
        cached = self.get(key)
        if cached:
            try:
                return json.loads(cached)
            except json.JSONDecodeError:
                return None
        return None

    def store_analysis(self, session_id: str, cache_key: str, data: dict, ttl: Optional[int] = None) -> bool:
        """Cache analysis result"""
        import json
        key = self._make_cache_key('analysis', session_id, cache_key)
        return self.set(key, json.dumps(data), ttl)


# Global cache instance
cache = Cache()
