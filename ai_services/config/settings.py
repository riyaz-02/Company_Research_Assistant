"""
Application settings with environment variable parsing
"""
from typing import List, Optional
from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import Field


class Settings(BaseSettings):
    """Application settings"""
    
    # Gemini API Configuration
    gemini_api_keys: str = Field(default="")
    gemini_api_url: str = "https://generativelanguage.googleapis.com/v1beta/models"
    gemini_model: str = "gemini-1.5-flash"
    
    # Throttling Configuration
    throttle_min_delay: float = 2.0
    throttle_max_delay: float = 4.0
    
    # Retry Configuration
    max_retries: int = 4
    retry_delays: str = Field(default="3,8,20,45")
    
    # Cache Configuration
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0
    redis_password: Optional[str] = None
    cache_ttl: int = 7200  # 2 hours
    
    # Token Limits
    max_output_tokens: int = 2048
    max_input_tokens: int = 8000
    max_snippet_length: int = 200
    max_snippets_per_step: int = 3
    
    # Conflict Detection
    numeric_conflict_threshold: float = 0.1  # 10% difference
    
    # Server Configuration
    host: str = "0.0.0.0"
    port: int = 8000
    workers: int = 1
    log_level: str = "INFO"
    log_file: str = "logs/ai_service.log"
    
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore"
    )
    
    def get_api_keys(self) -> List[str]:
        """Get API keys as list"""
        if isinstance(self.gemini_api_keys, str):
            return [k.strip() for k in self.gemini_api_keys.split(",") if k.strip()]
        return []
    
    def get_retry_delays(self) -> List[int]:
        """Get retry delays as list"""
        if isinstance(self.retry_delays, str):
            return [int(d.strip()) for d in self.retry_delays.split(",") if d.strip()]
        return [3, 8, 20, 45]


# Global settings instance
settings = Settings()
