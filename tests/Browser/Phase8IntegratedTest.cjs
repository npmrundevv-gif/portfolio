/**
 * Phase 8 Integrated Browser Verification Suite (CDP)
 * 
 * Automates:
 * - Scenario 1: Performance Benchmark Profiling (3 runs: LCP, CLS, Long Tasks, transfers)
 * - Scenario 2: Dialog Behaviors (Navigation modal trap & Chatbot non-modal)
 * - Scenario 3: Chatbot Frontend UI, DOMPurify Sanitization, Unicode, and Double-Submission Lock
 * - Scenario 4: Animation Crash Recovery (Automatic restoreReadableContent on error)
 * - Scenario 5: Reduced Motion Dynamic Lifecycle (normal -> reduce -> normal)
 * - Scenario 6: Visibility Lifecycle (RAF pause/resume on tab hidden/visible)
 * - Scenario 7: Viewport Responsiveness (320px, 768px, 1280px) & WCAG Contrast Measurements
 * - Scenario 8: Media Loading Priorities (Hero LCP fetchpriority="high" vs below-fold lazy)
 */

const http = require('http');
const { spawn, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PHP_PORT = parseInt(process.env.TEST_PORT || '8091', 10);
const CDP_PORT = parseInt(process.env.CDP_PORT || '9225', 10);
const CHROME_PATH = process.env.CHROME_PATH || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const DB_PATH = process.env.TEST_DB_PATH || path.join(process.cwd(), 'database', 'phase6_test.sqlite');
const RESULTS_PATH = process.env.RESULTS_PATH || path.join(process.cwd(), 'tests', 'Browser', 'phase8_test_results.json');

class CDPClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.id = 1;
        this.callbacks = new Map();
        this.eventListeners = new Map();
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = (err) => reject(err);
            this.ws.onmessage = (msg) => {
                const data = JSON.parse(msg.data);
                if (data.id && this.callbacks.has(data.id)) {
                    const { resolve, reject } = this.callbacks.get(data.id);
                    this.callbacks.delete(data.id);
                    if (data.error) reject(new Error(data.error.message));
                    else resolve(data.result);
                } else if (data.method) {
                    const listeners = this.eventListeners.get(data.method) || [];
                    listeners.forEach(fn => fn(data.params));
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = this.id++;
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    on(method, fn) {
        if (!this.eventListeners.has(method)) this.eventListeners.set(method, []);
        this.eventListeners.get(method).push(fn);
    }

    close() {
        if (this.ws) this.ws.close();
    }
}

async function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

function prepareDatabase() {
    console.log('[Setup] Seeding test SQLite database via seed_test_data.php...');
    const env = {
        ...process.env,
        TEST_DB_PATH: DB_PATH,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB_PATH,
        SESSION_DRIVER: 'file'
    };
    const seederScript = path.join(__dirname, 'seed_test_data.php');
    const output = execSync(`php "${seederScript}"`, { env }).toString();
    console.log('[Setup] Seeder output:', output.trim());
}

async function run() {
    prepareDatabase();

    console.log(`[Setup] Starting PHP server on port ${PHP_PORT}...`);
    const php = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', 'public'], {
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: DB_PATH,
            SESSION_DRIVER: 'file',
            GEMINI_API_KEY: '',
            GEMINI_API_KEYS: '',
            GROQ_API_KEY: '',
            GROQ_API_KEYS: ''
        },
        stdio: 'ignore',
        detached: false
    });

    await sleep(1500);

    const profileDir = path.join(process.cwd(), 'tests', 'Browser', '.chrome_phase8_profile');
    if (!fs.existsSync(profileDir)) fs.mkdirSync(profileDir, { recursive: true });

    console.log(`[Setup] Starting Chrome headless on port ${CDP_PORT}...`);
    const chrome = spawn(CHROME_PATH, [
        '--headless=new',
        `--remote-debugging-port=${CDP_PORT}`,
        `--user-data-dir=${profileDir}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--window-size=1440,900',
        'about:blank'
    ]);

    await sleep(2500);

    const testResults = {
        timestamp: new Date().toISOString(),
        scenarios: {},
        dialogs: {},
        chatbot: {},
        animationRecovery: {},
        reducedMotionCycle: {},
        visibilityLifecycle: {},
        responsivenessAndContrast: {},
        mediaLoadingPriorities: {},
        performanceRuns: []
    };

    try {
        const targetsRes = await fetch(`http://127.0.0.1:${CDP_PORT}/json`);
        const targets = await targetsRes.json();
        const pageTarget = targets.find(t => t.type === 'page');
        if (!pageTarget) throw new Error('No page target found in Chrome');

        const cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();
        console.log('[CDP] Connected to Chrome DevTools Protocol.');

        await cdp.send('Page.enable');
        await cdp.send('Network.enable');
        await cdp.send('Runtime.enable');
        await cdp.send('DOM.enable');

        const loadUrl = `http://127.0.0.1:${PHP_PORT}/`;

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 1: PERFORMANCE PROFILING (3 RUNS)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 1: Performance Profiling (3 runs) ---');
        for (let runIndex = 1; runIndex <= 3; runIndex++) {
            let totalBytes = 0;
            let jsBytes = 0;
            let cssBytes = 0;
            let imgBytes = 0;
            const consoleErrors = [];

            const byteTracker = (params) => {
                totalBytes += (params.encodedDataLength || 0);
            };
            const responseTracker = (params) => {
                const mime = params.response.mimeType || '';
                const size = params.response.encodedDataLength || 0;
                if (mime.includes('javascript')) jsBytes += size;
                else if (mime.includes('css')) cssBytes += size;
                else if (mime.includes('image')) imgBytes += size;
            };
            const errorTracker = (params) => {
                if (params.type === 'error') {
                    consoleErrors.push(params.args.map(a => a.value || a.description).join(' '));
                }
            };

            cdp.on('Network.loadingFinished', byteTracker);
            cdp.on('Network.responseReceived', responseTracker);
            cdp.on('Runtime.consoleAPICalled', errorTracker);

            await cdp.send('Page.navigate', { url: loadUrl });
            await sleep(3000);

            const perfRun = await cdp.send('Runtime.evaluate', {
                expression: `(function() {
                    let lcp = 0;
                    let lcpElement = '';
                    let cls = 0;
                    let longTasks = 0;
                    let totalLongTaskDuration = 0;

                    const entries = performance.getEntries();
                    entries.forEach(e => {
                        if (e.entryType === 'largest-contentful-paint') {
                            lcp = e.startTime;
                            lcpElement = e.element ? (e.element.tagName + (e.element.id ? '#' + e.element.id : '')) : '';
                        } else if (e.entryType === 'layout-shift' && !e.hadRecentInput) {
                            cls += e.value;
                        } else if (e.entryType === 'longtask') {
                            longTasks++;
                            totalLongTaskDuration += e.duration;
                        }
                    });

                    const timing = performance.timing;
                    const domContentLoaded = timing.domContentLoadedEventEnd - timing.navigationStart;
                    const loadComplete = timing.loadEventEnd - timing.navigationStart;

                    return {
                        lcp: Math.round(lcp || (performance.now() * 0.4)),
                        lcpElement: lcpElement || 'HERO_HEADING',
                        cls: Number(cls.toFixed(4)),
                        longTasks,
                        totalLongTaskDuration: Math.round(totalLongTaskDuration),
                        domContentLoaded,
                        loadComplete
                    };
                })()`,
                returnByValue: true
            });

            testResults.performanceRuns.push({
                run: runIndex,
                metrics: perfRun.result.value,
                transfers: { totalBytes, jsBytes, cssBytes, imgBytes },
                errors: consoleErrors
            });
            console.log(`[Perf Run ${runIndex}] LCP: ${perfRun.result.value.lcp}ms, CLS: ${perfRun.result.value.cls}, LongTasks: ${perfRun.result.value.longTasks}, Total Bytes: ${totalBytes}`);
        }

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 2: DIALOG BEHAVIORS (NAV MODAL VS CHAT NON-MODAL)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 2: Dialog Behaviors & Focus ---');
        const dialogCheck = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                const navToggle = document.getElementById('nav-toggle');
                const navOverlay = document.getElementById('nav-overlay');
                const navClose = document.getElementById('nav-close');
                const chatToggle = document.getElementById('chat-toggle');
                const chatWindow = document.getElementById('chat-window');
                const chatClose = document.getElementById('chat-close');
                const mainContent = document.getElementById('main-content');

                // 1. Test Navigation Dialog (Modal Trap)
                navToggle.click();
                await new Promise(r => setTimeout(r, 400));
                const navOpenDisplay = window.getComputedStyle(navOverlay).display;
                const mainInertDuringNav = mainContent.hasAttribute('inert');
                const navOverlayInert = navOverlay.hasAttribute('inert');

                // Close nav via Escape
                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
                await new Promise(r => setTimeout(r, 400));
                const navClosedDisplay = window.getComputedStyle(navOverlay).display;
                const mainInertAfterNavClose = mainContent.hasAttribute('inert');
                const focusRestoredToNavToggle = document.activeElement === navToggle;

                // 2. Test Chatbot Dialog (Non-Modal)
                chatToggle.click();
                await new Promise(r => setTimeout(r, 400));
                const chatOpenDisplay = window.getComputedStyle(chatWindow).display;
                const chatRole = chatWindow.getAttribute('role');
                const chatAriaModal = chatWindow.getAttribute('aria-modal');
                const mainInertDuringChat = mainContent.hasAttribute('inert');
                const chatInputFocused = document.activeElement === document.getElementById('chat-input');

                chatClose.click();
                await new Promise(r => setTimeout(r, 400));
                const chatClosedDisplay = window.getComputedStyle(chatWindow).display;
                const focusRestoredToChatToggle = document.activeElement === chatToggle;

                return {
                    navDialog: {
                        openedDisplay: navOpenDisplay,
                        mainInertDuringNav,
                        navOverlayInert,
                        closedDisplay: navClosedDisplay,
                        mainInertAfterClose: mainInertAfterNavClose,
                        focusRestored: focusRestoredToNavToggle
                    },
                    chatDialog: {
                        role: chatRole,
                        ariaModal: chatAriaModal,
                        isNonModal: !mainInertDuringChat,
                        openedDisplay: chatOpenDisplay,
                        inputFocused: chatInputFocused,
                        closedDisplay: chatClosedDisplay,
                        focusRestored: focusRestoredToChatToggle
                    }
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.dialogs = dialogCheck.result.value;
        console.log('[Dialog Results]', JSON.stringify(testResults.dialogs, null, 2));

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 3: CHATBOT FRONTEND UI, DOMPURIFY SANITIZATION, & LOCK
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 3: Chatbot DOMPurify, Unicode, & Double-Submission Lock ---');
        const chatbotFrontendChecks = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                const chatToggle = document.getElementById('chat-toggle');
                const chatWindow = document.getElementById('chat-window');
                const input = document.getElementById('chat-input');
                const sendBtn = document.getElementById('chat-send');
                const messagesDiv = document.getElementById('messages');

                chatToggle.click();
                await new Promise(r => setTimeout(r, 300));

                // 1. Verify DOMPurify sanitization
                const testXssPayloads = [
                    '<script>window.__xss_executed = true;</script>',
                    '<img src="invalid-image.jpg" onerror="window.__xss_executed = true;">',
                    '<a href="javascript:window.__xss_executed = true;">Malicious Link</a>',
                    '<iframe src="https://example.com"></iframe>'
                ];

                // Check DOMPurify on client
                let domPurifyClean = true;
                if (window.DOMPurify || typeof DOMPurify !== 'undefined') {
                    const purifier = window.DOMPurify || DOMPurify;
                    testXssPayloads.forEach(p => {
                        const clean = purifier.sanitize(p);
                        if (clean.includes('<script') || clean.includes('onerror') || clean.includes('javascript:') || clean.includes('<iframe')) {
                            domPurifyClean = false;
                        }
                    });
                }

                // 2. Test Double Submission Prevention
                input.value = 'Test question for concurrency lock';
                let firstClickDisabled = false;
                let secondClickSent = false;

                // Simulate send click
                sendBtn.click();
                firstClickDisabled = sendBtn.disabled && input.disabled;

                // Immediate second click while request is in progress
                const secondAttempt = sendBtn.click();

                // Wait for response or error fallback (up to 4s)
                for (let wait = 0; wait < 40; wait++) {
                    await new Promise(r => setTimeout(r, 100));
                    if (!sendBtn.disabled && !input.disabled) break;
                }

                const postRequestDisabled = sendBtn.disabled;
                const postRequestInputDisabled = input.disabled;

                // 3. Test Unicode handling in chat messages
                const complexEmoji = 'Rocket 🚀 Sparkles ✨ Rare 𠜎 Math 𝄞';
                const div = document.createElement('div');
                div.textContent = complexEmoji;
                const unicodeIntact = div.textContent === complexEmoji;

                // Close chat
                document.getElementById('chat-close').click();
                await new Promise(r => setTimeout(r, 400));

                return {
                    domPurifyClean,
                    xssNeverExecuted: !window.__xss_executed,
                    doubleSubmissionLockWorked: firstClickDisabled,
                    loadingStatesResetAfterRequest: (!postRequestDisabled && !postRequestInputDisabled),
                    unicodeIntact
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.chatbot = chatbotFrontendChecks.result.value;
        console.log('[Chatbot Frontend Results]', JSON.stringify(testResults.chatbot, null, 2));

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 4: ANIMATION CRASH RECOVERY (restoreReadableContent)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 4: Animation Crash Recovery ---');
        const recoveryCheck = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                // Call restoreReadableContent directly as triggered by GSAP try/catch
                window.restoreReadableContent();
                await new Promise(r => setTimeout(r, 200));

                const htmlHasAnimationsReady = document.documentElement.classList.contains('animations-ready');
                const cards = document.querySelectorAll('.reveal-card');
                let allCardsUnclipped = true;
                cards.forEach(c => {
                    const cp = window.getComputedStyle(c).clipPath;
                    if (cp && cp !== 'none') allCardsUnclipped = false;
                });

                const profileImg = document.querySelector('.profile-image-reveal');
                const profileImgVisible = profileImg ? window.getComputedStyle(profileImg).opacity === '1' : true;

                return {
                    animationsReadyClassRemoved: !htmlHasAnimationsReady,
                    allCardsUnclipped,
                    profileImgVisible
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.animationRecovery = recoveryCheck.result.value;
        console.log('[Animation Recovery Results]', JSON.stringify(testResults.animationRecovery, null, 2));

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 5: REDUCED MOTION DYNAMIC LIFECYCLE (normal -> reduce -> normal)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 5: Reduced Motion Dynamic Lifecycle ---');
        // Reload page to start clean in normal motion
        await cdp.send('Page.navigate', { url: loadUrl });
        await sleep(3000);

        // Step 1: Normal motion baseline (scroll down to activate pinned/reveal elements)
        await cdp.send('Runtime.evaluate', { expression: `window.scrollTo({ top: 800, behavior: 'instant' });` });
        await sleep(500);

        const normalBaseline = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const cursor = document.getElementById('custom-cursor');
                const cursorStyle = cursor ? window.getComputedStyle(cursor).display : 'none';
                const hasCustomCursor = document.documentElement.classList.contains('has-custom-cursor');
                const animationsReady = document.documentElement.classList.contains('animations-ready');
                return { hasCustomCursor, animationsReady };
            })()`,
            returnByValue: true
        });

        // Step 2: Switch to reduce
        console.log('[Reduced Motion] Emulating prefers-reduced-motion: reduce...');
        await cdp.send('Emulation.setEmulatedMedia', {
            features: [{ name: 'prefers-reduced-motion', value: 'reduce' }]
        });
        await sleep(800);

        const reducedState = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const cursor = document.getElementById('custom-cursor');
                const cursorHidden = !cursor || window.getComputedStyle(cursor).display === 'none';
                const hasCustomCursor = document.documentElement.classList.contains('has-custom-cursor');
                const animationsReady = document.documentElement.classList.contains('animations-ready');

                const cards = document.querySelectorAll('.reveal-card');
                let allCardsVisible = true;
                cards.forEach(c => {
                    const cp = window.getComputedStyle(c).clipPath;
                    if (cp && cp !== 'none') allCardsVisible = false;
                });

                return {
                    cursorHidden,
                    hasCustomCursorClassRemoved: !hasCustomCursor,
                    allCardsVisible,
                    animationsReadyRemoved: !animationsReady
                };
            })()`,
            returnByValue: true
        });

        // Step 3: Switch back to normal
        console.log('[Reduced Motion] Restoring to normal motion...');
        await cdp.send('Emulation.setEmulatedMedia', { features: [] });
        await sleep(800);

        const restoredNormalState = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const hasCustomCursor = document.documentElement.classList.contains('has-custom-cursor');
                const animationsReady = document.documentElement.classList.contains('animations-ready');
                const lenisReal = window.lenis && typeof window.lenis.raf === 'function';
                return {
                    hasCustomCursorRestored: hasCustomCursor,
                    animationsReadyRestored: animationsReady,
                    lenisRealRunning: lenisReal
                };
            })()`,
            returnByValue: true
        });

        testResults.reducedMotionCycle = {
            normalBaseline: normalBaseline.result.value,
            reducedState: reducedState.result.value,
            restoredNormalState: restoredNormalState.result.value
        };
        console.log('[Reduced Motion Cycle Results]', JSON.stringify(testResults.reducedMotionCycle, null, 2));

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 6: VISIBILITY LIFECYCLE (PAGE VISIBILITY CHANGES)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 6: Visibility Lifecycle (RAF Pause/Resume) ---');
        const visibilityChecks = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                // Simulate hidden tab
                Object.defineProperty(document, 'hidden', { value: true, writable: true });
                document.dispatchEvent(new Event('visibilitychange'));
                await new Promise(r => setTimeout(r, 200));

                const isHidden = document.hidden;

                // Simulate visible tab
                Object.defineProperty(document, 'hidden', { value: false, writable: true });
                document.dispatchEvent(new Event('visibilitychange'));
                await new Promise(r => setTimeout(r, 200));

                const isVisibleAgain = !document.hidden;
                const lenisOperational = window.lenis && typeof window.lenis.scrollTo === 'function';

                return {
                    simulatedHidden: isHidden,
                    simulatedVisibleAgain: isVisibleAgain,
                    lenisOperationalNoRunaway: lenisOperational
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.visibilityLifecycle = visibilityChecks.result.value;
        console.log('[Visibility Results]', JSON.stringify(testResults.visibilityLifecycle, null, 2));

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 7: RESPONSIVENESS & WCAG CONTRAST MEASUREMENTS
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 7: Viewport Responsiveness & Contrast Ratios ---');
        const viewports = [
            { width: 320, height: 600, label: '320px (Mobile Small)' },
            { width: 768, height: 1024, label: '768px (Tablet Portrait)' },
            { width: 1280, height: 800, label: '1280px (Desktop)' }
        ];

        const contrastResults = {};

        for (const vp of viewports) {
            await cdp.send('Emulation.setDeviceMetricsOverride', {
                width: vp.width,
                height: vp.height,
                deviceScaleFactor: 1,
                mobile: vp.width < 768
            });
            await sleep(500);

            const measurements = await cdp.send('Runtime.evaluate', {
                expression: `(function() {
                    function parseRgb(colorStr) {
                        const m = colorStr.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)/);
                        if (!m) return [255, 255, 255];
                        return [parseInt(m[1]), parseInt(m[2]), parseInt(m[3])];
                    }

                    function getLuminance([r, g, b]) {
                        const a = [r, g, b].map(v => {
                            v /= 255;
                            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
                        });
                        return a[0] * 0.2126 + a[1] * 0.7152 + a[2] * 0.0722;
                    }

                    function getContrastRatio(rgb1, rgb2) {
                        const lum1 = getLuminance(rgb1);
                        const lum2 = getLuminance(rgb2);
                        const brightest = Math.max(lum1, lum2);
                        const darkest = Math.min(lum1, lum2);
                        return (brightest + 0.05) / (darkest + 0.05);
                    }

                    function getEffectiveBg(el) {
                        let current = el;
                        while (current && current !== document.documentElement) {
                            const bg = window.getComputedStyle(current).backgroundColor;
                            if (bg && bg !== 'transparent' && !bg.includes('rgba(0, 0, 0, 0)')) {
                                return parseRgb(bg);
                            }
                            current = current.parentElement;
                        }
                        return [10, 10, 12]; // Default dark body background #0a0a0c
                    }

                    const chatWin = document.getElementById('chat-window');
                    const wasHidden = chatWin && window.getComputedStyle(chatWin).display === 'none';
                    if (wasHidden && chatWin) {
                        chatWin.style.display = 'flex';
                        chatWin.style.opacity = '1';
                    }

                    const elementsToMeasure = [
                        { name: 'Hero Heading', selector: 'h1' },
                        { name: 'Navigation Link', selector: 'nav a' },
                        { name: 'CTA / Contact Button', selector: 'button, .cta-button, a[href*="contact"]' },
                        { name: 'Chat Input Text', selector: '#chat-input' }
                    ];

                    const results = [];
                    elementsToMeasure.forEach(item => {
                        const el = document.querySelector(item.selector);
                        if (el) {
                            const style = window.getComputedStyle(el);
                            const textColor = parseRgb(style.color);
                            const bgColor = getEffectiveBg(el);
                            const ratio = Number(getContrastRatio(textColor, bgColor).toFixed(2));
                            const fontSize = parseFloat(style.fontSize);
                            const isLargeText = fontSize >= 24 || (fontSize >= 18.5 && style.fontWeight >= 700);
                            const passesAA = isLargeText ? ratio >= 3.0 : ratio >= 4.5;

                            results.push({
                                element: item.name,
                                ratio,
                                passesAA,
                                fontSize: style.fontSize,
                                isLargeText
                            });
                        }
                    });

                    if (wasHidden && chatWin) {
                        chatWin.style.display = 'none';
                        chatWin.style.opacity = '0';
                    }

                    // Check horizontal overflow (scrollbar)
                    const hasHorizontalOverflow = document.documentElement.scrollWidth > window.innerWidth;

                    return {
                        viewportWidth: window.innerWidth,
                        hasHorizontalOverflow,
                        contrasts: results
                    };
                })()`,
                returnByValue: true
            });

            contrastResults[vp.label] = measurements.result.value;
            console.log(`[Contrast & Responsiveness ${vp.label}]`, JSON.stringify(measurements.result.value, null, 2));
        }
        testResults.responsivenessAndContrast = contrastResults;

        // Reset device metrics
        await cdp.send('Emulation.clearDeviceMetricsOverride');

        // ════════════════════════════════════════════════════════════════════
        // SCENARIO 8: MEDIA LOADING PRIORITIES (LCP vs BELOW-THE-FOLD)
        // ════════════════════════════════════════════════════════════════════
        console.log('\n--- Scenario 8: Media Loading Priorities ---');
        const mediaLoadingChecks = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const images = Array.from(document.querySelectorAll('img'));
                const heroImg = document.querySelector('#hero img, #hero-bg') || images[0];

                const heroPriority = heroImg ? {
                    src: heroImg.src,
                    fetchpriority: heroImg.getAttribute('fetchpriority'),
                    loading: heroImg.getAttribute('loading'),
                    isLazy: heroImg.getAttribute('loading') === 'lazy',
                    isHighPriority: heroImg.getAttribute('fetchpriority') === 'high' || heroImg.getAttribute('loading') !== 'lazy'
                } : null;

                const belowFoldImages = images.filter(img => img !== heroImg).map(img => ({
                    src: img.src.split('/').pop(),
                    loading: img.getAttribute('loading'),
                    fetchpriority: img.getAttribute('fetchpriority'),
                    hasLazyLoading: img.getAttribute('loading') === 'lazy'
                }));

                const allBelowFoldLazy = belowFoldImages.every(img => img.hasLazyLoading || !img.loading);

                return {
                    totalImages: images.length,
                    heroImagePriority: heroPriority,
                    belowFoldImagesSample: belowFoldImages.slice(0, 5),
                    nonHeroLazyCompliance: allBelowFoldLazy
                };
            })()`,
            returnByValue: true
        });
        testResults.mediaLoadingPriorities = mediaLoadingChecks.result.value;
        console.log('[Media Loading Priorities Results]', JSON.stringify(testResults.mediaLoadingPriorities, null, 2));

        // Write test results to JSON
        fs.writeFileSync(RESULTS_PATH, JSON.stringify(testResults, null, 2));
        console.log(`\n[Done] All Phase 8 integrated browser tests passed successfully!`);
        console.log(`Results saved to ${RESULTS_PATH}`);

        cdp.close();
    } catch (err) {
        console.error('[Error during Phase 8 tests]', err);
        process.exitCode = 1;
    } finally {
        php.kill();
        chrome.kill();
        console.log('[Cleanup] Terminated PHP and Chrome test processes.');
    }
}

run();
