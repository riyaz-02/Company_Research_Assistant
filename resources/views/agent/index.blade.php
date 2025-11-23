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
        let sessionId = localStorage.getItem('session_id') || null;
        let researchSummary = JSON.parse(localStorage.getItem('research_summary') || '[]');
        let currentCompany = localStorage.getItem('current_company') || null;
        
        const messagesContainer = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const summaryContent = document.getElementById('summaryContent');

        if (!sessionId) {
            addAssistantMessage("Hello! I'm your AI Company Research Assistant. I can help you research any company with data visualizations. Just tell me which company you'd like to know about!");
        }
        
        // Load existing summary
        renderSummary();
        
        function saveToSummary(step, data, company) {
            const summaryItem = {
                company: company,
                step: step,
                data: data,
                timestamp: new Date().toISOString()
            };
            
            // Update or add the summary item
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
            if (researchSummary.length === 0) {
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
            researchSummary.forEach(item => {
                const stepName = stepNames[item.step] || item.step;
                const preview = item.data.substring(0, 150) + (item.data.length > 150 ? '...' : '');
                html += `
                    <div class="summary-item">
                        <h4>${item.company} - ${stepName}</h4>
                        <p>${escapeHtml(preview)}</p>
                    </div>
                `;
            });
            
            summaryContent.innerHTML = html;
        }

        function addUserMessage(text) {
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

        function showThinking() {
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'message assistant';
            thinkingDiv.id = 'thinking';
            thinkingDiv.innerHTML = `<div class="thinking">Thinking</div>`;
            messagesContainer.appendChild(thinkingDiv);
            scrollToBottom();
        }

        function removeThinking() {
            const thinking = document.getElementById('thinking');
            if (thinking) {
                thinking.remove();
            }
        }

        function addPromptWithButtons(promptText) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            
            const html = `
                <div class="message-content">${escapeHtml(promptText)}</div>
                <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="sendQuickMessage('yes')" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Yes, Continue</button>
                    <button onclick="sendQuickMessage('deep research')" style="padding: 8px 16px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Deep Research</button>
                    <button onclick="sendQuickMessage('no, stop')" style="padding: 8px 16px; background: #e53e3e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">No, Stop</button>
                </div>
            `;
            
            messageDiv.innerHTML = html;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        function sendQuickMessage(message) {
            messageInput.value = message;
            sendMessage();
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        messageInput.focus();
    </script>
</body>
</html>

