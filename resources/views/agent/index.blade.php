<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Company Research Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.06) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(147, 51, 234, 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        /* Glassmorphism Classes */
        .glass {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .glass-dark {
            background: rgba(10, 10, 15, 0.7);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .glass-lighter {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.4), rgba(147, 51, 234, 0.4));
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.7), rgba(147, 51, 234, 0.7));
            background-clip: padding-box;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.7; }
            30% { transform: translateY(-10px); opacity: 1; }
        }
        
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        
        .chat-message {
            animation: fadeIn 0.3s ease-in;
        }
        
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 12px 20px;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.8);
            animation: typing 1.4s ease-in-out infinite;
        }
        
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        .activity-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 500;
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .activity-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: white;
            animation: bounce 1.4s ease-in-out infinite both;
        }
        
        .activity-dot:nth-child(1) { animation-delay: -0.32s; }
        .activity-dot:nth-child(2) { animation-delay: -0.16s; }
    </style>
</head>
<body>
    <!-- Activity Indicator -->
    <div id="activityIndicator" class="activity-indicator">
        <span>AI Working</span>
        <div class="activity-dot"></div>
        <div class="activity-dot"></div>
        <div class="activity-dot"></div>
    </div>

    <div class="flex h-screen p-4 gap-4">
        <!-- Chat Panel (Left) -->
        <div class="flex-1 glass rounded-2xl flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="glass-dark text-white p-6 flex-shrink-0 rounded-t-2xl">
                <h1 class="text-2xl font-bold text-white mb-2">Company Research Assistant</h1>
                <p class="text-gray-300 text-sm">AI-powered company research and account planning</p>
            </div>

            <!-- Chat Messages -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4">
                <!-- Welcome Message -->
                <div class="chat-message">
                    <div class="flex justify-start">
                        <div class="glass border border-white/20 rounded-xl p-6 max-w-3xl shadow-lg">
                            <h3 class="font-bold text-white text-lg mb-3">👋 Welcome! I'm your AI Research Assistant</h3>
                            <p class="text-gray-200 text-sm mb-4">I'll guide you through a comprehensive 7-step research process to create detailed account plans:</p>
                            
                            <div class="space-y-1.5 text-sm text-gray-200 mb-5">
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">1.</span> Company Basics
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">2.</span> Financial Analysis
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">3.</span> Products & Technology
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">4.</span> Competitive Landscape
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">5.</span> Pain Points & Opportunities
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">6.</span> Strategic Recommendations
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-blue-400">7.</span> Final Account Plan
                                </div>
                            </div>
                            
                            <p class="text-gray-300 text-sm mb-3 font-semibold">Try one of these examples:</p>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="sendSampleQuestion('Research Microsoft comprehensively')" class="glass border border-blue-400/30 hover:bg-blue-600/20 text-white px-4 py-2 rounded-xl text-xs font-medium transition-all duration-300 hover:scale-105">
                                    🔍 Research Microsoft
                                </button>
                                <button onclick="sendSampleQuestion('Create account plan for Salesforce')" class="glass border border-purple-400/30 hover:bg-purple-600/20 text-white px-4 py-2 rounded-xl text-xs font-medium transition-all duration-300 hover:scale-105">
                                    📊 Salesforce Account Plan
                                </button>
                                <button onclick="sendSampleQuestion('Analyze Amazon competitors and market position')" class="glass border border-green-400/30 hover:bg-green-600/20 text-white px-4 py-2 rounded-xl text-xs font-medium transition-all duration-300 hover:scale-105">
                                    🎯 Amazon Analysis
                                </button>
                                <button onclick="sendSampleQuestion('Generate detailed research for Google')" class="glass border border-orange-400/30 hover:bg-orange-600/20 text-white px-4 py-2 rounded-xl text-xs font-medium transition-all duration-300 hover:scale-105">
                                    📈 Google Research
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="glass-dark border-t border-white/10 p-5 rounded-b-2xl">
                <form id="chatForm" class="flex gap-3">
                    <input 
                        type="text" 
                        id="messageInput" 
                        placeholder="Ask me to research a company or generate an account plan..." 
                        class="flex-1 glass-lighter border border-white/10 rounded-2xl px-5 py-3.5 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-400/30 backdrop-blur-xl transition-all duration-300"
                        autocomplete="off"
                    >
                    <button 
                        type="submit" 
                        id="sendButton"
                        class="bg-gradient-to-r from-blue-600/50 to-purple-600/50 hover:from-blue-600/70 hover:to-purple-600/70 text-white px-8 py-3.5 rounded-2xl font-semibold border border-blue-400/20 hover:border-blue-300/40 focus:outline-none focus:ring-2 focus:ring-blue-400/50 transition-all duration-300 shadow-lg hover:shadow-blue-500/30 backdrop-blur-xl hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                    >
                        <span class="flex items-center gap-2">
                            <span>Send</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Plan Panel (Right) -->
        <div class="w-1/2 glass rounded-2xl overflow-hidden shadow-2xl">
            <!-- Header with Buttons -->
            <div class="glass-dark border-b border-white/10 px-6 py-5 w-full">
                <div class="flex justify-between items-center w-full">
                    <div class="flex-1 min-w-0 pr-4">
                        <h2 class="text-2xl font-bold text-white mb-1">Account Plan</h2>
                        <p class="text-sm text-gray-300 truncate" id="companyName">No company selected</p>
                    </div>
                    <!-- Action Buttons -->
                    <div class="flex-shrink-0">
                        <div class="flex gap-3" id="planActionButtons">
                            <button onclick="clearAccountPlan()" 
                                    id="clearPlanBtn"
                                    class="px-6 py-2.5 glass-lighter hover:bg-red-600/30 text-white text-sm font-semibold rounded-2xl border border-red-500/30 hover:border-red-400/50 focus:outline-none focus:ring-2 focus:ring-red-400/50 transition-all duration-300 shadow-lg hover:shadow-red-500/20 hover:scale-105 backdrop-blur-xl" 
                                    title="Clear account plan for new research">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Clear
                                </span>
                            </button>
                            <button onclick="generateFinalPlan()" 
                                    id="generatePlanBtn"
                                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600/40 to-purple-600/40 hover:from-blue-600/60 hover:to-purple-600/60 text-white text-sm font-semibold rounded-2xl border border-blue-400/30 hover:border-blue-300/50 focus:outline-none focus:ring-2 focus:ring-blue-400/50 transition-all duration-300 shadow-lg hover:shadow-blue-500/30 backdrop-blur-xl hover:scale-105" 
                                    title="Generate comprehensive final plan">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Generate
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6 h-full overflow-y-auto" id="planContent">
                <div class="text-center text-gray-300 py-16">
                    <div class="glass border border-white/10 rounded-2xl p-8 mx-auto max-w-md">
                        <div class="w-16 h-16 mx-auto mb-4 glass rounded-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">No Account Plan</h3>
                        <p class="text-gray-300 text-sm">Start a conversation to generate a comprehensive account plan</p>
                    </div>
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

            // Disable all buttons to prevent clicking old buttons
            disableAllButtons();

            // Add user message to chat
            addMessage('user', message);
            messageInput.value = '';
            sendButton.disabled = true;

            // Show typing indicator and activity indicator
            const typingId = showTypingIndicator();
            showActivityIndicator();

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
                console.log('Response data:', data);
                removeTypingIndicator(typingId);
                hideActivityIndicator();

                if (data.success) {
                    // Display responses based on action type
                    if (data.responses && data.responses.length > 0) {
                        console.log('Processing responses:', data.responses);
                        
                        // Check if this is the new step-by-step format (look for ask_user or update_plan)
                        const hasStepFormat = data.responses.some(r => 
                            ['ask_user', 'update_plan', 'finish'].includes(r.type)
                        );
                        
                        console.log('Has step format:', hasStepFormat);
                        
                        if (hasStepFormat) {
                            // Use new response handler
                            console.log('Using new response handler');
                            handleResponses(data.responses);
                        } else {
                            // Use legacy response handler
                            console.log('Using legacy response handler');
                            data.responses.forEach((response, index) => {
                                console.log(`Response ${index}:`, response);
                                if (response.type === 'search_start') {
                                    addSearchProgress(response);
                                } else if (response.type === 'search_complete') {
                                    updateSearchProgress(response);
                                } else if (response.type === 'parameter_updated') {
                                    addParameterUpdate(response);
                                } else if (response.type === 'detailed_progress') {
                                    addDetailedProgress(response);
                                } else if (response.type === 'progress') {
                                    addProgressMessage(response.content, response.progress);
                                } else if (response.type === 'message') {
                                    addMessage('assistant', response.content);
                                } else if (response.type === 'plan_update') {
                                    // Legacy support
                                    const param = response.parameter || response.section;
                                    addProgressMessage(`✅ Updated: ${param}`);
                                } else if (response.type === 'final_plan') {
                                    addMessage('assistant', response.content);
                                    if (response.plan) {
                                        displayFinalAccountPlan(response.plan);
                                    }
                                }
                            });
                        }
                    } else {
                        // No responses generated - show helpful message
                        addMessage('assistant', 'I received your message but couldn\'t generate a response. This might be due to API configuration. Please check the server logs.');
                    }

                    // Update plan
                    if (data.plan) {
                        console.log('Updating plan with:', data.plan);
                        updatePlan(data.plan);
                    } else {
                        console.log('No plan data received');
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
                        <div class="bg-gradient-to-r from-blue-600/40 to-purple-600/40 backdrop-blur-xl border border-blue-400/30 text-white rounded-2xl p-5 max-w-md shadow-xl">
                            <p class="text-gray-100">${escapeHtml(content)}</p>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="flex justify-start">
                        <div class="glass-lighter border border-white/10 rounded-2xl p-5 max-w-md shadow-xl backdrop-blur-xl">
                            <p class="text-gray-100">${escapeHtml(content)}</p>
                        </div>
                    </div>
                `;
            }

            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addProgressMessage(content, progress = null) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            let progressBar = '';
            if (progress) {
                const percentage = progress.percentage || 0;
                progressBar = `
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Research Progress</span>
                            <span>${progress.completed}/${progress.total} parameters (${percentage}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${percentage}%"></div>
                        </div>
                        ${progress.conflicting > 0 ? `                            <div class="text-xs text-red-600 mt-1">Warning: ${progress.conflicting} conflicting data points</div>` : ''}
                    </div>
                `;
            }
            
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-2xl">
                        <div class="flex items-center gap-2">
                            <span class="progress-indicator"></span>
                            <p class="text-gray-800 text-sm">${escapeHtml(content)}</p>
                        </div>
                        ${progressBar}
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addSearchProgress(response) {
            const messageDiv = document.createElement('div');
            messageDiv.id = `search_${response.iteration}_${Date.now()}`;
            messageDiv.className = 'chat-message search-progress';
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="glass-lighter border border-blue-400/20 rounded-2xl p-5 max-w-2xl shadow-xl backdrop-blur-xl">
                        <div class="flex items-center gap-3">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-400"></div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-100">${escapeHtml(response.content)}</div>
                                <div class="text-xs text-gray-400 mt-1">Started at ${response.timestamp}</div>
                            </div>
                            <div class="text-xs text-gray-300 glass-lighter border border-white/10 px-3 py-1.5 rounded-xl backdrop-blur-xl">
                                Searching...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Store reference for updating
            window.currentSearchElement = messageDiv;
        }

        function updateSearchProgress(response) {
            if (window.currentSearchElement) {
                const statusClass = response.results_count > 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                const statusText = response.results_count > 0 ? `${response.results_count} results found` : 'No results found';
                
                window.currentSearchElement.innerHTML = `
                    <div class="flex justify-start">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 max-w-2xl">
                            <div class="flex items-center gap-2">
                                <div class="text-green-600 font-semibold">Complete</div>
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-green-800">${escapeHtml(response.query)}</div>
                                    <div class="text-xs text-green-600 mt-1">${response.content}</div>
                                </div>
                                <div class="text-xs ${statusClass} px-2 py-1 rounded">
                                    ${statusText}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                window.currentSearchElement = null;
            }
        }

        function addParameterUpdate(response) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            const progressBar = response.progress ? `
                <div class="mt-2">
                    <div class="flex justify-between text-xs text-green-600 mb-1">
                        <span>Overall Progress</span>
                        <span>${response.progress.completed}/${response.progress.total} (${response.progress.percentage}%)</span>
                    </div>
                    <div class="w-full bg-green-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: ${response.progress.percentage}%"></div>
                    </div>
                </div>
            ` : '';
            
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 max-w-2xl">
                        <div class="flex items-center gap-2">
                            <div class="text-green-600">Updated</div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-green-800">Updated: ${response.parameter.replace(/_/g, ' ')}</div>
                                <div class="text-xs text-green-600 mt-1">
                                    ${response.timestamp} • ${response.evidence_count} sources
                                </div>
                            </div>
                        </div>
                        ${progressBar}
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addDetailedProgress(response) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            const nextActions = response.next_actions ? response.next_actions.map(action => 
                `<span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded">${action}</span>`
            ).join(' ') : '';
            
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-3xl">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="text-blue-600">Progress</div>
                            <div class="font-medium text-blue-800">${response.current_phase}</div>
                            <div class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded">
                                ${response.elapsed_time}s elapsed
                            </div>
                        </div>
                        
                        <div class="text-sm text-blue-700 mb-2">${escapeHtml(response.content)}</div>
                        
                        <div class="flex justify-between text-xs text-blue-600 mb-1">
                            <span>Progress</span>
                            <span>${response.progress.completed}/${response.progress.total} (${response.progress.percentage}%)</span>
                        </div>
                        <div class="w-full bg-blue-200 rounded-full h-2 mb-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${response.progress.percentage}%"></div>
                        </div>
                        
                        ${nextActions ? `
                            <div class="text-xs text-blue-600 mb-1">Next actions:</div>
                            <div class="flex flex-wrap gap-1">${nextActions}</div>
                        ` : ''}
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
            console.log('updatePlan called with:', plan);
            
            if (plan.company_name) {
                companyNameEl.textContent = plan.company_name;
            }

            // Handle comprehensive plan structure
            if (plan.account_plan) {
                updateComprehensivePlan(plan);
                return;
            }

            // Step workflow sections (primary)
            const stepSections = [
                { key: 'company_overview', title: '1. Company Overview' },
                { key: 'financial_overview', title: '2. Financial Overview' },
                { key: 'products_services', title: '3. Products & Services' },
                { key: 'competitive_landscape', title: '4. Competitive Landscape' },
                { key: 'pain_points', title: '5. Pain Points & Challenges' },
                { key: 'recommendations', title: '6. Strategic Recommendations' },
                { key: 'executive_summary', title: '7. Executive Summary' },
            ];
            
            // Legacy plan structure for compatibility
            const legacySections = [
                { key: 'overview', title: 'Overview' },
                { key: 'products', title: 'Products' },
                { key: 'competitors', title: 'Competitors' },
                { key: 'opportunities', title: 'Opportunities' },
                { key: 'market_position', title: 'Market Position' },
                { key: 'financial_summary', title: 'Financial Summary' },
                { key: 'key_contacts', title: 'Key Contacts' },
            ];

            let html = '';
            let hasContent = false;
            
            // Render step sections first
            stepSections.forEach(section => {
                const value = plan[section.key];
                if (value && (typeof value === 'string' ? value.trim() : (Array.isArray(value) ? value.length > 0 : true))) {
                    hasContent = true;
                    html += `
                        <div class=\"glass-lighter border border-white/10 rounded-2xl p-6 mb-4 backdrop-blur-xl shadow-xl hover:border-white/20 transition-all duration-300\">
                            <div class=\"flex justify-between items-center mb-3\">
                                <h3 class=\"font-semibold text-white text-lg\">${section.title}</h3>
                                <button 
                                    onclick=\"regenerateSection('${section.key}')\" 
                                    class=\"text-sm text-blue-400 hover:text-blue-300 glass-lighter px-3 py-1.5 rounded-xl border border-blue-400/30 hover:border-blue-300/50 transition-all duration-300\"
                                >
                                    Regenerate
                                </button>
                            </div>
                            <div class=\"text-gray-200 leading-relaxed whitespace-pre-wrap\">
                                ${formatSectionContent(value)}
                            </div>
                        </div>
                    `;
                }
            });
            
            // Then legacy sections if no step content
            if (!hasContent) {
                legacySections.forEach(section => {
                    const value = plan[section.key];
                    if (value && (typeof value === 'string' ? value.trim() : (Array.isArray(value) ? value.length > 0 : true))) {
                        hasContent = true;
                        html += `
                            <div class=\"glass-lighter border border-white/10 rounded-2xl p-6 mb-4 backdrop-blur-xl shadow-xl hover:border-white/20 transition-all duration-300\">
                                <div class=\"flex justify-between items-center mb-3\">
                                    <h3 class=\"font-semibold text-white text-lg\">${section.title}</h3>
                                    <button 
                                        onclick=\"regenerateSection('${section.key}')\" 
                                        class=\"text-sm text-blue-400 hover:text-blue-300 glass-lighter px-3 py-1.5 rounded-xl border border-blue-400/30 hover:border-blue-300/50 transition-all duration-300\"
                                    >
                                        Regenerate
                                    </button>
                                </div>
                                <div class=\"text-gray-200 leading-relaxed\">
                                    ${formatSectionContent(value)}
                                </div>
                            </div>
                        `;
                    }
                });
            }

            if (!hasContent) {
                html = '<div class="text-center text-gray-500 py-12"><p>Plan sections will appear here as they are generated</p></div>';
            }

            planContent.innerHTML = html;
        }

        function updateComprehensivePlan(plan) {
            const sections = [
                { key: 'company_basics', title: 'Company Basics' },
                { key: 'financial_info', title: 'Financial Information' },
                { key: 'product_technology', title: 'Product & Technology' },
                { key: 'leadership_people', title: 'Leadership & People' },
                { key: 'market_analysis', title: 'Market Analysis' },
                { key: 'gtm_strategy', title: 'Go-to-Market Strategy' },
                { key: 'recent_intelligence', title: 'Recent Intelligence' },
                { key: 'pain_points', title: 'Pain Points Analysis' },
                { key: 'strategic_assessment', title: 'Strategic Assessment' },
            ];

            let html = '<div class="space-y-4">';
            
            sections.forEach(section => {
                const sectionData = plan[section.key];
                if (sectionData && Object.keys(sectionData).length > 0) {
                    html += `
                        <div class=\"glass-lighter border border-white/10 rounded-2xl p-6 backdrop-blur-xl shadow-xl hover:border-white/20 transition-all duration-300\">
                            <div class=\"flex justify-between items-center mb-4\">
                                <h3 class=\"font-bold text-white text-lg\">
                                    ${section.title}
                                </h3>
                                <button 
                                    onclick=\"regenerateSection('${section.key}')\" 
                                    class=\"text-sm text-blue-400 hover:text-blue-300 glass-lighter px-3 py-1.5 rounded-xl border border-blue-400/30 hover:border-blue-300/50 transition-all duration-300\"
                                >
                                    Regenerate
                                </button>
                            </div>
                            <div class=\"space-y-3 w-full\">
                                ${formatComprehensiveSectionContent(sectionData)}
                            </div>
                        </div>
                    `;
                }
            });

            // Show research metadata
            if (plan.research_metadata && plan.research_metadata.research_status) {
                const progress = calculateProgress(plan.research_metadata.research_status);
                html += `
                    <div class=\"glass-lighter border border-blue-400/30 rounded-2xl p-6 backdrop-blur-xl shadow-xl\">
                        <h3 class=\"font-bold text-white mb-3 text-lg\">Research Progress</h3>
                        <div class=\"flex justify-between text-sm text-gray-300 mb-3\">
                            <span>Completion Status</span>
                            <span class=\"font-semibold text-blue-400\">${progress.completed}/${progress.total} parameters (${progress.percentage}%)</span>
                        </div>
                        <div class=\"w-full glass-dark rounded-full h-3 overflow-hidden\">
                            <div class=\"bg-gradient-to-r from-blue-600 to-purple-600 h-3 rounded-full transition-all duration-500 shadow-lg\" style=\"width: ${progress.percentage}%\"></div>
                        </div>
                    </div>
                `;
            }

            html += '</div>';

            if (html === '<div class="space-y-4"></div>') {
                html = '<div class="text-center text-gray-500 py-12"><p>Research in progress... Data will appear here as it becomes available.</p></div>';
            }

            planContent.innerHTML = html;
        }

        function displayFinalAccountPlan(finalPlan) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-6 max-w-4xl w-full">
                        <h3 class="font-bold text-green-900 mb-4">${finalPlan.title || 'Account Plan'}</h3>
                        <div class="text-sm text-green-700 mb-4">Generated: ${new Date(finalPlan.generated_at).toLocaleString()}</div>
                        
                        <div class="space-y-4">
                            ${Object.entries(finalPlan.sections || {}).map(([section, content]) => `
                                <div class="border border-green-200 rounded-lg p-4 bg-white">
                                    <h4 class="font-semibold text-gray-900 mb-2">${section}</h4>
                                    <div class="text-gray-700 text-sm">
                                        ${formatFinalPlanSection(content)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="mt-4 flex gap-2">
                            <button onclick="downloadAccountPlan(${JSON.stringify(finalPlan).replace(/"/g, '&quot;')})" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                                Download Report
                            </button>
                            <button onclick="shareAccountPlan(${JSON.stringify(finalPlan).replace(/"/g, '&quot;')})" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                                Share Plan
                            </button>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
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

        function formatComprehensiveSectionContent(sectionData) {
            return Object.entries(sectionData).map(([key, value]) => {
                if (!value || (Array.isArray(value) && value.length === 0) || (typeof value === 'string' && !value.trim())) {
                    return '';
                }
                
                const displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                return `
                    <div class=\"glass-dark rounded-xl p-4 backdrop-blur-xl border border-white/5 w-full\">
                        <div class=\"font-semibold text-blue-400 text-sm mb-2\">${displayKey}</div>
                        <div class=\"text-gray-200 text-sm leading-relaxed break-words\">
                            ${formatSectionContent(value)}
                        </div>
                    </div>
                `;
            }).filter(html => html).join('');
        }

        function formatFinalPlanSection(content) {
            if (Array.isArray(content)) {
                return content.map(item => `<div class="mb-2">${escapeHtml(JSON.stringify(item))}</div>`).join('');
            } else if (typeof content === 'object') {
                return Object.entries(content).map(([key, value]) => {
                    const displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    return `<div class="mb-2"><strong>${displayKey}:</strong> ${formatSectionContent(value)}</div>`;
                }).join('');
            }
            return escapeHtml(content);
        }

        function calculateProgress(researchStatus) {
            const total = Object.keys(researchStatus).length;
            const completed = Object.values(researchStatus).filter(s => s.status === 'completed').length;
            return {
                total,
                completed,
                percentage: total > 0 ? Math.round((completed / total) * 100) : 0
            };
        }

        function downloadAccountPlan(planData) {
            // Create a downloadable document (simplified version)
            const content = `
# ${planData.title}
Generated: ${new Date(planData.generated_at).toLocaleString()}

${Object.entries(planData.sections || {}).map(([section, content]) => `
## ${section}
${typeof content === 'object' ? JSON.stringify(content, null, 2) : content}
`).join('\n')}
            `.trim();
            
            const blob = new Blob([content], { type: 'text/markdown' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `account_plan_${planData.title?.replace(/[^a-zA-Z0-9]/g, '_') || 'plan'}.md`;
            a.click();
            URL.revokeObjectURL(url);
        }

        function shareAccountPlan(planData) {
            if (navigator.share) {
                navigator.share({
                    title: planData.title,
                    text: 'Account Plan Generated',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Plan URL copied to clipboard!');
                });
            }
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

        function sendExample(message) {
            document.getElementById('messageInput').value = message;
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }

        // New function for sample questions
        function sendSampleQuestion(message) {
            sendExample(message);
        }

        // Function to create and handle button responses
        function createButtonGroup(buttons, context = {}) {
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'flex flex-wrap gap-2 mt-3';

            buttons.forEach(btn => {
                const button = document.createElement('button');

                // Handle both string and object button formats
                const buttonText = typeof btn === 'string' ? btn : btn.text;
                const buttonValue = typeof btn === 'string' ? btn : (btn.value || btn.text);

                button.textContent = buttonText;
                button.className = 'glass-lighter border border-white/20 hover:bg-white/10 text-white px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 hover:scale-105';
                button.onclick = () => handleButtonClick(buttonValue, context);
                buttonContainer.appendChild(button);
            });

            return buttonContainer;
        }

        // Disable all buttons in the chat after user interaction
        function disableAllButtons() {
            const allButtons = document.querySelectorAll('#chatMessages button');
            allButtons.forEach(button => {
                button.disabled = true;
                button.classList.add('opacity-50', 'cursor-not-allowed');
                button.classList.remove('hover:bg-white/10', 'hover:scale-105');
            });
        }        // Handle button clicks
        async function handleButtonClick(buttonText, context) {
            console.log('Button clicked:', buttonText, context);

            // Disable all buttons to prevent multiple clicks on old buttons
            disableAllButtons();

            // Add user message showing button selection
            addMessage('user', buttonText);            // Send to backend
            const typingId = showTypingIndicator();
            showActivityIndicator();
            
            try {
                const response = await fetch('/api/agent/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    body: JSON.stringify({
                        message: buttonText,
                        session_id: sessionId,
                        context: context
                    })
                });

                const data = await response.json();
                console.log('Button response:', data);
                removeTypingIndicator(typingId);
                hideActivityIndicator();

                if (data.success && data.responses) {
                    handleResponses(data.responses);
                }
            } catch (error) {
                removeTypingIndicator(typingId);
                hideActivityIndicator();
                console.error('Button error:', error);
                addMessage('assistant', 'Sorry, there was an error processing your selection.');
            }
        }

        // Handle different response types
        function handleResponses(responses) {
            console.log('handleResponses called with:', responses);
            responses.forEach(response => {
                console.log('Processing response:', response);
                if (response.type === 'progress') {
                    showProgressMessage(response.message || response.content);
                } else if (response.type === 'ask_user') {
                    console.log('Adding message with buttons:', response.buttons);
                    addMessageWithButtons(response.content, response.buttons || [], response.context || {});
                } else if (response.type === 'update_plan') {
                    updatePlanSection(response.section, response.content);
                    addMessage('assistant', `✅ Updated: ${response.section}`);
                } else if (response.type === 'message') {
                    addMessage('assistant', response.content);
                } else if (response.type === 'finish') {
                    addMessage('assistant', response.content);
                }
            });
        }

        // Add message with buttons
        function addMessageWithButtons(content, buttons, context = {}) {
            console.log('addMessageWithButtons called:', { content, buttons, context });
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            const messageContent = document.createElement('div');
            messageContent.className = 'flex justify-start';
            
            const bubble = document.createElement('div');
            bubble.className = 'glass-lighter border border-white/10 rounded-2xl p-5 max-w-2xl shadow-xl backdrop-blur-xl';
            bubble.innerHTML = `<p class="text-gray-100">${escapeHtml(content)}</p>`;
            
            if (buttons && buttons.length > 0) {
                console.log('Creating button group with:', buttons);
                const buttonGroup = createButtonGroup(buttons, context);
                bubble.appendChild(buttonGroup);
            } else {
                console.log('No buttons to create');
            }
            
            messageContent.appendChild(bubble);
            messageDiv.appendChild(messageContent);
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Show progress message
        function showProgressMessage(message) {
            const progressDiv = document.createElement('div');
            progressDiv.className = 'chat-message';
            progressDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="glass-lighter border border-blue-400/30 rounded-2xl p-4 shadow-lg backdrop-blur-xl">
                        <div class="flex items-center gap-3">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-400"></div>
                            <span class="text-gray-100 text-sm font-medium">${escapeHtml(message)}</span>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(progressDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Remove after 3 seconds
            setTimeout(() => progressDiv.remove(), 3000);
        }

        // Update plan section
        async function updatePlanSection(section, content) {
            console.log('Updating plan section:', section, 'with content length:', content.length);
            
            // Fetch the latest plan to get all sections
            try {
                const response = await fetch(`/api/agent/plan?session_id=${sessionId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                if (data.success && data.plan) {
                    console.log('Fetched latest plan:', data.plan);
                    updatePlan(data.plan);
                } else {
                    console.error('Failed to fetch plan:', data);
                }
            } catch (error) {
                console.error('Error fetching plan:', error);
                // Fallback: just log the update
                console.log('Section update:', section, content);
            }
        }

        async function generateFinalPlan() {
            // Check if there's any research data to generate a plan from
            const planContent = document.getElementById('planContent');
            if (planContent.innerHTML.includes('No company selected') || planContent.innerHTML.includes('Start a conversation')) {
                addMessage('assistant', 'Please conduct some research first before generating the final plan. Try asking me to "Research [Company Name] comprehensively".');
                return;
            }

            const message = 'Generate the comprehensive final account plan document';
            addMessage('user', message);
            
            const typingId = showTypingIndicator();
            showActivityIndicator();
            
            try {
                console.log('Generating final plan for session:', sessionId);
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
                console.log('Generate plan response:', data);
                removeTypingIndicator(typingId);
                hideActivityIndicator();

                if (data.success && data.responses) {
                    data.responses.forEach(response => {
                        if (response.type === 'final_plan') {
                            addMessage('assistant', response.content);
                            if (response.plan) {
                                displayFinalAccountPlan(response.plan);
                            }
                        } else if (response.type === 'message') {
                            addMessage('assistant', response.content);
                        }
                    });
                } else {
                    addMessage('assistant', 'Unable to generate final plan. Please ensure you have completed some research first.');
                }
            } catch (error) {
                removeTypingIndicator(typingId);
                hideActivityIndicator();
                addMessage('assistant', 'Error generating final plan. Please try again.');
                console.error('Generate plan error:', error);
            }
        }

        async function clearHistory() {
            if (!confirm('Clear all conversation history and reset the account plan?')) return;

            try {
                const response = await fetch('/api/agent/history', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });

                if (response.ok) {
                    // Clear the chat
                    document.getElementById('chatMessages').innerHTML = '';
                    
                    // Reset plan
                    document.getElementById('planContent').innerHTML = '<div class="text-center text-gray-500 py-12"><p>Start a conversation to generate an account plan</p></div>';
                    document.getElementById('companyName').textContent = 'No company selected';
                    
                    // Generate new session
                    sessionId = generateSessionId();
                    localStorage.setItem('sessionId', sessionId);
                    
                    addMessage('assistant', 'History cleared. Ready for new research!');
                }
            } catch (error) {
                console.error('Error clearing history:', error);
            }
        }

        function showActivityIndicator() {
            document.getElementById('activityIndicator').style.display = 'flex';
        }

        function hideActivityIndicator() {
            document.getElementById('activityIndicator').style.display = 'none';
        }

        async function clearAccountPlan() {
            if (!confirm('Clear the current account plan? This will reset all research data for this session.')) {
                return;
            }

            try {
                console.log('Clearing account plan for session:', sessionId);
                
                // Call the clear history API to reset the plan
                const response = await fetch('/api/agent/history', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });

                console.log('Clear plan response status:', response.status);

                if (response.ok) {
                    // Reset the plan display
                    document.getElementById('planContent').innerHTML = `
                        <div class="text-center text-gray-500 py-12">
                            <div class="mb-4">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <p class="text-lg font-medium text-gray-900 mb-2">Account Plan Cleared</p>
                            <p class="text-sm text-gray-500">Start a new research conversation to generate a fresh account plan</p>
                        </div>
                    `;
                    
                    // Reset company name
                    document.getElementById('companyName').textContent = 'No company selected';
                    
                    // Generate new session for fresh start
                    sessionId = generateSessionId();
                    localStorage.setItem('sessionId', sessionId);
                    console.log('New session ID generated:', sessionId);
                    
                    // Add success message to chat
                    addMessage('assistant', 'Account plan cleared successfully. Ready for new research!');
                    
                } else {
                    console.error('Failed to clear account plan:', response.status);
                    addMessage('assistant', 'Failed to clear account plan. Please try again.');
                }
                
            } catch (error) {
                console.error('Error clearing account plan:', error);
                addMessage('assistant', 'Error clearing account plan: ' + error.message);
            }
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
            
            // Debug: Check if buttons are present
            setTimeout(() => {
                const clearBtn = document.querySelector('button[onclick="clearAccountPlan()"]');
                const generateBtn = document.querySelector('button[onclick="generateFinalPlan()"]');
                
                if (!clearBtn || !generateBtn) {
                    console.error('BUTTONS NOT FOUND IN DOM!');
                }
            }, 1000);
        });
    </script>
</body>
</html>

