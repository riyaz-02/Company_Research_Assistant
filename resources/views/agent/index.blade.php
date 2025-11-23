<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Company Research Assistant - AI Agent</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .chat-message {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .progress-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3b82f6;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Chat Panel (Left) -->
        <div class="flex-1 flex flex-col border-r border-gray-200">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Company Research Assistant</h1>
                <p class="text-sm text-gray-600 mt-1">AI-powered company research and account planning</p>
            </div>

            <!-- Chat Messages -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4">
                <!-- No initial message - agent will respond based on user input -->
            </div>

            <!-- Input Area -->
            <div class="bg-white border-t border-gray-200 p-4">
                <form id="chatForm" class="flex gap-2">
                    <input 
                        type="text" 
                        id="messageInput" 
                        placeholder="Type your message..." 
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        autocomplete="off"
                    >
                    <button 
                        type="submit" 
                        id="sendButton"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Send
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Plan Panel (Right) -->
        <div class="w-1/2 bg-white overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 z-10">
                <h2 class="text-xl font-bold text-gray-900">Account Plan</h2>
                <p class="text-sm text-gray-600 mt-1" id="companyName">No company selected</p>
            </div>

            <div class="p-6 space-y-6" id="planContent">
                <div class="text-center text-gray-500 py-12">
                    <p>Start a conversation to generate an account plan</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let sessionId = localStorage.getItem('sessionId') || generateSessionId();
        localStorage.setItem('sessionId', sessionId);

        function generateSessionId() {
            return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const chatMessages = document.getElementById('chatMessages');
        const planContent = document.getElementById('planContent');
        const companyNameEl = document.getElementById('companyName');

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            // Add user message to chat
            addMessage('user', message);
            messageInput.value = '';
            sendButton.disabled = true;

            // Show typing indicator
            const typingId = showTypingIndicator();

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
                removeTypingIndicator(typingId);

                if (data.success) {
                    // Display responses based on action type
                    if (data.responses && data.responses.length > 0) {
                        data.responses.forEach(response => {
                            if (response.type === 'progress') {
                                addProgressMessage(response.content);
                            } else if (response.type === 'message') {
                                addMessage('assistant', response.content);
                            } else if (response.type === 'plan_update') {
                                // Plan update handled by updatePlan call below
                                addProgressMessage(`Updated ${response.section} section`);
                            }
                        });
                    } else {
                        // No responses generated - show helpful message
                        addMessage('assistant', 'I received your message but couldn\'t generate a response. This might be due to API configuration. Please check the server logs.');
                    }

                    // Update plan
                    if (data.plan) {
                        updatePlan(data.plan);
                    }
                } else {
                    const errorMsg = data.message || data.error || 'Sorry, I encountered an error. Please try again.';
                    addMessage('assistant', errorMsg);
                    console.error('API Error:', data);
                }
            } catch (error) {
                removeTypingIndicator(typingId);
                addMessage('assistant', 'Sorry, I encountered an error. Please try again.');
                console.error('Error:', error);
            } finally {
                sendButton.disabled = false;
                messageInput.focus();
            }
        });

        function addMessage(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            if (role === 'user') {
                messageDiv.innerHTML = `
                    <div class="flex justify-end">
                        <div class="bg-blue-600 text-white rounded-lg p-4 max-w-md">
                            <p>${escapeHtml(content)}</p>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="flex justify-start">
                        <div class="bg-gray-100 border border-gray-200 rounded-lg p-4 max-w-md">
                            <p class="text-gray-800">${escapeHtml(content)}</p>
                        </div>
                    </div>
                `;
            }

            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addProgressMessage(content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-md">
                        <div class="flex items-center gap-2">
                            <span class="progress-indicator"></span>
                            <p class="text-gray-800 text-sm">${escapeHtml(content)}</p>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function showTypingIndicator() {
            const typingId = 'typing_' + Date.now();
            const messageDiv = document.createElement('div');
            messageDiv.id = typingId;
            messageDiv.className = 'chat-message';
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-gray-100 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center gap-2">
                            <span class="progress-indicator"></span>
                            <p class="text-gray-600 text-sm">Thinking...</p>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return typingId;
        }

        function removeTypingIndicator(id) {
            const element = document.getElementById(id);
            if (element) {
                element.remove();
            }
        }

        function updatePlan(plan) {
            if (plan.company_name) {
                companyNameEl.textContent = plan.company_name;
            }

            const sections = [
                { key: 'overview', title: 'Overview' },
                { key: 'products', title: 'Products & Services' },
                { key: 'competitors', title: 'Competitors' },
                { key: 'opportunities', title: 'Opportunities' },
                { key: 'recommendations', title: 'Recommendations' },
                { key: 'market_position', title: 'Market Position' },
                { key: 'financial_summary', title: 'Financial Summary' },
                { key: 'key_contacts', title: 'Key Contacts' },
            ];

            let html = '';
            sections.forEach(section => {
                const value = plan[section.key];
                if (value && (typeof value === 'string' ? value.trim() : (Array.isArray(value) ? value.length > 0 : true))) {
                    html += `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-semibold text-gray-900">${section.title}</h3>
                                <button 
                                    onclick="regenerateSection('${section.key}')" 
                                    class="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Regenerate
                                </button>
                            </div>
                            <div class="text-gray-700">
                                ${formatSectionContent(value)}
                            </div>
                        </div>
                    `;
                }
            });

            if (!html) {
                html = '<div class="text-center text-gray-500 py-12"><p>Plan sections will appear here as they are generated</p></div>';
            }

            planContent.innerHTML = html;
        }

        function formatSectionContent(value) {
            if (Array.isArray(value)) {
                if (value.length === 0) return '<p class="text-gray-500 italic">No data available</p>';
                return '<ul class="list-disc list-inside space-y-1">' + 
                    value.map(item => `<li>${escapeHtml(typeof item === 'string' ? item : JSON.stringify(item))}</li>`).join('') + 
                    '</ul>';
            }
            return '<p>' + escapeHtml(value) + '</p>';
        }

        async function regenerateSection(section) {
            if (!confirm(`Regenerate ${section} section?`)) return;

            try {
                const response = await fetch('/api/agent/plan/regenerate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        section: section
                    })
                });

                const data = await response.json();
                if (data.success && data.plan) {
                    updatePlan(data.plan);
                    addMessage('assistant', `Regenerated ${section} section.`);
                }
            } catch (error) {
                console.error('Error regenerating section:', error);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load existing plan on page load
        window.addEventListener('load', async () => {
            try {
                const response = await fetch(`/api/agent/plan?session_id=${sessionId}`);
                const data = await response.json();
                if (data.success && data.plan) {
                    updatePlan(data.plan);
                }
            } catch (error) {
                console.error('Error loading plan:', error);
            }
        });
    </script>
</body>
</html>

