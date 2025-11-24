"""
PDF Generation Service
Generates professional research reports
"""
import os
from typing import Dict, Any
from loguru import logger
from datetime import datetime
from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.lib.units import inch
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY


class PDFGenerator:
    """Generates PDF reports from research data"""
    
    def __init__(self):
        logger.info("PDFGenerator initialized")
    
    def create_pdf(self, company: str, research_data: Dict[str, Any], output_path: str) -> bool:
        """
        Create a professional PDF report from research data
        
        Args:
            company: Company name
            research_data: Dictionary containing research data for each step
            output_path: Path where PDF should be saved
            
        Returns:
            True if successful, False otherwise
        """
        try:
            logger.info(f"Creating PDF for {company} at {output_path}")
            logger.info(f"Research data keys: {list(research_data.keys())}")
            
            # Create the PDF document
            doc = SimpleDocTemplate(output_path, pagesize=letter,
                                    rightMargin=72, leftMargin=72,
                                    topMargin=72, bottomMargin=18)
            
            # Container for the 'Flowable' objects
            elements = []
            
            # Get styles
            styles = getSampleStyleSheet()
            
            # Custom styles
            title_style = ParagraphStyle(
                'CustomTitle',
                parent=styles['Heading1'],
                fontSize=24,
                textColor=colors.HexColor('#1a202c'),
                spaceAfter=30,
                alignment=TA_CENTER
            )
            
            heading_style = ParagraphStyle(
                'CustomHeading',
                parent=styles['Heading2'],
                fontSize=16,
                textColor=colors.HexColor('#2d3748'),
                spaceAfter=12,
                spaceBefore=12
            )
            
            body_style = ParagraphStyle(
                'CustomBody',
                parent=styles['BodyText'],
                fontSize=11,
                alignment=TA_JUSTIFY,
                spaceAfter=12
            )
            
            # Add title
            title = Paragraph(f"<b>Research Report: {company}</b>", title_style)
            elements.append(title)
            elements.append(Spacer(1, 12))
            
            # Add generation date
            date_text = Paragraph(f"<i>Generated on {datetime.now().strftime('%B %d, %Y at %I:%M %p')}</i>", 
                                  styles['Normal'])
            elements.append(date_text)
            elements.append(Spacer(1, 24))
            
            # Step names mapping
            step_names = {
                'overview': 'Company Overview',
                'financials': 'Financial Analysis',
                'products': 'Products & Services',
                'competitors': 'Competitive Analysis',
                'pain_points': 'Pain Points & Challenges',
                'opportunities': 'Opportunities',
                'recommendations': 'Strategic Recommendations'
            }
            
            # Add each research section
            for step, data in research_data.items():
                step_title = step_names.get(step, step.replace('_', ' ').title())
                
                # Add section heading
                heading = Paragraph(f"<b>{step_title}</b>", heading_style)
                elements.append(heading)
                
                # Extract text content
                if isinstance(data, dict):
                    text_content = data.get('text', '')
                elif isinstance(data, str):
                    text_content = data
                else:
                    text_content = str(data)
                
                # Add section content
                if text_content:
                    # Split into paragraphs and add each
                    paragraphs = text_content.split('\n\n')
                    for para in paragraphs:
                        if para.strip():
                            # Clean up the text - escape XML special characters
                            para = para.strip()
                            para = para.replace('&', '&amp;')
                            para = para.replace('<', '&lt;')
                            para = para.replace('>', '&gt;')
                            
                            p = Paragraph(para, body_style)
                            elements.append(p)
                            elements.append(Spacer(1, 6))
                
                elements.append(Spacer(1, 12))
            
            # Add footer note
            elements.append(Spacer(1, 24))
            footer = Paragraph(
                "<i>This report was automatically generated by the Company Research Assistant.</i>",
                styles['Normal']
            )
            elements.append(footer)
            
            # Build PDF
            doc.build(elements)
            
            logger.info(f"PDF successfully created at {output_path}")
            return True
            
        except Exception as e:
            logger.error(f"Error creating PDF: {e}", exc_info=True)
            return False


# Global instance
pdf_generator = PDFGenerator()
