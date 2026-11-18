import { marked } from 'marked';
import DOMPurify from 'dompurify';
import gsap from 'gsap';

export const initChat = (chatRoute, csrfToken, brandName) => {
    const messagesDiv = document.getElementById('messages');
    const input = document.getElementById('chat-input');
    const chatWindow = document.getElementById('chat-window');
    const chatToggle = document.getElementById('chat-toggle');
    const chatClose = document.getElementById('chat-close');
    const sendBtn = document.getElementById('chat-send');

    if (!messagesDiv || !input || !chatWindow || !chatToggle || !chatClose || !sendBtn) {
        return;
    }

    let chatHistory = [];
    let isOpen = false;
    let isRequestInProgress = false; // Prevent duplicate submissions
    marked.setOptions({ breaks: true, gfm: true });

    // Configure DOMPurify to add rel attributes to links
    DOMPurify.addHook('afterSanitizeAttributes', function (node) {
        if (node.tagName === 'A') {
            node.setAttribute('rel', 'noopener noreferrer');
            if (node.getAttribute('target') === '_blank' || !node.getAttribute('target')) {
                node.setAttribute('target', '_blank');
            }
        }
    });

    let savedScrollY = 0;
    let savedBodyTop = '';
    let isScrollLocked = false;
    const desktopViewport = window.matchMedia('(min-width: 640px)');

    const syncScrollLock = () => {
        const shouldLock = isOpen && !desktopViewport.matches;
        if (shouldLock === isScrollLocked) return;
        isScrollLocked = shouldLock;

        if (shouldLock) {
            savedScrollY = window.scrollY;
            savedBodyTop = document.body.style.top;
            document.body.style.top = `-${savedScrollY}px`;
            document.body.classList.add('chat-open');
            window.lenis?.stop();
        } else {
            document.body.classList.remove('chat-open');
            document.body.style.top = savedBodyTop;
            window.lenis?.start();
            window.scrollTo({ top: savedScrollY, behavior: 'instant' });
        }
    };

    const toggleChat = () => {
        isOpen = !isOpen;
        syncScrollLock();
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (isOpen) {
            chatToggle.setAttribute('aria-expanded', 'true');
            chatWindow.setAttribute('aria-hidden', 'false');

            chatWindow.style.display = 'flex';
            if (prefersReduced) {
                gsap.set(chatWindow, { opacity: 1, y: 0, scale: 1, pointerEvents: 'auto' });
            } else {
                gsap.to(chatWindow, { opacity: 1, y: 0, scale: 1, duration: 0.5, pointerEvents: 'auto', ease: "back.out(1.7)" });
            }
            chatToggle.classList.add('chat-toggle-hidden');

            setTimeout(() => {
                input.focus();
            }, 150);

            if (messagesDiv.children.length === 0) {
                setTimeout(() => addMessage(`Hi! I'm an AI Assistant for ${brandName}. 🤖 How can I help?`, 'ai'), 600);
            }
        } else {
            chatToggle.setAttribute('aria-expanded', 'false');
            chatWindow.setAttribute('aria-hidden', 'true');

            if (prefersReduced) {
                gsap.set(chatWindow, { opacity: 0, y: 30, scale: 0.9, pointerEvents: 'none' });
                chatWindow.style.display = 'none';
            } else {
                gsap.to(chatWindow, { opacity: 0, y: 30, scale: 0.9, duration: 0.4, pointerEvents: 'none', ease: "power2.in", onComplete: () => chatWindow.style.display = 'none' });
            }
            chatToggle.classList.remove('chat-toggle-hidden');

            setTimeout(() => {
                chatToggle.focus();
            }, 100);
        }
    };

    chatToggle.addEventListener('click', toggleChat);
    chatClose.addEventListener('click', toggleChat);
    desktopViewport.addEventListener('change', syncScrollLock);

    // ESC Key Closure
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) {
            e.preventDefault();
            toggleChat();
        }
    });


    // Generic fallback message for all error types — never expose raw backend errors
    const FALLBACK_ERROR_MSG = "I've hit a small snag. Mind trying again in a moment?";

    async function sendMessage() {
        const message = input.value.trim();
        if (!message || isRequestInProgress) return;
        
        isRequestInProgress = true;
        addMessage(message, 'user');
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        const announcer = document.getElementById('chat-announcer');
        if (announcer) {
            announcer.textContent = 'Message sent. Waiting for response...';
        }
        
        const indicator = showTypingIndicator();
        try {
            const controller = new AbortController();
            // Client timeout: slightly longer than server budget (30s) to let server respond
            const timeoutId = setTimeout(() => controller.abort(), 35000);

            const response = await fetch(chatRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    history: chatHistory
                }),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (response.status === 429) {
                throw new Error("You're sending messages too quickly. Please wait a moment.");
            }

            if (!response.ok) {
                let errorMsg = FALLBACK_ERROR_MSG;
                try {
                    const errData = await response.json();
                    // Only show server error messages that are safe public strings
                    if (errData.error && typeof errData.error === 'string') {
                        errorMsg = errData.error;
                    }
                } catch (e) {
                    // Non-JSON response — use fallback
                }
                throw new Error(errorMsg);
            }

            const data = await response.json();

            if (!data.text || typeof data.text !== 'string') {
                throw new Error(FALLBACK_ERROR_MSG);
            }

            indicator.remove();
            addMessage(data.text, 'ai', true);

            // Add to history only on successful response (bound each entry to backend contract: max 1000 Unicode code points, max 30 individual messages / 15 pairs)
            // Full text remains displayed to visitor without truncation
            chatHistory.push({ role: 'user', text: truncateUnicode(message, 1000) });
            chatHistory.push({ role: 'ai', text: truncateUnicode(data.text, 1000) });
            
            if (chatHistory.length > 30) {
                chatHistory = chatHistory.slice(-30);
            }
        } catch (e) {
            indicator.remove();
            const msg = e.name === 'AbortError'
                ? "The request timed out. Mind trying again?"
                : (e.message || FALLBACK_ERROR_MSG);
            addMessage(msg, 'ai');
            // Do NOT push error messages into chatHistory
        } finally {
            isRequestInProgress = false;
            input.disabled = false;
            sendBtn.disabled = false;
            if (isOpen && (document.activeElement === input || !document.activeElement || document.activeElement === document.body)) {
                input.focus();
            }
        }
    }

    function showTypingIndicator() {
        const div = document.createElement('div');
        div.className = 'typing-indicator';
        div.innerHTML = '<span class="typing-dot" style="animation-delay:0s"></span><span class="typing-dot" style="animation-delay:0.2s"></span><span class="typing-dot" style="animation-delay:0.4s"></span>';
        messagesDiv.appendChild(div);
        gsap.from(div, { opacity: 0, y: 10, duration: 0.3 });
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        return div;
    }

    let lastAnnouncement = '';

    function addMessage(text, type, animate = false) {
        const div = document.createElement('div');
        div.className = `chat-message chat-${type} markdown-body`;
        messagesDiv.appendChild(div);
        
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReduced) {
            gsap.from(div, { opacity: 0, y: 15, scale: 0.9, duration: 0.4, ease: "power2.out" });
        }

        if (type === 'ai') {
            const rawHtml = marked.parse(text);
            const htmlContent = DOMPurify.sanitize(rawHtml);

            // Announce new message to screen readers via aria-live region without repeating history
            const announcer = document.getElementById('chat-announcer');
            if (announcer) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = htmlContent;
                const cleanText = (tempDiv.textContent || tempDiv.innerText || '').trim();
                if (cleanText && cleanText !== lastAnnouncement) {
                    lastAnnouncement = cleanText;
                    announcer.textContent = 'Assistant: ' + cleanText;
                }
            }

            if (animate && !prefersReduced) {
                typewriterEffect(div, text, htmlContent);
            } else {
                div.innerHTML = htmlContent;
            }
        } else {
            // User messages: always textContent, never innerHTML
            div.textContent = text;
        }
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function typewriterEffect(element, text, htmlContent) {
        if (!htmlContent) {
            const rawHtml = marked.parse(text);
            htmlContent = DOMPurify.sanitize(rawHtml);
        }
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlContent;
        const plainText = tempDiv.textContent || tempDiv.innerText;

        let index = 0;
        let displayText = '';

        const cursor = document.createElement('span');
        cursor.className = 'typewriter-cursor';
        cursor.textContent = '|';

        element.textContent = '';
        element.appendChild(cursor);

        function type() {
            if (index < plainText.length) {
                const char = plainText[index];
                displayText += char;

                element.textContent = displayText;
                element.appendChild(cursor);

                index++;
                messagesDiv.scrollTop = messagesDiv.scrollHeight;

                let delay = 15;
                if (char === '.' || char === '!' || char === '?') {
                    delay = 200;
                } else if (char === ',' || char === ':' || char === ';') {
                    delay = 100;
                } else if (char === '\n') {
                    delay = 150;
                } else if (char === ' ') {
                    delay = 10;
                }

                setTimeout(type, delay);
            } else {
                cursor.remove();
                // Final render: sanitized HTML with DOMPurify
                element.innerHTML = htmlContent;
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }

        setTimeout(type, 100);
    }


    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
};

/**
 * Safely truncate a string to a maximum number of Unicode code points.
 * Unlike String.prototype.slice(), this iterates Unicode code points (characters)
 * to guarantee that UTF-16 surrogate pairs (such as emojis) are never split in half,
 * preventing malformed UTF-8 characters from corrupting the JSON payload.
 *
 * @param {string} str
 * @param {number} maxCodePoints
 * @returns {string}
 */
export function truncateUnicode(str, maxCodePoints = 1000) {
    if (!str || typeof str !== 'string') return '';
    const codePoints = Array.from(str);
    if (codePoints.length <= maxCodePoints) {
        return str;
    }
    return codePoints.slice(0, maxCodePoints).join('');
}
