"""
Text Cleaning and Token Optimization Utilities
"""
import re
from typing import List, Set


def clean_snippet(snippet: str, max_length: int = 200) -> str:
    """
    Clean a single search result snippet
    
    Args:
        snippet: Raw snippet text
        max_length: Maximum length after cleaning
        
    Returns:
        Cleaned snippet text
    """
    if not snippet:
        return ""
    
    # Remove all newlines and carriage returns
    text = snippet.replace('\r\n', ' ').replace('\n', ' ').replace('\r', ' ')
    
    # Collapse multiple spaces to single space
    text = re.sub(r'\s+', ' ', text)
    
    # Remove trailing ellipsis and dots
    text = text.rstrip('.').rstrip('…')
    
    # Remove leading/trailing whitespace
    text = text.strip()
    
    # Truncate to max length
    if len(text) > max_length:
        text = text[:max_length].rstrip()
    
    return text


def remove_duplicates(snippets: List[str]) -> List[str]:
    """
    Remove duplicate or highly similar snippets
    
    Args:
        snippets: List of text snippets
        
    Returns:
        Deduplicated list
    """
    if not snippets:
        return []
    
    unique_snippets = []
    seen_normalized: Set[str] = set()
    
    for snippet in snippets:
        # Normalize for comparison (lowercase, remove punctuation)
        normalized = re.sub(r'[^\w\s]', '', snippet.lower())
        normalized = re.sub(r'\s+', ' ', normalized).strip()
        
        # Skip if we've seen very similar content
        if normalized not in seen_normalized:
            unique_snippets.append(snippet)
            seen_normalized.add(normalized)
    
    return unique_snippets


def extract_key_sentences(text: str, max_sentences: int = 3) -> str:
    """
    Extract key sentences from longer text
    
    Args:
        text: Input text
        max_sentences: Maximum sentences to extract
        
    Returns:
        Extracted key sentences
    """
    # Split into sentences (simple approach)
    sentences = re.split(r'[.!?]+', text)
    sentences = [s.strip() for s in sentences if s.strip() and len(s.strip()) > 20]
    
    # Take first N sentences (usually most important)
    key_sentences = sentences[:max_sentences]
    
    return '. '.join(key_sentences) + ('.' if key_sentences else '')


def compress_long_list(text: str, max_items: int = 5) -> str:
    """
    Compress long bullet lists
    
    Args:
        text: Text with potential list items
        max_items: Maximum items to keep
        
    Returns:
        Compressed text
    """
    # Detect bullet points or numbered lists
    lines = text.split('\n')
    list_items = []
    other_lines = []
    
    for line in lines:
        stripped = line.strip()
        if stripped and (stripped[0] in ['•', '-', '*'] or (stripped[0].isdigit() and '.' in stripped[:3])):
            list_items.append(stripped)
        elif stripped:
            other_lines.append(stripped)
    
    # If too many list items, truncate
    if len(list_items) > max_items:
        list_items = list_items[:max_items]
        list_items.append(f"...and {len(list_items) - max_items} more")
    
    return '\n'.join(other_lines + list_items)


def clean_search_results(results: List[dict], max_snippets: int = 3, max_length: int = 200) -> List[dict]:
    """
    Clean and optimize search results
    
    Args:
        results: List of search result dicts with 'snippet' key
        max_snippets: Maximum snippets to keep
        max_length: Max length per snippet
        
    Returns:
        Cleaned search results
    """
    cleaned = []
    
    for result in results[:max_snippets]:
        snippet = result.get('snippet', '')
        
        if not snippet:
            continue
        
        cleaned_snippet = clean_snippet(snippet, max_length)
        
        if cleaned_snippet:
            cleaned.append({
                **result,
                'snippet': cleaned_snippet
            })
    
    return cleaned


def normalize_whitespace(text: str) -> str:
    """
    Normalize all whitespace in text
    
    Args:
        text: Input text
        
    Returns:
        Text with normalized whitespace
    """
    # Replace all whitespace sequences with single space
    text = re.sub(r'\s+', ' ', text)
    
    # Remove leading/trailing whitespace
    text = text.strip()
    
    return text


def remove_special_chars(text: str) -> str:
    """
    Remove problematic special characters
    
    Args:
        text: Input text
        
    Returns:
        Text with special chars removed
    """
    # Remove control characters except newline and tab
    text = re.sub(r'[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]', '', text)
    
    # Normalize quotes
    text = text.replace('"', '"').replace('"', '"')
    text = text.replace(''', "'").replace(''', "'")
    
    # Remove zero-width characters
    text = re.sub(r'[\u200B-\u200D\uFEFF]', '', text)
    
    return text


def trim_to_token_limit(text: str, approx_token_limit: int = 1000) -> str:
    """
    Trim text to approximate token limit
    (rough estimate: 1 token ≈ 4 characters)
    
    Args:
        text: Input text
        approx_token_limit: Approximate token limit
        
    Returns:
        Trimmed text
    """
    char_limit = approx_token_limit * 4
    
    if len(text) <= char_limit:
        return text
    
    # Trim at sentence boundary if possible
    trimmed = text[:char_limit]
    last_period = trimmed.rfind('.')
    
    if last_period > char_limit * 0.8:  # If we can find a sentence within 80% of limit
        return trimmed[:last_period + 1]
    
    return trimmed + '...'
