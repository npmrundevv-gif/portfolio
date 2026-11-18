import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../resources/js/components/chat.js', import.meta.url), 'utf8');

class Element extends EventTarget {
    style = { top: '' };
    children = [];
    attributes = new Map();

    constructor() {
        super();
        const classes = new Set();
        this.classList = {
            add: (name) => classes.add(name),
            remove: (name) => classes.delete(name),
            contains: (name) => classes.has(name),
        };
    }

    setAttribute(name, value) { this.attributes.set(name, value); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
}

async function createChat({ width = 390, reducedMotion = false, withLenis = true, bodyTop = '' } = {}) {
    const elements = Object.fromEntries(
        ['messages', 'chat-input', 'chat-window', 'chat-toggle', 'chat-close', 'chat-send']
            .map((id) => [id, new Element()]),
    );
    const body = new Element();
    body.style.top = bodyTop;
    const mediaQueries = new Map();
    const scrollRestores = [];
    const lenis = {
        isStopped: false,
        stops: 0,
        starts: 0,
        stop() { this.isStopped = true; this.stops++; },
        start() { this.isStopped = false; this.starts++; },
    };
    const window = Object.assign(new EventTarget(), {
        innerWidth: width,
        scrollY: 720,
        lenis: withLenis ? lenis : undefined,
        scrollTo({ top }) { this.scrollY = top; scrollRestores.push(top); },
        matchMedia(query) {
            if (!mediaQueries.has(query)) {
                const media = new EventTarget();
                media.matches = query.includes('prefers-reduced-motion') ? reducedMotion : this.innerWidth >= 640;
                mediaQueries.set(query, media);
            }
            return mediaQueries.get(query);
        },
    });
    const context = vm.createContext({
        window,
        document: { body, getElementById: (id) => elements[id] ?? null },
        setTimeout: () => 0,
    });
    const animate = (_element, options) => options.onComplete?.();
    const dependencies = {
        marked: { marked: { setOptions() {} } },
        dompurify: { default: { addHook() {} } },
        gsap: { default: { set: animate, to: animate, from: animate } },
    };
    const module = new vm.SourceTextModule(source, { context });
    await module.link((specifier) => {
        const exports = dependencies[specifier];
        return new vm.SyntheticModule(Object.keys(exports), function () {
            for (const [name, value] of Object.entries(exports)) this.setExport(name, value);
        }, { context });
    });
    await module.evaluate();
    module.namespace.initChat('/chat/message', 'test-csrf', 'Test Portfolio');

    return {
        body, elements, window, lenis, scrollRestores,
        open: () => elements['chat-toggle'].dispatchEvent(new Event('click')),
        close: () => elements['chat-close'].dispatchEvent(new Event('click')),
        escape: () => window.dispatchEvent(Object.assign(new Event('keydown', { cancelable: true }), { key: 'Escape' })),
        resize(nextWidth) {
            window.innerWidth = nextWidth;
            for (const [query, media] of mediaQueries) {
                if (query.includes('prefers-reduced-motion')) continue;
                const matches = nextWidth >= 640;
                if (media.matches !== matches) {
                    media.matches = matches;
                    media.dispatchEvent(new Event('change'));
                }
            }
            window.dispatchEvent(new Event('resize'));
        },
    };
}

for (const reducedMotion of [false, true]) {
    test(`mobile to desktop releases the lock while chat stays open (reduced motion: ${reducedMotion})`, async () => {
        const chat = await createChat({ reducedMotion });
        chat.open();
        assert.equal(chat.body.classList.contains('chat-open'), true);
        assert.equal(chat.lenis.isStopped, true);
        chat.window.scrollY = 0; // A fixed body can reset the viewport scroll offset.
        chat.resize(1024);
        assert.equal(chat.body.classList.contains('chat-open'), false);
        assert.equal(chat.body.style.top, '');
        assert.equal(chat.lenis.isStopped, false);
        assert.equal(chat.window.scrollY, 720);
        assert.equal(chat.elements['chat-window'].getAttribute('aria-hidden'), 'false');
        chat.close();
        assert.equal(chat.lenis.starts, 1);
        assert.equal(chat.elements['chat-toggle'].getAttribute('aria-expanded'), 'false');
    });
}

test('closing after a width change unlocks even before a breakpoint event arrives', async () => {
    const chat = await createChat();
    chat.open();
    chat.window.scrollY = 0;
    chat.window.innerWidth = 1024;
    chat.close();
    assert.equal(chat.body.classList.contains('chat-open'), false);
    assert.equal(chat.body.style.top, '');
    assert.equal(chat.lenis.isStopped, false);
    assert.equal(chat.window.scrollY, 720);
});

test('desktop to mobile acquires the lock and Escape releases it', async () => {
    const chat = await createChat({ width: 640 });
    chat.open();
    assert.equal(chat.lenis.stops, 0);
    chat.resize(639);
    assert.equal(chat.body.classList.contains('chat-open'), true);
    assert.equal(chat.body.style.top, '-720px');
    assert.equal(chat.lenis.isStopped, true);
    chat.window.scrollY = 0;
    chat.escape();
    assert.equal(chat.body.classList.contains('chat-open'), false);
    assert.equal(chat.lenis.isStopped, false);
    assert.equal(chat.window.scrollY, 720);
    assert.equal(chat.elements['chat-window'].getAttribute('aria-hidden'), 'true');
});

test('resizes within mobile retain the saved position and restore the previous body offset', async () => {
    const chat = await createChat({ bodyTop: '12px' });
    chat.open();
    chat.window.scrollY = 0;
    chat.resize(520);
    chat.resize(639);
    chat.close();
    assert.equal(chat.body.style.top, '12px');
    assert.equal(chat.lenis.stops, 1);
    assert.equal(chat.lenis.starts, 1);
    assert.deepEqual(chat.scrollRestores, [720]);
});

test('repeated breakpoint crossings restore the position saved for each mobile visit', async () => {
    const chat = await createChat({ width: 639 });
    chat.open();
    chat.window.scrollY = 0;
    chat.resize(640);
    chat.window.scrollY = 850;
    chat.resize(639);
    chat.window.scrollY = 0;
    chat.resize(640);
    chat.resize(1280);
    chat.close();
    assert.equal(chat.body.classList.contains('chat-open'), false);
    assert.equal(chat.lenis.stops, 2);
    assert.equal(chat.lenis.starts, 2);
    assert.deepEqual(chat.scrollRestores, [720, 850]);
});

test('desktop-only chat and closed-chat resizes leave scrolling unchanged', async () => {
    const chat = await createChat({ width: 1280, bodyTop: '15px' });
    chat.lenis.isStopped = true;
    chat.open();
    chat.close();
    chat.resize(390);
    assert.equal(chat.body.classList.contains('chat-open'), false);
    assert.equal(chat.body.style.top, '15px');
    assert.equal(chat.lenis.isStopped, true);
    assert.equal(chat.lenis.stops, 0);
    assert.equal(chat.lenis.starts, 0);
    assert.deepEqual(chat.scrollRestores, []);
});

test('native scrolling is restored when Lenis is unavailable', async () => {
    const chat = await createChat({ withLenis: false });
    chat.open();
    chat.window.scrollY = 0;
    chat.resize(1024);
    chat.close();
    assert.equal(chat.body.classList.contains('chat-open'), false);
    assert.equal(chat.body.style.top, '');
    assert.equal(chat.window.scrollY, 720);
});
