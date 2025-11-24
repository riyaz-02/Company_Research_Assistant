<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Company Research Assistant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #000000 0%, #0a0f1a 25%, #0f172a 50%, #0a0f1a 75%, #000000 100%);
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            padding-bottom: 45px;
            overflow: hidden;
        }

        /* Enhanced animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.18) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.12) 0%, transparent 50%);
            animation: particleFloat 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes particleFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }
        
        .main-container {
            max-width: 1600px;
            margin: 20px auto;
            height: calc(100vh - 40px);
            display: flex;
            gap: 20px;
            padding: 0 20px;
        }
        
        /* Enhanced Glassmorphism styles */
        .glass {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(99, 102, 241, 0.3);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6), 0 2px 8px rgba(99, 102, 241, 0.1);
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-radius: 20px;
            overflow: hidden;
        }
        
        .summary-container {
            width: 420px;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .summary-header {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 2px solid rgba(99, 102, 241, 0.6);
            color: white;
            padding: 24px;
            border-bottom: 2px solid rgba(99, 102, 241, 0.5);
        }

        .summary-header h2 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .summary-content::-webkit-scrollbar {
            width: 6px;
        }

        .summary-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .summary-content::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.6);
            border-radius: 3px;
        }

        .summary-content::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.8);
        }
        
        .summary-item {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(99, 102, 241, 0.12) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
            border-color: rgba(99, 102, 241, 0.6);
        }

        .summary-item:hover::before {
            opacity: 1;
        }
        
        .summary-item h4 {
            color: #a5b4fc;
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 8px 0;
            position: relative;
            z-index: 1;
        }
        
        .summary-item p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .chat-header {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            color: white;
            padding: 24px;
            text-align: center;
            border-bottom: 2px solid rgba(99, 102, 241, 0.6);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
        }

        .chat-header h1 {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.6);
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.8);
        }
        
        .message {
            margin-bottom: 20px;
            animation: fadeIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
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
            padding: 14px 20px;
            border-radius: 16px;
            max-width: 70%;
            word-wrap: break-word;
            position: relative;
            overflow: hidden;
        }
        
        .message.user .message-content {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            border: 1px solid rgba(129, 140, 248, 0.6);
            color: white;
            box-shadow: 0 12px 32px rgba(79, 70, 229, 0.3), 0 2px 8px rgba(99, 102, 241, 0.2);
        }
        
        .message.assistant .message-content {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            color: #f3f4f6;
            border: 1px solid rgba(99, 102, 241, 0.3);
            max-width: 85%;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 2px 8px rgba(99, 102, 241, 0.1);
        }

        .message.assistant .message-content strong {
            color: #c7d2fe;
            font-weight: 700;
        }

        .message.assistant .message-content ul {
            list-style: none;
            margin: 12px 0;
            padding-left: 0;
        }

        .message.assistant .message-content li {
            margin: 8px 0;
            padding-left: 24px;
            position: relative;
        }

        .message.assistant .message-content li::before {
            content: "•";
            position: absolute;
            left: 8px;
            color: #818cf8;
            font-weight: bold;
        }
        
        .research-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 16px;
            padding: 24px;
            margin-top: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5), 0 2px 8px rgba(99, 102, 241, 0.08);
            max-width: 90%;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .research-card:hover {
            transform: translateY(-2px);
            background: rgba(15, 23, 42, 0.75);
            box-shadow: 0 16px 48px rgba(79, 70, 229, 0.25), 0 4px 12px rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.5);
            border-color: rgba(99, 102, 241, 0.6);
        }
        
        .research-card h3 {
            color: #e0e7ff;
            font-size: 20px;
            margin-bottom: 14px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(79, 70, 229, 0.6);
        }
        
        .research-card p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.7;
            white-space: pre-wrap;
            margin-bottom: 16px;
        }

        .research-card strong {
            color: #e0e7ff;
            font-weight: 700;
        }

        .research-card ul {
            list-style: none;
            margin: 12px 0;
            padding-left: 0;
        }

        .research-card li {
            margin: 8px 0;
            padding-left: 24px;
            position: relative;
        }

        .research-card li::before {
            content: "•";
            position: absolute;
            left: 8px;
            color: #a5b4fc;
            font-weight: bold;
        }
        
        .chart-container {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 16px;
        }
        
        .chat-input-area {
            padding: 24px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-top: 2px solid rgba(99, 102, 241, 0.6);
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.5), 0 -1px 4px rgba(99, 102, 241, 0.1);
            display: flex;
            gap: 12px;
        }
        
        .chat-input {
            flex: 1;
            padding: 14px 20px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 28px;
            font-size: 15px;
            color: white;
            outline: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .chat-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .chat-input:focus {
            border-color: rgba(99, 102, 241, 0.8);
            background: rgba(15, 23, 42, 0.7);
            box-shadow: 0 0 32px rgba(99, 102, 241, 0.3), 0 4px 16px rgba(0, 0, 0, 0.4);
        }
        
        .send-button {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            color: white;
            border: 1px solid rgba(129, 140, 248, 0.6);
            padding: 14px 20px;
            border-radius: 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.3), 0 2px 8px rgba(99, 102, 241, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .send-button svg {
            width: 20px;
            height: 20px;
            fill: white;
        }
        
        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(79, 70, 229, 0.4), 0 4px 12px rgba(99, 102, 241, 0.25);
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
        }

        .send-button:active {
            transform: translateY(0);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .voice-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            color: white;
            border: 1px solid rgba(16, 185, 129, 0.6);
            padding: 14px 20px;
            border-radius: 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3), 0 2px 8px rgba(5, 150, 105, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .voice-button svg {
            width: 20px;
            height: 20px;
            fill: white;
        }

        .voice-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4), 0 4px 12px rgba(5, 150, 105, 0.25);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .voice-button.recording {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-color: rgba(239, 68, 68, 0.6);
            animation: pulse-recording 1.5s ease-in-out infinite;
        }

        @keyframes pulse-recording {
            0%, 100% { box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4), 0 2px 8px rgba(220, 38, 38, 0.3); }
            50% { box-shadow: 0 8px 32px rgba(239, 68, 68, 0.6), 0 2px 12px rgba(220, 38, 38, 0.4); }
        }

        .voice-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Action Buttons */
        .action-btn {
            padding: 6px 14px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            color: white;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .action-btn:hover {
            background: rgba(99, 102, 241, 0.5);
            border-color: rgba(99, 102, 241, 0.8);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.6);
        }

        .action-btn.edit {
            background: rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.5);
            color: #c7d2fe;
        }

        .action-btn.regenerate {
            background: rgba(168, 85, 247, 0.2);
            border-color: rgba(168, 85, 247, 0.5);
            color: #e9d5ff;
        }

        .action-btn.save {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.5);
            color: #a7f3d0;
        }

        .action-btn.cancel {
            background: rgba(107, 114, 128, 0.2);
            border-color: rgba(107, 114, 128, 0.5);
            color: #d1d5db;
        }

        /* Prompt Buttons */
        .prompt-button {
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .prompt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
            background: rgba(99, 102, 241, 0.4);
            border-color: rgba(99, 102, 241, 0.7);
        }

        .prompt-button.primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            border-color: rgba(129, 140, 248, 0.6);
        }

        .prompt-button.warning {
            background: rgba(251, 146, 60, 0.3);
            border-color: rgba(251, 146, 60, 0.6);
            color: #fdba74;
        }

        .prompt-button.danger {
            background: rgba(239, 68, 68, 0.3);
            border-color: rgba(239, 68, 68, 0.6);
            color: #fca5a5;
        }

        .prompt-button.secondary {
            background: rgba(107, 114, 128, 0.3);
            border-color: rgba(107, 114, 128, 0.6);
            color: #d1d5db;
        }
        
        .thinking {
            display: inline-block;
            padding: 14px 20px;
            background: rgba(79, 70, 229, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(99, 102, 241, 0.5);
            border-radius: 16px;
            color: #c7d2fe;
            font-style: italic;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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

        /* Footer styling */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 41, 59, 0.85) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-top: 1px solid rgba(99, 102, 241, 0.4);
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.5), 0 -1px 4px rgba(99, 102, 241, 0.1);
            z-index: 1000;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .footer-divider {
            color: rgba(99, 102, 241, 0.5);
            margin: 0 4px;
        }

        .footer a {
            color: #a5b4fc;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: #c7d2fe;
        }

        /* Adjust main container to account for footer */
        body {
            padding-bottom: 60px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="chat-container glass">
            <div class="chat-header">
                <h1 style="font-size: 28px; font-weight: 700; margin: 0; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);">AI Company Research Assistant</h1>
                <p style="font-size: 14px; opacity: 0.95; margin-top: 8px;">Intelligent research with data visualization powered by AI</p>
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
                <button id="sendButton" class="send-button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
                <button id="voiceButton" class="voice-button">
                    <svg id="voiceIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                        <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="summary-container glass">
            <div class="summary-header">
                <h2 style="font-size: 20px; font-weight: 700; margin: 0;">Account Plan</h2>
                <p style="font-size: 13px; opacity: 0.9; margin-top: 8px;">Collected insights & data</p>
            </div>
            <div class="summary-content" id="summaryContent">
                <p style="color: rgba(255, 255, 255, 0.5); text-align: center; margin-top: 60px; font-size: 14px;">
                    No research data yet.<br><br>Start by entering a company name!
                </p>
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
        const voiceButton = document.getElementById('voiceButton');
        const voiceIcon = document.getElementById('voiceIcon');
        const summaryContent = document.getElementById('summaryContent');

        // Voice recognition setup
        let recognition = null;
        let isRecording = false;
        let silenceTimer = null;
        let finalTranscript = '';

        // Check if browser supports speech recognition
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                isRecording = true;
                voiceButton.classList.add('recording');
                // Change to stop icon
                voiceIcon.innerHTML = '<path d="M6 6h12v12H6z"/>';
                finalTranscript = '';
                messageInput.placeholder = 'Listening...';
            };

            recognition.onresult = (event) => {
                let interimTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript + ' ';
                    } else {
                        interimTranscript += transcript;
                    }
                }

                // Update input field with current transcript
                messageInput.value = (finalTranscript + interimTranscript).trim();

                // Clear previous silence timer
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                }

                // Set new silence timer (2 seconds of silence will trigger send)
                silenceTimer = setTimeout(() => {
                    if (isRecording && finalTranscript.trim()) {
                        stopRecording();
                        // Auto-send after detecting silence
                        setTimeout(() => {
                            if (messageInput.value.trim()) {
                                sendMessage();
                            }
                        }, 500);
                    }
                }, 2000);
            };

            recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                if (event.error === 'no-speech') {
                    // User didn't speak, just continue listening
                    return;
                } else if (event.error === 'not-allowed') {
                    addAssistantMessage('Microphone access denied. Please allow microphone permissions in your browser settings.');
                    stopRecording();
                } else if (event.error === 'aborted') {
                    // Normal abort, don't show error
                    return;
                } else if (event.error === 'network') {
                    addAssistantMessage('Network error. Please check your internet connection.');
                    stopRecording();
                } else {
                    // For other errors, just restart
                    console.log('Restarting recognition after error:', event.error);
                    if (isRecording) {
                        setTimeout(() => {
                            try {
                                recognition.start();
                            } catch (e) {
                                console.error('Could not restart:', e);
                            }
                        }, 100);
                    }
                }
            };

            recognition.onend = () => {
                if (isRecording) {
                    // If still in recording state, restart (for continuous listening)
                    setTimeout(() => {
                        if (isRecording) {
                            try {
                                recognition.start();
                            } catch (e) {
                                console.error('Could not restart recognition:', e);
                                stopRecording();
                            }
                        }
                    }, 100);
                }
            };
        } else {
            // Hide voice button if not supported
            voiceButton.style.display = 'none';
        }

        function startRecording() {
            if (!recognition) {
                addAssistantMessage('Voice recognition is not supported in your browser. Please use Chrome, Edge, or Safari.');
                return;
            }

            if (isRecording) {
                return; // Already recording
            }

            try {
                finalTranscript = '';
                messageInput.value = '';
                recognition.start();
            } catch (e) {
                console.error('Error starting recognition:', e);
                // Only show error if it's not already running
                if (e.message && !e.message.includes('already started')) {
                    addAssistantMessage('Could not start voice recognition. Please try again.');
                }
            }
        }

        function stopRecording() {
            if (recognition && isRecording) {
                isRecording = false;
                recognition.stop();
                voiceButton.classList.remove('recording');
                // Restore microphone icon
                voiceIcon.innerHTML = '<path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>';
                messageInput.placeholder = 'Type a message or company name...';
                
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                    silenceTimer = null;
                }
            }
        }

        // Voice button click handler
        voiceButton.addEventListener('click', () => {
            if (isRecording) {
                stopRecording();
            } else {
                startRecording();
            }
        });
        
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
                                <button onclick="editSection(${actualIndex})" class="action-btn edit" title="Edit this section">
                                    Edit
                                </button>
                                <button onclick="regenerateSection(${actualIndex})" class="action-btn regenerate" title="Regenerate with AI">
                                    Regenerate
                                </button>
                            </div>
                        </div>
                        <p id="summary-text-${actualIndex}" style="margin: 0;">${escapeHtml(preview)}</p>
                        <div id="edit-form-${actualIndex}" style="display: none; margin-top: 10px;">
                            <textarea id="edit-textarea-${actualIndex}" style="width: 100%; min-height: 150px; padding: 12px; background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; color: white;">${escapeHtml(item.data)}</textarea>
                            <div style="display: flex; gap: 8px; margin-top: 8px;">
                                <button onclick="saveEdit(${actualIndex})" class="action-btn save">
                                    Save
                                </button>
                                <button onclick="cancelEdit(${actualIndex})" class="action-btn cancel">
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

        function formatText(text) {
            // Escape HTML first
            let formatted = escapeHtml(text);
            
            // Convert **text** to <strong>text</strong>
            formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            
            // Convert bullet points (• ) to proper list items
            formatted = formatted.replace(/• (.+?)(?=\n|$)/g, '<li>$1</li>');
            
            // Wrap consecutive list items in <ul>
            formatted = formatted.replace(/(<li>.*<\/li>)+/g, '<ul>$&</ul>');
            
            // Convert --- to horizontal rule
            formatted = formatted.replace(/---/g, '<hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 12px 0;">');
            
            // Convert single line breaks to <br>, but remove excessive multiple line breaks
            formatted = formatted.replace(/\n{3,}/g, '\n\n'); // Reduce 3+ line breaks to 2
            formatted = formatted.replace(/\n\n/g, '<br><br>'); // Double line breaks
            formatted = formatted.replace(/\n(?!<br>)/g, '<br>'); // Single line breaks
            
            return formatted;
        }

        function addAssistantMessage(text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant';
            messageDiv.innerHTML = `<div class="message-content">${formatText(text)}</div>`;
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
                    <p>${formatText(data)}</p>
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
                    <button class="prompt-button primary" onclick="sendQuickMessage('yes, generate pdf')">Yes, Generate PDF</button>
                    <button class="prompt-button secondary" onclick="sendQuickMessage('no thanks')">No, Thanks</button>
                `;
            } else {
                // Standard research action buttons
                buttonsHtml = `
                    <button class="prompt-button primary" onclick="sendQuickMessage('yes')">Yes, Continue</button>
                    <button class="prompt-button warning" onclick="sendQuickMessage('deep research')">Deep Research</button>
                    <button class="prompt-button danger" onclick="handleStopButton()">No, Stop</button>
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

I can help you research any company with comprehensive insights including company overview and background, financial analysis and performance, products and services, competitive landscape, and market opportunities.`;
                    
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message assistant';
                    messageDiv.innerHTML = `
                        <div class="message-content">
                            <p style="margin-bottom: 16px;">${escapeHtml(welcomeMessage)}</p>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; justify-content: center;">
                                <button class="prompt-button" onclick="sendQuickMessage('research Google')" style="padding: 12px 24px; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); backdrop-filter: blur(10px); color: white; border: 1px solid rgba(129, 140, 248, 0.5); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4); transition: all 0.3s ease;">
                                    Research Google
                                </button>
                                <button class="prompt-button" onclick="sendQuickMessage('research Tesla')" style="padding: 12px 24px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); backdrop-filter: blur(10px); color: white; border: 1px solid rgba(192, 132, 252, 0.5); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 4px 16px rgba(124, 58, 237, 0.4); transition: all 0.3s ease;">
                                    Research Tesla
                                </button>
                                <button class="prompt-button" onclick="sendQuickMessage('research Apple')" style="padding: 12px 24px; background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%); backdrop-filter: blur(10px); color: white; border: 1px solid rgba(96, 165, 250, 0.5); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4); transition: all 0.3s ease;">
                                    Research Apple
                                </button>
                            </div>
                        </div>
                    `;
                    messagesContainer.appendChild(messageDiv);
                    scrollToBottom();
                }, 300);
            }
        }

        showWelcomeMessage();
        messageInput.focus();
    </script>

    <div class="footer">
        <div class="footer-left">
            <span style="font-weight: 600; color: #c7d2fe;">Eightfold AI: Agentic AI Internship Program</span>
            <span class="footer-divider">|</span>
            <span>Recruitment Assessment</span>
            <span class="footer-divider">|</span>
            <a href="https://github.com/riyaz-02/Company_Research_Assistant" target="_blank" rel="noopener noreferrer">GitHub Repository</a>
        </div>
        <div class="footer-right">
            <span><strong>Name:</strong> Sk Riyaz</span>
            <span class="footer-divider">|</span>
            <span><strong>Email:</strong> <a href="mailto:riyaz.skk1@gmail.com">riyaz.skk1@gmail.com</a></span>
            <span class="footer-divider">|</span>
            <span><strong>College:</strong> JIS College of Engineering</span>
        </div>
    </div>

</body>
</html>

