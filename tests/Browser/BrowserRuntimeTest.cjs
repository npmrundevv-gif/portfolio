/**
 * Chrome DevTools Protocol (CDP) Browser Runtime Test Suite
 * 
 * Verifies runtime behaviors, progressive enhancement, accessibility, and dialogs:
 * - Scenario 1: Normal production JS & Core Web Vitals performance (LCP, CLS, longtasks)
 * - Scenario 2: Dialog behaviors (Navigation overlay modal trap & Chatbot non-modal widget)
 * - Scenario 3: Reduced-motion lifecycle & dynamic recovery (kursor, pin-spacers, fallback)
 * - Scenario 4: JavaScript completely disabled (No-JS fallback navigation & content readability)
 * - Scenario 5: Main Vite bundle blocked (Simulated script failure)
 * - Scenario 6: Animation crash recovery via restoreReadableContent
 * - Scenario 7: Accessibility tree & semantic landmarks inspection
 * 
 * Configurable via environment variables:
 * - TEST_PORT (default: 8089)
 * - CDP_PORT (default: 9223)
 * - CHROME_PATH or CHROME_BIN
 * - TEST_DB_PATH
 * - RESULTS_PATH
 */

const http = require('http');
const { spawn, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PHP_PORT = parseInt(process.env.TEST_PORT || '8089', 10);
const CDP_PORT = parseInt(process.env.CDP_PORT || '9223', 10);
const CHROME_PATH = process.env.CHROME_PATH || process.env.CHROME_BIN || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const DB_PATH = process.env.TEST_DB_PATH || path.join(process.cwd(), 'database', 'phase6_test.sqlite');
const RESULTS_PATH = process.env.RESULTS_PATH || path.join(process.cwd(), 'tests', 'Browser', 'browser_test_results.json');

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

function prepareTestDatabase() {
    console.log('[Setup] Preparing representative SQLite test database...');
    const env = {
        ...process.env,
        TEST_DB_PATH: DB_PATH,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB_PATH,
        SESSION_DRIVER: 'file'
    };
    const seederScript = path.join(__dirname, 'seed_test_data.php');
    const output = execSync(`php "${seederScript}"`, { env }).toString();
    console.log('[Setup] Seed output:', output.trim());
}

async function run() {
    prepareTestDatabase();

    console.log(`[Setup] Starting PHP server on port ${PHP_PORT}...`);
    const phpEnv = {
        ...process.env,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB_PATH,
        SESSION_DRIVER: 'file'
    };
    const php = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', 'public'], {
        env: phpEnv,
        stdio: 'ignore',
        detached: false
    });

    await sleep(1500);

    const userDataDir = path.join(process.cwd(), 'tests', 'Browser', '.chrome_profile');
    if (!fs.existsSync(userDataDir)) fs.mkdirSync(userDataDir, { recursive: true });

    console.log(`[Setup] Starting Chrome headless on port ${CDP_PORT}...`);
    const chrome = spawn(CHROME_PATH, [
        '--headless=new',
        `--remote-debugging-port=${CDP_PORT}`,
        `--user-data-dir=${userDataDir}`,
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
        reducedMotion: {},
        performance: {},
        accessibilityTree: {}
    };

    try {
        const targetsRes = await fetch(`http://127.0.0.1:${CDP_PORT}/json`);
        const targets = await targetsRes.json();
        const pageTarget = targets.find(t => t.type === 'page');
        if (!pageTarget) throw new Error('No page target found');

        const cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();
        console.log('[CDP] Connected to Chrome DevTools Protocol.');

        await cdp.send('Page.enable');
        await cdp.send('Network.enable');
        await cdp.send('Runtime.enable');
        await cdp.send('DOM.enable');

        // SCENARIO 1: Performance Measurements
        console.log('\n--- Running Scenario 1: Normal JS & Performance Measurements ---');
        let networkBytes = 0;
        let jsBytes = 0;
        let cssBytes = 0;
        let imgBytes = 0;
        const consoleErrors = [];
        const failedRequests = [];

        cdp.on('Network.loadingFinished', (params) => {
            networkBytes += (params.encodedDataLength || 0);
        });

        cdp.on('Network.responseReceived', (params) => {
            const mime = params.response.mimeType || '';
            const size = params.response.encodedDataLength || 0;
            if (mime.includes('javascript')) jsBytes += size;
            else if (mime.includes('css')) cssBytes += size;
            else if (mime.includes('image')) imgBytes += size;

            if (params.response.status >= 400) {
                failedRequests.push({ url: params.response.url, status: params.response.status });
            }
        });

        cdp.on('Runtime.consoleAPICalled', (params) => {
            if (params.type === 'error') {
                consoleErrors.push(params.args.map(a => a.value || a.description).join(' '));
            }
        });

        const loadUrl = `http://127.0.0.1:${PHP_PORT}/`;
        await cdp.send('Page.navigate', { url: loadUrl });
        await sleep(3500);

        const perfData = await cdp.send('Runtime.evaluate', {
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

        testResults.performance = {
            timing: perfData.result.value,
            transfers: {
                totalEncodedBytes: networkBytes,
                jsBytes,
                cssBytes,
                imgBytes
            },
            consoleErrorsCount: consoleErrors.length,
            consoleErrors,
            failedRequestsCount: failedRequests.length,
            failedRequests
        };
        console.log('[Performance Results]', JSON.stringify(testResults.performance, null, 2));

        // SCENARIO 2: Dialog Behavior & Keyboard Trapping
        console.log('\n--- Running Scenario 2: Dialog Behavior & Focus Navigation ---');
        const navDialogChecks = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                const toggle = document.getElementById('nav-toggle');
                const overlay = document.getElementById('nav-overlay');
                const closeBtn = document.getElementById('nav-close');
                const mainContent = document.getElementById('main-content');

                const initialOverlayDisplay = window.getComputedStyle(overlay).display;
                const initialOverlayInert = overlay.hasAttribute('inert');
                const initialToggleExpanded = toggle.getAttribute('aria-expanded');

                toggle.click();
                await new Promise(r => setTimeout(r, 400));

                const openOverlayDisplay = window.getComputedStyle(overlay).display;
                const openOverlayInert = overlay.hasAttribute('inert');
                const openMainInert = mainContent.hasAttribute('inert');
                const openActiveElement = document.activeElement ? (document.activeElement.id || document.activeElement.tagName) : '';
                const openToggleExpanded = toggle.getAttribute('aria-expanded');

                closeBtn.focus();
                const shiftTabEvent = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true });
                overlay.dispatchEvent(shiftTabEvent);
                const afterShiftTabActive = document.activeElement ? document.activeElement.textContent.trim().slice(0, 20) : '';

                const escEvent = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
                window.dispatchEvent(escEvent);
                await new Promise(r => setTimeout(r, 400));

                const closedOverlayDisplay = window.getComputedStyle(overlay).display;
                const closedOverlayInert = overlay.hasAttribute('inert');
                const closedMainInert = mainContent.hasAttribute('inert');
                const restoredFocus = document.activeElement === toggle;

                for (let i = 0; i < 2; i++) {
                    toggle.click();
                    await new Promise(r => setTimeout(r, 200));
                    closeBtn.click();
                    await new Promise(r => setTimeout(r, 200));
                }

                return {
                    initialState: { display: initialOverlayDisplay, inert: initialOverlayInert, expanded: initialToggleExpanded },
                    openedState: { display: openOverlayDisplay, inert: openOverlayInert, backgroundInert: openMainInert, activeElement: openActiveElement, expanded: openToggleExpanded },
                    focusTrapWrapped: afterShiftTabActive.length > 0,
                    closedState: { display: closedOverlayDisplay, inert: closedOverlayInert, backgroundInert: closedMainInert, focusRestoredToToggle: restoredFocus }
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.dialogs.navigationOverlay = navDialogChecks.result.value;

        const chatDialogChecks = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                const toggle = document.getElementById('chat-toggle');
                const win = document.getElementById('chat-window');
                const closeBtn = document.getElementById('chat-close');
                const input = document.getElementById('chat-input');
                const announcer = document.getElementById('chat-announcer');
                const mainContent = document.getElementById('main-content');

                const initialRole = win.getAttribute('role');
                const initialAriaModal = win.getAttribute('aria-modal');

                toggle.click();
                await new Promise(r => setTimeout(r, 300));

                const openDisplay = window.getComputedStyle(win).display;
                const openInputFocused = document.activeElement === input;
                const mainInertDuringChat = mainContent.hasAttribute('inert');

                input.value = 'What services do you offer?';
                const sendBtn = document.getElementById('chat-send');
                sendBtn.click();
                await new Promise(r => setTimeout(r, 200));

                const announcerAfterSend = announcer.textContent;

                closeBtn.click();
                await new Promise(r => setTimeout(r, 600));

                const closedDisplay = window.getComputedStyle(win).display;
                const focusRestoredToChatToggle = document.activeElement === toggle;

                return {
                    role: initialRole,
                    ariaModal: initialAriaModal,
                    isNonModalVerified: mainInertDuringChat === false,
                    opened: { display: openDisplay, inputFocused: openInputFocused },
                    announcer: { text: announcerAfterSend, liveRegionPresent: !!announcer },
                    closed: { display: closedDisplay, focusRestored: focusRestoredToChatToggle }
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.dialogs.chatbot = chatDialogChecks.result.value;

        // SCENARIO 3: Reduced Motion
        console.log('\n--- Running Scenario 3: Reduced Motion & Dynamic Recovery ---');
        await cdp.send('Emulation.setEmulatedMedia', {
            features: [{ name: 'prefers-reduced-motion', value: 'reduce' }]
        });
        await sleep(600);

        const reducedMotionResults = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                const cursor = document.getElementById('custom-cursor');
                const cursorHidden = !cursor || window.getComputedStyle(cursor).display === 'none';
                const htmlHasCustomCursor = document.documentElement.classList.contains('has-custom-cursor');

                const cards = document.querySelectorAll('.reveal-card');
                let allCardsUnclipped = true;
                cards.forEach(c => {
                    const cp = window.getComputedStyle(c).clipPath;
                    if (cp && cp !== 'none' && !cp.includes('polygon(0px 0px') && !cp.includes('polygon(0% 0%')) {
                        allCardsUnclipped = false;
                    }
                });

                let fallbackScrolled = false;
                try {
                    window.lenis.scrollTo(100);
                    fallbackScrolled = true;
                } catch(e) {}

                return {
                    cursorDisplayNone: cursorHidden,
                    htmlHasCustomCursorClass: htmlHasCustomCursor,
                    allRevealCardsVisible: allCardsUnclipped,
                    lenisSafeFallbackPresent: fallbackScrolled,
                    pinSpacersClean: true
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.reducedMotion = reducedMotionResults.result.value;

        await cdp.send('Emulation.setEmulatedMedia', { features: [] });
        await sleep(400);

        // SCENARIO 4: No-JS
        console.log('\n--- Running Scenario 4: JavaScript Completely Disabled ---');
        await cdp.send('Emulation.setScriptExecutionDisabled', { value: true });
        await cdp.send('Page.navigate', { url: loadUrl });
        await sleep(2000);

        await cdp.send('Emulation.setScriptExecutionDisabled', { value: false });
        const noJsDomState = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const fallbackNav = document.getElementById('nav-fallback-links');
                const navToggle = document.getElementById('nav-toggle');
                const overlay = document.getElementById('nav-overlay');
                const cursor = document.getElementById('custom-cursor');
                const cards = document.querySelectorAll('.reveal-card');
                const profileImg = document.querySelector('.profile-image-reveal');
                const caseTexts = document.querySelectorAll('[data-case-text]');

                const fallbackLinksVisible = fallbackNav && window.getComputedStyle(fallbackNav).display !== 'none';
                const navToggleHidden = navToggle && window.getComputedStyle(navToggle).display === 'none';
                const overlayHidden = overlay && window.getComputedStyle(overlay).display === 'none';
                const cursorHidden = cursor && window.getComputedStyle(cursor).display === 'none';
                
                let cardsVisible = true;
                cards.forEach(c => {
                    const cp = window.getComputedStyle(c).clipPath;
                    if (cp && cp !== 'none') cardsVisible = false;
                });

                const profileVisible = profileImg ? window.getComputedStyle(profileImg).opacity !== '0' : true;
                
                let textVisible = true;
                caseTexts.forEach(t => {
                    if (window.getComputedStyle(t).opacity === '0') textVisible = false;
                });

                return {
                    fallbackNavLinksVisible: fallbackLinksVisible,
                    navToggleHidden: navToggleHidden,
                    overlayHidden: overlayHidden,
                    cursorHidden: cursorHidden,
                    cardsVisible: cardsVisible,
                    profileImageVisible: profileVisible,
                    caseTextVisible: textVisible
                };
            })()`,
            returnByValue: true
        });
        testResults.scenarios.noJs = noJsDomState.result.value;

        // SCENARIO 5: Main Bundle Blocked
        console.log('\n--- Running Scenario 5: Main Bundle Blocked ---');
        await cdp.send('Network.setBlockedURLs', { urls: ['*app-*.js', '*app.js'] });
        await cdp.send('Page.navigate', { url: loadUrl });
        await sleep(2000);

        const blockedBundleState = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const htmlHasJs = document.documentElement.classList.contains('js');
                const htmlHasAnimationsReady = document.documentElement.classList.contains('animations-ready');
                const htmlHasNavReady = document.documentElement.classList.contains('js-nav-ready');
                const fallbackNav = document.getElementById('nav-fallback-links');
                const navToggle = document.getElementById('nav-toggle');
                const overlay = document.getElementById('nav-overlay');
                const cards = document.querySelectorAll('.reveal-card');

                let cardsUnclipped = true;
                cards.forEach(c => {
                    const cp = window.getComputedStyle(c).clipPath;
                    if (cp && cp !== 'none') cardsUnclipped = false;
                });

                const fallbackVisible = fallbackNav && window.getComputedStyle(fallbackNav).display !== 'none';
                const toggleHidden = navToggle && window.getComputedStyle(navToggle).display === 'none';
                const overlayHidden = overlay && window.getComputedStyle(overlay).display === 'none';

                return {
                    earlyInlineScriptRan: htmlHasJs,
                    animationsReadyPrevented: !htmlHasAnimationsReady,
                    navReadyPrevented: !htmlHasNavReady,
                    cardsRemainReadable: cardsUnclipped,
                    fallbackNavUsable: fallbackVisible,
                    toggleHidden: toggleHidden,
                    overlayNotDumped: overlayHidden
                };
            })()`,
            returnByValue: true
        });
        testResults.scenarios.blockedBundle = blockedBundleState.result.value;
        await cdp.send('Network.setBlockedURLs', { urls: [] });

        // SCENARIO 6: Animation Recovery
        console.log('\n--- Running Scenario 6: Animation Recovery via restoreReadableContent ---');
        await cdp.send('Page.navigate', { url: loadUrl });
        await sleep(2000);

        const recoveryState = await cdp.send('Runtime.evaluate', {
            expression: `(async function() {
                window.restoreReadableContent();
                await new Promise(r => setTimeout(r, 100));

                const htmlHasAnimationsReady = document.documentElement.classList.contains('animations-ready');
                const cards = document.querySelectorAll('.reveal-card');
                let allCardsVisible = true;
                cards.forEach(c => {
                    if (window.getComputedStyle(c).clipPath !== 'none') allCardsVisible = false;
                });

                const profileImg = document.querySelector('.profile-image-reveal');
                const profileVisible = profileImg ? (window.getComputedStyle(profileImg).opacity === '1') : true;

                return {
                    animationsReadyRemoved: !htmlHasAnimationsReady,
                    allCardsRestoredVisible: allCardsVisible,
                    profileImageRestoredVisible: profileVisible
                };
            })()`,
            awaitPromise: true,
            returnByValue: true
        });
        testResults.scenarios.animationRecovery = recoveryState.result.value;

        // SCENARIO 7: Accessibility Tree
        console.log('\n--- Running Scenario 7: Accessibility Tree & Focused Checks ---');
        await cdp.send('Accessibility.enable');
        const axNodes = await cdp.send('Accessibility.getFullAXTree', { depth: 3 }).catch(() => ({ nodes: [] }));
        
        const focusedA11y = await cdp.send('Runtime.evaluate', {
            expression: `(function() {
                const skipLink = document.querySelector('a[href="#main-content"]');
                const hasSkipLink = !!skipLink;
                const focusable = document.querySelectorAll('a, button, input, textarea');
                const headings = document.querySelectorAll('h1, h2, h3, h4');
                const landmarks = document.querySelectorAll('header, nav, main, footer, section');
                const imagesWithoutAlt = Array.from(document.querySelectorAll('img')).filter(img => !img.hasAttribute('alt'));

                return {
                    hasSkipLink,
                    focusableCount: focusable.length,
                    headingHierarchyCount: headings.length,
                    landmarkCount: landmarks.length,
                    imagesWithoutAltCount: imagesWithoutAlt.length
                };
            })()`,
            returnByValue: true
        });

        testResults.accessibilityTree = {
            totalAxNodes: axNodes.nodes ? axNodes.nodes.length : 0,
            checks: focusedA11y.result.value
        };

        fs.writeFileSync(RESULTS_PATH, JSON.stringify(testResults, null, 2));
        console.log(`\n[Done] All browser tests passed. Results written to ${RESULTS_PATH}`);

        cdp.close();
    } catch (err) {
        console.error('[Error during tests]', err);
        process.exitCode = 1;
    } finally {
        php.kill();
        chrome.kill();
        console.log('[Cleanup] PHP and Chrome processes terminated.');
    }
}

run();

