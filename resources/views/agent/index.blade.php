<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Company Research Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            gap: 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .summary-container {
            width: 400px;
            background: white;
            border-left: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .summary-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .summary-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f7f9fc;
        }
        
        .summary-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .summary-item h4 {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .summary-item p {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f7f9fc;
        }
        
        .message {
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.user {
            text-align: right;
        }
        
        .message.assistant {
            text-align: left;
        }
        
        .message-content {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 18px;
            max-width: 70%;
            word-wrap: break-word;
        }
        
        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .message.assistant .message-content {
            background: white;
            color: #333;
            border: 1px solid #e5e7eb;
            max-width: 85%;
        }
        
        .research-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            max-width: 90%;
            display: inline-block;
        }
        
        .research-card h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .research-card p {
            color: #4b5563;
            line-height: 1.6;
            white-space: pre-wrap;
            margin-bottom: 16px;
        }
        
        .chart-container {
            margin-top: 16px;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
        }
        
        .chat-input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .chat-input:focus {
            border-color: #667eea;
        }
        
        .send-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 24px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .thinking {
            display: inline-block;
            padding: 12px 18px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            color: #667eea;
            font-style: italic;
        }
        
        .thinking::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="chat-container">
            <div class="chat-header">
                <h1 style="font-size: 24px; font-weight: 700; margin: 0;">AI Company Research Assistant</h1>
                <p style="font-size: 14px; opacity: 0.9; margin-top: 5px;">Intelligent research with data visualization</p>
            </div>
            
            <div class="chat-messages" id="chatMessages"></div>
            
            <div class="chat-input-area">
                <input 
                    type="text" 
                    id="messageInput" 
                    class="chat-input" 
                    placeholder="Type a message or company name..."
                    autocomplete="off"
                />
                <button id="sendButton" class="send-button">Send</button>
            </div>
        </div>
        
        <div class="summary-container">
            <div class="summary-header">
                <h2 style="font-size: 20px; font-weight: 700; margin: 0;">Research Summary</h2>
                <p style="font-size: 13px; opacity: 0.9; margin-top: 5px;">Collected research data</p>
            </div>
            <div class="summary-content" id="summaryContent">
                <p style="color: #9ca3af; text-align: center; margin-top: 40px;">No research data yet. Start by entering a company name!</p>
            </div>
        </div>
    </div>

    <script>
        let sessionId = null; // Always start fresh on page load
        let researchSummary = [];
        let currentCompany = null;
        
        // Clear session data on page load
        localStorage.removeItem('session_id');
        localStorage.removeItem('research_summary');
        localStorage.removeItem('current_company');
        
        const messagesContainer = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const summaryContent = document.getElementById('summaryContent');
        
        // Load existing summary
        renderSummary();
        
        function saveToSummary(step, data, company) {
            // If company changes, clear previous research data
            if (currentCompany && currentCompany !== company) {
                researchSummary = [];
            }
            
            const summaryItem = {
                company: company,
                step: step,
                data: data,
                timestamp: new Date().toISOString()
            };
            
            // Update or add the summary item for current company only
            const existingIndex = researchSummary.findIndex(item => 
                item.company === company && item.step === step
            );
            
            if (existingIndex >= 0) {
                researchSummary[existingIndex] = summaryItem;
            } else {
                researchSummary.push(summaryItem);
            }
            
            localStorage.setItem('research_summary', JSON.stringify(researchSummary));
            localStorage.setItem('current_company', company);
            currentCompany = company;
            renderSummary();
        }
        
        function renderSummary() {
            // Filter to show only current company's research
            const currentResearch = currentCompany 
                ? researchSummary.filter(item => item.company === currentCompany)
                : researchSummary;
            
            if (currentResearch.length === 0) {
                summaryContent.innerHTML = '<p style="color: #9ca3af; text-align: center; margin-top: 40px;">No research data yet. Start by entering a company name!</p>';
                return;
            }
            
            const stepNames = {
                'overview': 'Overview',
                'financials': 'Financials',
                'products': 'Products & Services',
                'competitors': 'Competitors',
                'pain_points': 'Pain Points',
                'opportunities': 'Opportunities',
                'recommendations': 'Recommendations',
                'custom': 'Custom Research'
            };
            
            let html = '';
            currentResearch.forEach((item, index) => {
                const actualIndex = researchSummary.findIndex(i => i === item);
                const stepName = stepNames[item.step] || item.step;
                const preview = item.data.substring(0, 150) + (item.data.length > 150 ? '...' : '');
                html += `
                    <div class="summary-item" id="summary-item-${actualIndex}">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <h4 style="margin: 0; flex: 1;">${item.company} - ${stepName}</h4>
                            <div style="display: flex; gap: 8px;">
                                <button onclick="editSection(${actualIndex})" style="padding: 4px 8px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Edit this section">
                                    Edit
                                </button>
                                <button onclick="regenerateSection(${actualIndex})" style="padding: 4px 8px; background: #f59e0b; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Regenerate with AI">
                                    Regenerate
                                </button>
                            </div>
                        </div>
                        <p id="summary-text-${actualIndex}" style="margin: 0;">${escapeHtml(preview)}</p>
                        <div id="edit-form-${actualIndex}" style="display: none; margin-top: 10px;">
                            <textarea id="edit-textarea-${actualIndex}" style="width: 100%; min-height: 150px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; font-family: inherit; resize: vertical;">${escapeHtml(item.data)}</textarea>
                            <div style="display: flex; gap: 8px; margin-top: 8px;">
                                <button onclick="saveEdit(${actualIndex})" style="padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    Save
                                </button>
                                <button onclick="cancelEdit(${actualIndex})" style="padding: 6px 12px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            summaryContent.innerHTML = html;
        }

        function addUserMessage(text) {
            disablePreviousButtons();
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message user';
            messageDiv.innerHTML = `<div class="message-content">${escapeHtml(text)}</div>`;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function addAssistantMessage(text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            messageDiv.innerHTML = `<div class="message-content">${escapeHtml(text)}</div>`;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function addResearchData(step, data, company, chart) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            
            const stepTitle = step.charAt(0).toUpperCase() + step.slice(1).replace('_', ' ');
            const chartId = 'chart-' + Date.now();
            
            let html = `
                <div class="research-card">
                    <h3>${company} - ${stepTitle}</h3>
                    <p>${escapeHtml(data)}</p>
            `;
            
            if (chart) {
                html += `<div class="chart-container"><div id="${chartId}" style="width:100%;height:400px;"></div></div>`;
            }
            
            html += `</div>`;
            
            messageDiv.innerHTML = html;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
            
            if (chart) {
                try {
                    const chartData = JSON.parse(chart);
                    Plotly.newPlot(chartId, chartData.data, chartData.layout, {responsive: true});
                } catch (e) {
                    console.error('Chart rendering error:', e);
                }
            }
            
            // Automatically save to summary
            saveToSummary(step, data, company);
        }

        let thinkingInterval = null;
        const thinkingStates = [
            'Thinking...',
            'Analyzing...',
            'Scraping data...',
            'Organizing...',
            'Processing...',
            'Researching...'
        ];
        let currentThinkingIndex = 0;

        function showThinking() {
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'message assistant';
            thinkingDiv.id = 'thinking';
            thinkingDiv.innerHTML = `<div class="thinking">${thinkingStates[0]}</div>`;
            messagesContainer.appendChild(thinkingDiv);
            scrollToBottom();
            
            currentThinkingIndex = 0;
            
            function updateThinkingState() {
                const thinkingElement = document.getElementById('thinking');
                if (thinkingElement) {
                    currentThinkingIndex = (currentThinkingIndex + 1) % thinkingStates.length;
                    thinkingElement.querySelector('.thinking').textContent = thinkingStates[currentThinkingIndex];
                    
                    // Set random duration between 2500ms and 5000ms for next update
                    const randomDelay = Math.floor(Math.random() * (5000 - 2500 + 1)) + 2500;
                    thinkingInterval = setTimeout(updateThinkingState, randomDelay);
                }
            }
            
            // Start with random initial delay (2.5s to 4.5s)
            const initialDelay = Math.floor(Math.random() * (4500 - 2500 + 1)) + 2500;
            thinkingInterval = setTimeout(updateThinkingState, initialDelay);
        }

        function removeThinking() {
            if (thinkingInterval) {
                clearTimeout(thinkingInterval);
                thinkingInterval = null;
            }
            const thinking = document.getElementById('thinking');
            if (thinking) {
                thinking.remove();
            }
        }

        function addPromptWithButtons(promptText) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant prompt-message';
            
            // Check if this is a PDF report prompt
            const isPdfPrompt = promptText.toLowerCase().includes('pdf report');
            
            let buttonsHtml = '';
            if (isPdfPrompt) {
                // Only Yes/No buttons for PDF prompt
                buttonsHtml = `
                    <button class="prompt-button" onclick="sendQuickMessage('yes, generate pdf')" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Yes, Generate PDF</button>
                    <button class="prompt-button" onclick="sendQuickMessage('no thanks')" style="padding: 8px 16px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">No, Thanks</button>
                `;
            } else {
                // Standard research action buttons
                buttonsHtml = `
                    <button class="prompt-button" onclick="sendQuickMessage('yes')" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Yes, Continue</button>
                    <button class="prompt-button" onclick="sendQuickMessage('deep research')" style="padding: 8px 16px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Deep Research</button>
                    <button class="prompt-button" onclick="handleStopButton()" style="padding: 8px 16px; background: #e53e3e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">No, Stop</button>
                `;
            }
            
            const html = `
                <div class="message-content">${escapeHtml(promptText)}</div>
                <div class="button-container" style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    ${buttonsHtml}
                </div>
            `;
            
            messageDiv.innerHTML = html;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        function addConflictMessage(conflictData, step) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            
            const fieldName = conflictData.field_name || 'data';
            const conflictType = conflictData.conflict_type || 'information';
            
            let sourcesHtml = '';
            conflictData.sources.forEach((source, index) => {
                const sourceType = source.is_official ? ' <span style="color: #10b981; font-weight: 600;">📄 (Official Source)</span>' : '';
                sourcesHtml += `
                    <div class="conflict-source" style="margin: 10px 0; padding: 12px; background: #f9fafb; border-left: 3px solid ${source.is_official ? '#10b981' : '#667eea'}; border-radius: 4px;">
                        <div style="font-weight: 600; color: ${source.is_official ? '#10b981' : '#667eea'};">Source ${source.source_id}${sourceType}</div>
                        <div style="margin-top: 8px; font-size: 15px; color: #1f2937; font-weight: 500;">${escapeHtml(source.display_text)}</div>
                        ${source.context ? `<div style="margin-top: 5px; font-size: 13px; color: #6b7280;">${escapeHtml(source.context)}</div>` : ''}
                        <button class="prompt-button conflict-choice" onclick="sendQuickMessage('use source ${source.source_id}')" 
                            style="margin-top: 8px; padding: 6px 12px; background: ${source.is_official ? '#10b981' : '#667eea'}; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; transition: all 0.2s;">
                            Use This Value
                        </button>
                    </div>
                `;
            });
            
            const html = `
                <div class="message-content">
                    <div style="color: #f59e0b; font-weight: 600; margin-bottom: 10px; font-size: 15px;">
                        🔍 Data Conflict Detected
                    </div>
                    <p style="margin-bottom: 15px; color: #374151;">${escapeHtml(conflictData.question || 'Which value should I use?')}</p>
                    ${sourcesHtml}
                    ${conflictData.recommendation ? `
                        <div style="margin-top: 15px; padding: 12px; background: #eff6ff; border-left: 3px solid #3b82f6; border-radius: 4px;">
                            <p style="margin: 0; font-size: 13px; color: #1e40af;">
                                <span style="font-weight: 600;">💡 Recommendation:</span> ${escapeHtml(conflictData.recommendation)}
                            </p>
                        </div>
                    ` : ''}
                </div>
            `;
            
            messageDiv.innerHTML = html;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        function addPdfDownloadButton(filename) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            
            const html = `
                <div class="message-content">
                    <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; text-align: center;">
                        <div style="color: white; font-size: 24px; margin-bottom: 10px;">📄</div>
                        <div style="color: white; font-weight: 600; font-size: 16px; margin-bottom: 15px;">
                            Your PDF Report is Ready!
                        </div>
                        <a href="http://localhost:8001/download/pdf/${filename}" download 
                           style="display: inline-block; padding: 12px 30px; background: white; color: #667eea; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"
                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)'"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'">
                            <span style="margin-right: 8px;">⬇️</span> Download PDF Report
                        </a>
                        <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-top: 12px;">
                            ${filename}
                        </div>
                    </div>
                </div>
            `;
            
            messageDiv.innerHTML = html;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        function disablePreviousButtons() {
            // Disable all buttons in previous prompt messages
            const allButtons = messagesContainer.querySelectorAll('.prompt-button');
            allButtons.forEach(button => {
                button.disabled = true;
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
            });
        }
        
        function sendQuickMessage(message) {
            disablePreviousButtons();
            messageInput.value = message;
            sendMessage();
        }
        
        function handleStopButton() {
            // Get the last research data that was displayed
            const researchCards = document.querySelectorAll('.research-card');
            if (researchCards.length > 0) {
                const lastCard = researchCards[researchCards.length - 1];
                const heading = lastCard.querySelector('h3');
                if (heading) {
                    const headingText = heading.textContent;
                    const match = headingText.match(/(.+?)\s*-\s*(.+)/);
                    if (match) {
                        const company = match[1].trim();
                        const step = match[2].trim().toLowerCase().replace(/\s+/g, '_');
                        const data = lastCard.querySelector('p')?.textContent || '';
                        
                        // Save to summary before stopping
                        saveToSummary(step, data, company);
                    }
                }
            }
            
            // Now send the stop message
            sendQuickMessage('no, stop');
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function editSection(index) {
            // Hide text, show edit form
            document.getElementById(`summary-text-${index}`).style.display = 'none';
            document.getElementById(`edit-form-${index}`).style.display = 'block';
        }
        
        function cancelEdit(index) {
            // Show text, hide edit form
            document.getElementById(`summary-text-${index}`).style.display = 'block';
            document.getElementById(`edit-form-${index}`).style.display = 'none';
        }
        
        function saveEdit(index) {
            const newText = document.getElementById(`edit-textarea-${index}`).value;
            
            // Update the summary data
            researchSummary[index].data = newText;
            localStorage.setItem('research_summary', JSON.stringify(researchSummary));
            
            // Update the preview text
            const preview = newText.substring(0, 150) + (newText.length > 150 ? '...' : '');
            document.getElementById(`summary-text-${index}`).innerHTML = escapeHtml(preview);
            
            // Hide edit form, show text
            cancelEdit(index);
            
            // Show success message in chat
            addAssistantMessage(`✓ Successfully updated ${researchSummary[index].company} - ${researchSummary[index].step} section.`);
        }
        
        async function regenerateSection(index) {
            const item = researchSummary[index];
            const stepNames = {
                'overview': 'Overview',
                'financials': 'Financials',
                'products': 'Products & Services',
                'competitors': 'Competitors',
                'pain_points': 'Pain Points',
                'opportunities': 'Opportunities',
                'recommendations': 'Recommendations',
                'custom': 'Custom Research'
            };
            const stepName = stepNames[item.step] || item.step;
            
            // Ask user to confirm
            const confirmed = confirm(`Regenerate ${item.company} - ${stepName} section with fresh AI research?`);
            if (!confirmed) return;
            
            // Send message to regenerate
            addAssistantMessage(`Regenerating ${stepName} section for ${item.company}...`);
            showThinking();
            
            try {
                const response = await fetch('/api/agent/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        message: `regenerate ${item.step} for ${item.company}`,
                        session_id: sessionId
                    })
                });

                const data = await response.json();
                removeThinking();

                if (data.success && data.messages) {
                    data.messages.forEach((msg, msgIndex) => {
                        setTimeout(() => {
                            if (msg.type === 'text') {
                                addAssistantMessage(msg.content);
                            } else if (msg.type === 'research') {
                                addResearchData(msg.step, msg.data, msg.company, msg.chart);
                            }
                        }, msgIndex * 300);
                    });
                } else {
                    addAssistantMessage('Successfully regenerated the section!');
                }
            } catch (error) {
                removeThinking();
                console.error('Error:', error);
                addAssistantMessage('Sorry, I encountered an error while regenerating. Please try again.');
            }
        }

        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;

            messageInput.value = '';
            sendButton.disabled = true;

            addUserMessage(message);
            showThinking();

            try {
                const response = await fetch('/api/agent/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId
                    })
                });

                const data = await response.json();

                removeThinking();

                if (data.success) {
                    sessionId = data.session_id;
                    localStorage.setItem('session_id', sessionId);

                    // Process messages array if present
                    if (data.messages && Array.isArray(data.messages)) {
                        data.messages.forEach((msg, index) => {
                            setTimeout(() => {
                                if (msg.type === 'text') {
                                    addAssistantMessage(msg.content);
                                } else if (msg.type === 'research') {
                                    addResearchData(msg.step, msg.data, msg.company, msg.chart);
                                } else if (msg.type === 'conflict') {
                                    addConflictMessage(msg.data, msg.step);
                                } else if (msg.type === 'pdf_ready') {
                                    addPdfDownloadButton(msg.filename);
                                } else if (msg.type === 'prompt') {
                                    addPromptWithButtons(msg.content);
                                }
                            }, index * 300); // 300ms delay between each message
                        });
                    } else {
                        // Fallback to old format
                        if (data.response) {
                            addAssistantMessage(data.response);
                        }
                        if (data.data && data.step && data.company) {
                            addResearchData(data.step, data.data, data.company, data.chart);
                        }
                        if (data.prompt_user && data.prompt_message) {
                            setTimeout(() => addPromptWithButtons(data.prompt_message), 300);
                        }
                    }
                } else {
                    addAssistantMessage('Sorry, I encountered an error. Please try again.');
                }
            } catch (error) {
                removeThinking();
                console.error('Error:', error);
                addAssistantMessage('Sorry, something went wrong. Please try again.');
            } finally {
                sendButton.disabled = false;
                messageInput.focus();
            }
        }

        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Show welcome message on page load
        function showWelcomeMessage() {
            // Always show welcome message on fresh page load
            const existingMessages = messagesContainer.querySelectorAll('.message');
            
            if (existingMessages.length === 0) {
                setTimeout(() => {
                    const welcomeMessage = `Hello! I'm your Company Research Assistant.

I can help you research any company with comprehensive insights including company overview and background, financial analysis and performance, products and services, competitive landscape, and market opportunities.

Try asking me about these companies:`;
                    
                    addAssistantMessage(welcomeMessage);
                    
                    setTimeout(() => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message assistant';
                        messageDiv.innerHTML = `
                            <div class="message-content">
                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                                    <button class="prompt-button" onclick="sendQuickMessage('research Google')" style="padding: 12px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; text-align: left;">
                                        Research Google
                                    </button>
                                    <button class="prompt-button" onclick="sendQuickMessage('research Tesla')" style="padding: 12px 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; text-align: left;">
                                        Research Tesla
                                    </button>
                                    <button class="prompt-button" onclick="sendQuickMessage('research Apple')" style="padding: 12px 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; text-align: left;">
                                        Research Apple
                                    </button>
                                </div>
                            </div>
                        `;
                        messagesContainer.appendChild(messageDiv);
                        scrollToBottom();
                    }, 500);
                }, 300);
            }
        }

        showWelcomeMessage();
        messageInput.focus();
    </script>
</body>
</html>

