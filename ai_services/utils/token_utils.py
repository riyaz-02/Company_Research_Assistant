"""
Token Usage Estimation and Management
"""
import re
from typing import Dict, Any


def estimate_tokens(text: str) -> int:
    """
    Estimate token count (rough approximation)
    Rule of thumb: 1 token ≈ 4 characters for English text
    
    Args:
        text: Input text
        
    Returns:
        Estimated token count
    """
    if not text:
        return 0
    
    # Remove extra whitespace
    cleaned = re.sub(r'\s+', ' ', text).strip()
    
    # Rough estimate: 1 token per 4 characters
    return len(cleaned) // 4


def get_prompt_stats(prompt: str) -> Dict[str, Any]:
    """
    Get statistics about a prompt
    
    Args:
        prompt: Prompt text
        
    Returns:
        Dict with char_count, estimated_tokens, word_count
    """
    char_count = len(prompt)
    word_count = len(prompt.split())
    estimated_tokens = estimate_tokens(prompt)
    
    return {
        'char_count': char_count,
        'word_count': word_count,
        'estimated_tokens': estimated_tokens
    }


def check_token_limit(text: str, max_tokens: int = 2048) -> tuple[bool, int]:
    """
    Check if text exceeds token limit
    
    Args:
        text: Text to check
        max_tokens: Maximum allowed tokens
        
    Returns:
        Tuple of (within_limit: bool, estimated_tokens: int)
    """
    tokens = estimate_tokens(text)
    return (tokens <= max_tokens, tokens)


def split_into_chunks(text: str, max_tokens_per_chunk: int = 1000) -> list[str]:
    """
    Split text into chunks that fit within token limit
    
    Args:
        text: Text to split
        max_tokens_per_chunk: Max tokens per chunk
        
    Returns:
        List of text chunks
    """
    # Approximate character limit
    max_chars = max_tokens_per_chunk * 4
    
    if len(text) <= max_chars:
        return [text]
    
    # Split by paragraphs first
    paragraphs = text.split('\n\n')
    
    chunks = []
    current_chunk = []
    current_length = 0
    
    for para in paragraphs:
        para_length = len(para)
        
        if current_length + para_length > max_chars:
            # Save current chunk
            if current_chunk:
                chunks.append('\n\n'.join(current_chunk))
            # Start new chunk
            current_chunk = [para]
            current_length = para_length
        else:
            current_chunk.append(para)
            current_length += para_length + 2  # +2 for \n\n
    
    # Add remaining chunk
    if current_chunk:
        chunks.append('\n\n'.join(current_chunk))
    
    return chunks
