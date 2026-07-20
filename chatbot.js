/**
 * chatbot.js - Frontend script for SmartGrave AI Chatbot
 * Features: Speech-to-Text, Audio Player for Al-Fatihah, Floating Panel UI, Markdown Parsing.
 */

document.addEventListener("DOMContentLoaded", () => {
    // ---------------------------------------------------------------
    // 1. Inject Styles
    // ---------------------------------------------------------------
    const style = document.createElement("style");
    style.innerHTML = `
        /* Floating Chatbot Container */
        #smartgrave-chatbot {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            pointer-events: none;
        }

        /* Floating Button */
        .chat-trigger-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #064e3b 0%, #10b981 100%);
            box-shadow: 0 10px 25px rgba(6, 78, 59, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            position: relative;
            pointer-events: auto;
            animation: chat-wiggle 6s ease-in-out infinite;
        }

        .chat-trigger-btn::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.4);
            z-index: -1;
            animation: chat-ping 3s cubic-bezier(0.25, 0, 0, 1) infinite;
        }

        @keyframes chat-ping {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        @keyframes chat-wiggle {
            0%, 90%, 100% { transform: rotate(0) scale(1); }
            92% { transform: rotate(-8deg) scale(1.05); }
            94% { transform: rotate(8deg) scale(1.05); }
            96% { transform: rotate(-6deg) scale(1.05); }
            98% { transform: rotate(6deg) scale(1.05); }
        }

        .chat-trigger-btn:hover {
            transform: scale(1.1) rotate(5deg) !important;
            box-shadow: 0 15px 30px rgba(6, 78, 59, 0.4);
            animation: none; /* Disable wiggle on hover */
        }

        .chat-trigger-btn:hover::before {
            display: none; /* Disable ping on hover */
        }

        .chat-trigger-btn i {
            font-size: 24px;
            transition: transform 0.3s ease;
        }

        .chat-trigger-btn.active {
            animation: none; /* Disable animation when chat is open */
        }

        .chat-trigger-btn.active::before {
            display: none; /* Disable ping when active */
        }

        .chat-trigger-btn.active i {
            transform: rotate(90deg);
        }

        /* Red dot notification */
        .chat-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid #ffffff;
            animation: pulse-badge 2s infinite;
        }

        @keyframes pulse-badge {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Chat Panel */
        .chat-panel {
            width: 380px;
            height: 580px;
            max-height: calc(100vh - 120px);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(6, 78, 59, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin-bottom: 16px;
            transform: scale(0.8) translateY(50px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-origin: bottom right;
        }

        .chat-panel.open {
            transform: scale(1) translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        /* Header */
        .chat-header {
            background: linear-gradient(135deg, #022c22 0%, #064e3b 100%);
            padding: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fbbf24;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chat-title-text h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .chat-status {
            font-size: 11px;
            color: #34d399;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
        }

        .chat-status::before {
            content: '';
            width: 8px;
            height: 8px;
            background-color: #34d399;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #34d399;
        }

        .chat-close-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 18px;
            transition: color 0.2s;
            padding: 4px;
        }

        .chat-close-btn:hover {
            color: #ffffff;
        }

        /* Message Area */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background-color: #f8fafc;
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
        }

        /* Message Bubbles */
        .chat-bubble {
            max-width: 85%;
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            animation: bubble-in 0.3s ease forwards;
        }

        @keyframes bubble-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chat-bubble.user {
            align-self: flex-end;
            background-color: #064e3b;
            color: #ffffff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.15);
        }

        .chat-bubble.bot {
            align-self: flex-start;
            background-color: #ffffff;
            color: #1e293b;
            border-bottom-left-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        /* Alert blocks rendering */
        .chat-alert {
            margin: 10px 0;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 13px;
        }
        .chat-alert-warning {
            background-color: #fffbeb;
            border-left-color: #f59e0b;
            color: #78350f;
        }
        .chat-alert-note {
            background-color: #f0fdf4;
            border-left-color: #10b981;
            color: #064e3b;
        }

        /* Audio Player custom container */
        .audio-player-container {
            margin-top: 10px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 12px;
            padding: 12px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .audio-player-title {
            font-size: 12px;
            font-weight: 600;
            color: #fbbf24;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .audio-player-controls {
            width: 100%;
            height: 32px;
            outline: none;
        }

        /* Input Area */
        .chat-input-area {
            padding: 16px;
            background-color: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            background-color: #f1f5f9;
            border-radius: 24px;
            padding: 4px 16px;
            border: 1.5px solid transparent;
            transition: all 0.2s;
        }

        .chat-input-wrapper:focus-within {
            border-color: #10b981;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .chat-input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            padding: 8px 0;
            font-size: 14px;
            color: #1e293b;
        }

        /* Icon Buttons */
        #smartgrave-chatbot .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .mic-btn {
            color: #64748b;
        }

        .mic-btn.recording {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.1);
            animation: pulse-mic 1.5s infinite;
        }

        @keyframes pulse-mic {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        #smartgrave-chatbot .send-btn {
            background-color: #064e3b !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(6, 78, 59, 0.2) !important;
        }

        #smartgrave-chatbot .send-btn i {
            color: #ffffff !important;
        }

        #smartgrave-chatbot .send-btn:hover {
            background-color: #042f24 !important;
            color: #ffffff !important;
            transform: scale(1.05) !important;
        }

        #smartgrave-chatbot .send-btn:disabled {
            background-color: #cbd5e1 !important;
            color: #94a3b8 !important;
            box-shadow: none !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        #smartgrave-chatbot .send-btn:disabled i {
            color: #94a3b8 !important;
        }

        /* Typing indicator */
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 4px 8px;
            align-items: center;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background-color: #94a3b8;
            border-radius: 50%;
            animation: typing-bounce 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing-bounce {
            0%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-6px); }
        }

        /* Media queries for Mobile devices */
        @media (max-width: 480px) {
            #smartgrave-chatbot {
                bottom: 12px;
                right: 12px;
            }
            .chat-panel {
                width: calc(100vw - 24px);
                height: calc(100vh - 100px);
                bottom: 80px;
                right: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // ---------------------------------------------------------------
    // 2. Render Widget HTML Structure
    // ---------------------------------------------------------------
    const widget = document.createElement("div");
    widget.id = "smartgrave-chatbot";
    widget.innerHTML = `
        <div class="chat-panel" id="chatPanel">
            <div class="chat-header">
                <div class="chat-header-info">
                    <div class="chat-avatar">
                        <i class="fas fa-mosque"></i>
                    </div>
                    <div class="chat-title-text">
                        <h3>PusaraBot</h3>
                        <div class="chat-status">Pembantu Maya</div>
                    </div>
                </div>
                <button class="chat-close-btn" id="closeChatBtn" aria-label="Tutup Sembang">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="chat-bubble bot">
                    Assalamualaikum! Saya <strong>PusaraBot</strong>, pembantu maya anda untuk sistem <strong>SmartGrave Bangi Lama</strong>.<br><br>
                    Ada apa yang boleh saya bantu? Anda boleh:<br>
                    <div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div>Tanya cara daftar khairat kematian</div></div>
                    <div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div>Minta panduan adab ziarah kubur</div></div>
                    <div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div>Tanya cara cari lokasi pusara</div></div>
                    <div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div>Minta saya mainkan Surah Al-Fatihah</div></div>
                    <div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div><a href="https://wa.me/601126923772?text=Saya%20perlukan%20bantuan%20mengenai%20SmartGrave" target="_blank" style="color: #059669; font-weight: bold; text-decoration: underline;"><i class="fab fa-whatsapp"></i> Hubungi Admin SmartGrave</a></div></div><br>
                    <em>(Klik butang 🎙️ untuk bercakap terus dengan saya!)</em>
                </div>
            </div>
            <div class="chat-input-area">
                <div class="chat-input-wrapper">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Tulis mesej anda..." autocomplete="off">
                    <button class="icon-btn mic-btn" id="micBtn" title="Bercakap (Speech to Text)">
                        <i class="fas fa-microphone"></i>
                    </button>
                </div>
                <button class="icon-btn send-btn" id="sendBtn" disabled title="Hantar Mesej">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
        <div class="chat-trigger-btn" id="chatTriggerBtn" title="Tanya Pembantu Maya AI">
            <i class="fas fa-comment-dots"></i>
            <div class="chat-badge" id="chatBadge"></div>
        </div>
    `;
    document.body.appendChild(widget);

    // Get elements
    const chatTriggerBtn = document.getElementById("chatTriggerBtn");
    const chatPanel = document.getElementById("chatPanel");
    const closeChatBtn = document.getElementById("closeChatBtn");
    const chatMessages = document.getElementById("chatMessages");
    const chatInput = document.getElementById("chatInput");
    const sendBtn = document.getElementById("sendBtn");
    const micBtn = document.getElementById("micBtn");
    const chatBadge = document.getElementById("chatBadge");

    let chatHistory = []; // Simpan sejarah mesej untuk perbualan berterusan
    let isWidgetOpened = false;

    // Format Markdown ringkas untuk gelembung perbualan
    function formatMarkdown(text) {
        if (!text) return "";
        let formatted = text
            // Ganti baris baharu
            .replace(/\r\n/g, "<br>")
            .replace(/\n/g, "<br>")
            // Ganti bold **teks**
            .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
            // Ganti kod ringkas `code`
            .replace(/`(.*?)`/g, "<code class='bg-slate-100 text-emerald-800 px-1 rounded'>$1</code>")
            // Ganti blockquote khas (Alerts)
            .replace(/&gt; \[!WARNING\](.*?)($|<br>)/gi, "<div class='chat-alert chat-alert-warning'><i class='fas fa-exclamation-triangle mr-2'></i><strong>PENTING:</strong>$1</div>")
            .replace(/&gt; \[!NOTE\](.*?)($|<br>)/gi, "<div class='chat-alert chat-alert-note'><i class='fas fa-info-circle mr-2'></i>$1</div>")
            .replace(/&gt; (.*?)($|<br>)/g, "<blockquote class='border-l-4 border-slate-300 pl-3 italic my-2 text-slate-500'>$1</blockquote>")
            // Ganti pautan Markdown [text](url)
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, "<a href='$2' target='_blank' style='color:#059669;font-weight:bold;text-decoration:underline;'>$1</a>")
            // Ganti bullet list (di awal baris atau selepas tag <br>)
            .replace(/(?:^|<br>)\s*\*\s*(.*?)(?=$|<br>)/g, (match, p1) => {
                return `<div class="flex items-start gap-2 my-1"><span class="text-emerald-600 mt-1.5 text-xs"><i class="fas fa-circle"></i></span><div>${p1}</div></div>`;
            })
            // Ganti italic *teks* atau _teks_
            .replace(/\*([^*]+)\*/g, "<em>$1</em>")
            .replace(/_([^_]+)_/g, "<em>$1</em>");
        
        return formatted;
    }

    // Paparkan jawapan selamat datang pertama dengan markdown terformat (Sudah dipra-format dalam HTML di atas)

    // Toggle Chat Panel
    chatTriggerBtn.addEventListener("click", () => {
        isWidgetOpened = !isWidgetOpened;
        if (isWidgetOpened) {
            chatPanel.classList.add("open");
            chatTriggerBtn.classList.add("active");
            chatBadge.style.display = "none"; // Hide badge once opened
            chatInput.focus();
        } else {
            closeChat();
        }
    });

    closeChatBtn.addEventListener("click", closeChat);

    function closeChat() {
        chatPanel.classList.remove("open");
        chatTriggerBtn.classList.remove("active");
        isWidgetOpened = false;
    }

    // Monitor Input to Enable/Disable Send Button
    chatInput.addEventListener("input", () => {
        sendBtn.disabled = chatInput.value.trim() === "";
    });

    // Handle Enter Key Press
    chatInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !sendBtn.disabled) {
            sendMessage();
        }
    });

    sendBtn.addEventListener("click", sendMessage);

    // ---------------------------------------------------------------
    // 3. Send Message logic
    // ---------------------------------------------------------------
    async function sendMessage() {
        const messageText = chatInput.value.trim();
        if (messageText === "") return;

        // 1. Paparkan mesej pengguna
        appendMessage(messageText, "user");
        chatInput.value = "";
        sendBtn.disabled = true;

        // 2. Paparkan Typing Indicator
        const typingIndicator = showTypingIndicator();

        try {
            // 3. Panggil API backend PHP
            const response = await fetch("chatbot_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    message: messageText,
                    history: chatHistory
                })
            });

            const data = await response.json();
            
            // Buang typing indicator
            typingIndicator.remove();

            if (data.status === "success" || data.reply) {
                let reply = data.reply;
                let playAlFatihah = false;

                // 4. Periksa trigger [PLAY_ALFATIHAH]
                if (reply.includes("[PLAY_ALFATIHAH]")) {
                    playAlFatihah = true;
                    reply = reply.replace("[PLAY_ALFATIHAH]", "").trim();
                }

                // Paparkan mesej bot
                appendMessage(reply, "bot");

                // Mainkan Al-Fatihah jika dicetuskan
                if (playAlFatihah) {
                    playAlFatihahAudio();
                }

                // Kemas kini sejarah chat
                chatHistory.push({ role: "user", parts: messageText });
                chatHistory.push({ role: "bot", parts: reply });
                
                // Hadkan saiz history (maksimum 10 mesej terakhir) untuk penjimatan token
                if (chatHistory.length > 20) {
                    chatHistory.shift();
                    chatHistory.shift();
                }
            } else {
                appendMessage("Maaf, berlaku ralat pemprosesan maklumat.", "bot");
            }
        } catch (err) {
            typingIndicator.remove();
            appendMessage("Maaf, saya tidak dapat menghubungi pelayan. Sila periksa sambungan internet anda.", "bot");
            console.error("Chatbot Error:", err);
        }
    }

    // Helper to Append Message Bubble
    function appendMessage(text, role) {
        const bubble = document.createElement("div");
        bubble.className = `chat-bubble ${role}`;
        bubble.innerHTML = formatMarkdown(text);
        chatMessages.appendChild(bubble);
        scrollToBottom();
    }

    // Show Typing Indicator
    function showTypingIndicator() {
        const bubble = document.createElement("div");
        bubble.className = "chat-bubble bot";
        bubble.innerHTML = `
            <div class="typing-indicator">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        `;
        chatMessages.appendChild(bubble);
        scrollToBottom();
        return bubble;
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // ---------------------------------------------------------------
    // 4. Surah Al-Fatihah Audio Player Integration
    // ---------------------------------------------------------------
    function playAlFatihahAudio() {
        // Bina element audio player premium
        const playerContainer = document.createElement("div");
        playerContainer.className = "audio-player-container";
        
        playerContainer.innerHTML = `
            <div class="audio-player-title">
                <i class="fas fa-play-circle text-yellow-400 animate-pulse"></i>
                <span>Surah Al-Fatihah - Sheikh Mishary Alafasy</span>
            </div>
            <audio class="audio-player-controls" controls autoplay>
                <source src="https://server8.mp3quran.net/afs/001.mp3" type="audio/mpeg">
                Browser anda tidak menyokong pemain audio.
            </audio>
        `;
        
        chatMessages.appendChild(playerContainer);
        scrollToBottom();
    }

    // ---------------------------------------------------------------
    // 5. Speech-to-Text (Speech Recognition) Implementation
    // ---------------------------------------------------------------
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (SpeechRecognition) {
        const recognition = new SpeechRecognition();
        recognition.lang = "ms-MY"; // Tetapkan input ke Bahasa Melayu
        recognition.continuous = false;
        recognition.interimResults = false;

        let isRecording = false;

        micBtn.addEventListener("click", () => {
            if (!isRecording) {
                recognition.start();
            } else {
                recognition.stop();
            }
        });

        recognition.onstart = () => {
            isRecording = true;
            micBtn.classList.add("recording");
            chatInput.placeholder = "Mendengar suara anda...";
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            chatInput.value = transcript;
            sendBtn.disabled = false;
        };

        recognition.onerror = (event) => {
            console.error("Speech Recognition Error:", event.error);
            isRecording = false;
            micBtn.classList.remove("recording");
            chatInput.placeholder = "Tulis mesej anda...";
        };

        recognition.onend = () => {
            isRecording = false;
            micBtn.classList.remove("recording");
            chatInput.placeholder = "Tulis mesej anda...";
        };
    } else {
        // Sekiranya browser tidak menyokong Speech Recognition, sorokkan butang mic
        micBtn.style.display = "none";
    }
});
