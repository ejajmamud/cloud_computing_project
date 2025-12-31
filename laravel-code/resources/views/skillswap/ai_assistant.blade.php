@extends('frontend.layouts.app')

@section('title', 'Swap AI - UniKL Intelligence Hub')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.1/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.9/dist/purify.min.js"></script>

<style>
    /* ... keeping your existing styles ... */
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --user-msg-bg: #4F46E5;
        --ai-msg-bg: #F3F4F6;
        --border-color: #E5E7EB;
    }
    .chat-wrapper {
        max-width: 1000px;
        margin: 2rem auto;
        height: 80vh; 
        display: flex;
        flex-direction: column;
        background: var(--glass-bg);
        border-radius: 24px;
        box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.5);
        backdrop-filter: blur(20px);
        position: relative;
        z-index: 1; 
    }
    .chat-container-section {
        padding-top: 100px;
        padding-bottom: 2rem;
        background-color: #f0f2f5;
        min-height: 100vh;
    }
    .chat-header {
        padding: 1.5rem 2rem;
        background: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 10;
    }
    .brand-title {
        font-size: 1.25rem;
        font-weight: 800;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.5px;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        background: #DEF7EC;
        color: #03543F;
        border-radius: 999px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .status-dot {
        width: 8px;
        height: 8px;
        background: #0E9F6E;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    #chat-history {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
        scroll-behavior: smooth;
        background: #ffffff;
    }
    .message-row {
        display: flex;
        margin-bottom: 1.5rem;
        opacity: 0;
        animation: slideIn 0.3s forwards ease-out;
    }
    .message-row.user { justify-content: flex-end; }
    .avatar {
        width: 40px; height: 40px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 1.2rem;
    }
    .ai-avatar { background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); margin-right: 1rem; }
    .message-bubble {
        max-width: 75%; padding: 1rem 1.5rem; border-radius: 18px;
        font-size: 0.95rem; line-height: 1.6; position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .message-row.ai .message-bubble { background: var(--ai-msg-bg); color: #1F2937; border-top-left-radius: 4px; }
    .message-row.user .message-bubble { background: var(--user-msg-bg); color: white; border-bottom-right-radius: 4px; }
    .input-zone {
        padding: 1.5rem; background: white; border-top: 1px solid var(--border-color); position: relative;
    }
    .input-wrapper {
        background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 16px;
        padding: 0.5rem 1rem; display: flex; align-items: flex-end;
        transition: all 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }
    .input-wrapper:focus-within {
        border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); background: white;
    }
    textarea {
        flex: 1; background: transparent; border: none; resize: none;
        padding: 10px 0; max-height: 150px; min-height: 24px;
        font-size: 1rem; color: #1F2937;
    }
    textarea:focus { outline: none; }
    .send-btn {
        background: var(--user-msg-bg); color: white; border: none;
        width: 40px; height: 40px; border-radius: 10px; margin-left: 10px;
        margin-bottom: 2px; cursor: pointer; transition: transform 0.1s;
        display: flex; align-items: center; justify-content: center;
    }
    .send-btn:hover { background: #4338ca; transform: scale(1.05); }
    .send-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }
    
    /* Markdown Styles */
    .prose pre { background: #282c34; border-radius: 8px; padding: 1rem; overflow-x: auto; margin: 1rem 0; position: relative; }
    .prose code { font-family: 'Fira Code', monospace; font-size: 0.85em; }
    .prose p { margin-bottom: 0.75rem; }
    .copy-btn {
        position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.1);
        color: #fff; border: none; padding: 4px 8px; border-radius: 4px;
        font-size: 0.7rem; cursor: pointer; opacity: 0; transition: opacity 0.2s;
    }
    pre:hover .copy-btn { opacity: 1; }
    
    /* Animation */
    @keyframes slideIn { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(14, 159, 110, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(14, 159, 110, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(14, 159, 110, 0); } }
    .typing-indicator { display: flex; gap: 4px; padding: 4px 8px; }
    .typing-dot { width: 6px; height: 6px; background: #9CA3AF; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; } .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    
    /* Navbar override - Ensure links are visible */
    .navbar-light .navbar-nav .nav-link, .nav-link { color: #1F2937 !important; font-weight: 600; }
</style>

<div class="chat-container-section">
    <div class="container">
        <div class="chat-wrapper">
            <div class="chat-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar ai-avatar">🤖</div>
                    <div>
                        <h1 class="brand-title m-0">Swap AI <span style="font-size:0.8rem; color:#6B7280; font-weight:400;">UniKL Edition</span></h1>
                        <small class="text-muted">Powered by SkillSwap Team</small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <form action="{{ route('skillswap.ai.clear') }}" method="POST" onsubmit="return confirm('Clear chat history?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 20px; font-size: 0.75rem; padding: 5px 15px;">
                            <i class="fas fa-trash-alt"></i> Clear Chat
                        </button>
                    </form>
                    <div class="status-badge">
                        <div class="status-dot"></div> Online
                    </div>
                </div>
            </div>

            <div id="chat-history">
                <div class="message-row ai">
                    <div class="avatar ai-avatar">🤖</div>
                    <div class="message-bubble">
                        <strong>Hello!</strong> I am your Swap AI Tutor. 🎓<br>
                        I remember our past conversations. Ask me anything to continue!
                    </div>
                </div>

                @if(isset($history) && count($history) > 0)
                    @foreach($history as $msg)
                        <div class="message-row {{ $msg->role }}">
                            @if($msg->role == 'ai') <div class="avatar ai-avatar">🤖</div> @endif
                            
                            <div class="message-bubble prose raw-message-content" data-role="{{ $msg->role }}">
                                {{ $msg->content }}
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="input-zone">
                <div class="input-wrapper">
                    <textarea id="user-input" rows="1" placeholder="Type your academic question here..."></textarea>
                    <button id="send-btn" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted" style="font-size: 0.7rem;">AI can make mistakes. Please verify important information.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatHistory = document.getElementById('chat-history');
        const userInput = document.getElementById('user-input');
        const sendBtn = document.getElementById('send-btn');
        let isProcessing = false;

        // 1. Format Existing History on Load
        document.querySelectorAll('.raw-message-content').forEach(bubble => {
            const rawText = bubble.innerText;
            const role = bubble.getAttribute('data-role');
            
            if (role === 'ai') {
                // Apply Markdown formatting for AI messages
                bubble.innerHTML = DOMPurify.sanitize(marked.parse(rawText));
                // Apply Code Highlighting
                bubble.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                    addCopyButton(block);
                });
            } else {
                // Simple text for user messages
                bubble.innerHTML = escapeHtml(rawText).replace(/\n/g, '<br>');
            }
            // Remove the 'raw' class so we don't process it again
            bubble.classList.remove('raw-message-content');
        });
        
        // Scroll to the latest message immediately
        setTimeout(scrollToBottom, 100);

        // 2. Input Handling
        userInput.addEventListener('input', function() {
            this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';
            if(this.value === '') this.style.height = 'auto';
        });

        userInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
        });

        sendBtn.addEventListener('click', handleSend);

        function handleSend() {
            const question = userInput.value.trim();
            if (!question || isProcessing) return;

            addMessage(question, 'user');
            userInput.value = ''; userInput.style.height = 'auto';
            isProcessing = true; sendBtn.disabled = true;

            const loadingId = addLoadingIndicator();

            fetch("{{ route('skillswap.ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ question: question })
            })
            .then(response => response.json())
            .then(data => {
                removeMessage(loadingId);
                if (data.success) {
                    addMessage(data.answer, 'ai');
                } else {
                    addMessage("⚠️ " + data.answer, 'ai', true);
                }
            })
            .catch(error => {
                removeMessage(loadingId);
                addMessage("❌ Connection Error.", 'ai', true);
                console.error(error);
            })
            .finally(() => {
                isProcessing = false; sendBtn.disabled = false; userInput.focus();
            });
        }

        // --- Helper Functions ---
        
        function addMessage(content, type, isError = false) {
            const div = document.createElement('div');
            div.className = `message-row ${type}`;
            const avatarHtml = type === 'ai' ? `<div class="avatar ai-avatar">🤖</div>` : '';
            
            let processedContent = content;
            if (type === 'ai') {
                processedContent = DOMPurify.sanitize(marked.parse(content));
            } else {
                processedContent = escapeHtml(content).replace(/\n/g, '<br>');
            }
            
            const errorStyle = isError ? 'background: #FEE2E2; color: #991B1B; border: 1px solid #F87171;' : '';
            div.innerHTML = `${avatarHtml}<div class="message-bubble prose" style="${errorStyle}">${processedContent}</div>`;
            
            chatHistory.appendChild(div);
            
            if (type === 'ai') {
                div.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                    addCopyButton(block);
                });
            }
            scrollToBottom();
        }

        function addLoadingIndicator() {
            const id = 'loading-' + Date.now();
            const div = document.createElement('div'); div.id = id; div.className = 'message-row ai';
            div.innerHTML = `<div class="avatar ai-avatar">🤖</div><div class="message-bubble"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div>`;
            chatHistory.appendChild(div); scrollToBottom(); return id;
        }

        function removeMessage(id) { const el = document.getElementById(id); if (el) el.remove(); }
        function scrollToBottom() { chatHistory.scrollTop = chatHistory.scrollHeight; }
        
        function escapeHtml(text) {
            if (!text) return "";
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function addCopyButton(block) {
            const pre = block.parentElement; const button = document.createElement('button');
            button.className = 'copy-btn'; button.innerText = 'Copy';
            button.addEventListener('click', () => { navigator.clipboard.writeText(block.innerText).then(() => { button.innerText = 'Copied!'; setTimeout(() => button.innerText = 'Copy', 2000); }); });
            pre.appendChild(button);
        }
    });
</script>
@endsection
