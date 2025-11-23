"""
Conflict Detection Service
"""
import re
from typing import List, Optional, Dict, Any
from loguru import logger

from models.schemas import Conflict, ConflictValue, Button
from config.settings import settings


class ConflictDetector:
    """Detects conflicts in research data"""
    
    def __init__(self):
        """Initialize conflict detector"""
        self.numeric_threshold = settings.numeric_conflict_threshold
        logger.info(f"ConflictDetector initialized with {self.numeric_threshold*100}% threshold")
    
    def detect_conflicts(
        self,
        current_content: str,
        previous_content: str,
        step_name: str
    ) -> List[Conflict]:
        """
        Detect conflicts between current and previous data
        
        Args:
            current_content: Current step content
            previous_content: Previous step content
            step_name: Step name for context
            
        Returns:
            List of detected conflicts
        """
        conflicts = []
        
        # Detect step-specific conflicts
        if step_name in ['company_basics', 'company_overview']:
            conflicts.extend(self._detect_basic_info_conflicts(current_content, previous_content))
        elif step_name in ['financial', 'financial_overview']:
            conflicts.extend(self._detect_financial_conflicts(current_content, previous_content))
        
        return conflicts
    
    def _detect_basic_info_conflicts(self, current: str, previous: str) -> List[Conflict]:
        """Detect basic company info conflicts"""
        conflicts = []
        
        # Employee count conflict
        current_emp = self._extract_employee_count(current)
        previous_emp = self._extract_employee_count(previous)
        
        if current_emp and previous_emp:
            diff_ratio = abs(current_emp - previous_emp) / max(current_emp, previous_emp)
            if diff_ratio > self.numeric_threshold:
                conflicts.append(Conflict(
                    field='employees',
                    question="I'm seeing two employee counts for this company:",
                    values=[
                        ConflictValue(label=f"{current_emp:,} employees", value=current_emp),
                        ConflictValue(label=f"{previous_emp:,} employees", value=previous_emp)
                    ],
                    current=current,
                    previous=previous
                ))
        
        # Headquarters conflict
        current_hq = self._extract_headquarters(current)
        previous_hq = self._extract_headquarters(previous)
        
        if current_hq and previous_hq and current_hq != previous_hq:
            conflicts.append(Conflict(
                field='headquarters',
                question="Two different headquarters are listed:",
                values=[
                    ConflictValue(label=current_hq, value=current_hq),
                    ConflictValue(label=previous_hq, value=previous_hq)
                ],
                current=current,
                previous=previous
            ))
        
        return conflicts
    
    def _detect_financial_conflicts(self, current: str, previous: str) -> List[Conflict]:
        """Detect financial data conflicts"""
        conflicts = []
        
        # Revenue conflict
        current_rev = self._extract_revenue(current)
        previous_rev = self._extract_revenue(previous)
        
        if current_rev and previous_rev:
            diff_ratio = abs(current_rev['amount'] - previous_rev['amount']) / max(current_rev['amount'], previous_rev['amount'])
            if diff_ratio > self.numeric_threshold:
                conflicts.append(Conflict(
                    field='revenue',
                    question="I found two different revenue figures:",
                    values=[
                        ConflictValue(label=current_rev['label'], value=current_rev['amount']),
                        ConflictValue(label=previous_rev['label'], value=previous_rev['amount'])
                    ],
                    current=current,
                    previous=previous
                ))
        
        return conflicts
    
    def _extract_employee_count(self, text: str) -> Optional[int]:
        """Extract employee count from text"""
        patterns = [
            r'(\d{1,3}(?:,\d{3})*|\d+)\s*(?:employees|staff|people|workforce)',
            r'employee.*?(\d{1,3}(?:,\d{3})*|\d+)',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                num_str = match.group(1).replace(',', '')
                try:
                    return int(num_str)
                except ValueError:
                    continue
        return None
    
    def _extract_headquarters(self, text: str) -> Optional[str]:
        """Extract headquarters location from text"""
        patterns = [
            r'headquarters?[:\s]+([^.\n;]+)',
            r'headquartered in ([^.\n;]+)',
            r'HQ[:\s]+([^.\n;]+)',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                return match.group(1).strip()
        return None
    
    def _extract_revenue(self, text: str) -> Optional[Dict[str, Any]]:
        """Extract revenue figure from text"""
        patterns = [
            r'([\$₹€£]?\s*\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(billion|million|crore|cr|b|m)',
            r'revenue[:\s]+([\$₹€£]?\s*\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(billion|million|crore|cr|b|m)',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                amount_str = re.sub(r'[^\d.]', '', match.group(1))
                try:
                    amount = float(amount_str)
                except ValueError:
                    continue
                
                unit = match.group(2).lower()
                multiplier = {
                    'billion': 1_000_000_000, 'b': 1_000_000_000,
                    'million': 1_000_000, 'm': 1_000_000,
                    'crore': 10_000_000, 'cr': 10_000_000
                }.get(unit, 1)
                
                return {
                    'amount': amount * multiplier,
                    'label': match.group(0).strip()
                }
        return None
    
    def format_conflict_question(self, conflicts: List[Conflict]) -> str:
        """
        Format conflicts into user question
        
        Args:
            conflicts: List of conflicts
            
        Returns:
            Formatted question string
        """
        if not conflicts:
            return ""
        
        conflict = conflicts[0]  # Handle first conflict
        question = conflict.question + "\n"
        for value in conflict.values:
            question += f"• {value.label}\n"
        question += "\nWhich value should I use?"
        
        return question
    
    def create_conflict_buttons(self, conflicts: List[Conflict]) -> List[Button]:
        """
        Create buttons for conflict resolution
        
        Args:
            conflicts: List of conflicts
            
        Returns:
            List of Button objects
        """
        if not conflicts:
            return []
        
        buttons = []
        conflict = conflicts[0]  # Handle first conflict
        
        # Value selection buttons
        for i, value in enumerate(conflict.values):
            buttons.append(Button(
                text=f"Use {value.label}",
                value=f"conflict_0_value_{i}"
            ))
        
        # Utility buttons
        buttons.extend([
            Button(text="Verify official source", value="conflict_0_verify"),
            Button(text="Skip this field", value="conflict_0_skip")
        ])
        
        return buttons


# Global detector instance
conflict_detector = ConflictDetector()
