/**
 * Visits every GET route in a real browser and reports what only a browser can
 * see.
 *
 * The suite already asks whether a route responds -- RouteSmokeTest does that
 * for admin, player and guest, and fails on a Blade artifact leaking into the
 * output. This asks the questions a server-side assertion cannot: did the page
 * MOUNT, did anything throw, does the markup hold together, and is the shell's
 * furniture where it should be.
 *
 * It replaces ~/ftap-shots/sweep.py, which patched three layout files and
 * appended temporary routes to routes/web.php, restoring them from .sweepbak
 * copies afterwards. That mechanism broke when layouts/app.blade.php was
 * deleted, and it is how probe instrumentation once reached a commit. This
 * patches nothing: it drives Chrome over CDP and signs in through the real
 * login form against a scratch database.
 *
 * Driven by bin/sweep.sh, which owns the database, the server and the browser.
 * Reads the plan on stdin -- see bin/sweep-seed.php for its shape.
 */

const CDP = process.env.SWEEP_CDP ?? 'http://127.0.0.1:9333';
const BASE = process.env.SWEEP_BASE ?? 'http://127.0.0.1:8123';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const plan = JSON.parse(await new Promise((resolve) => {
    let raw = '';
    process.stdin.on('data', (c) => { raw += c; });
    process.stdin.on('end', () => resolve(raw));
}));

/*
 * The browser TAB, not whatever /json/list happens to put first.
 *
 * Taking [0] attached to a Chrome extension's background page, and every step
 * after that then ran against chrome-extension://…/background.html -- which
 * reported, accurately and uselessly, that the login form had no email field.
 */
async function attach() {
    for (let i = 0; i < 60; i++) {
        try {
            const targets = await (await fetch(`${CDP}/json/list`)).json();
            const tab = targets.find((t) => t.type === 'page'
                && !t.url.startsWith('chrome-extension://')
                && !t.url.startsWith('devtools://'));

            if (tab?.webSocketDebuggerUrl) return tab.webSocketDebuggerUrl;
        } catch { /* not up yet */ }
        await sleep(250);
    }
    throw new Error(`no page target at ${CDP}`);
}

const ws = new WebSocket(await attach());
await new Promise((r) => ws.addEventListener('open', r));

let id = 0;
const pending = new Map();
let loadWaiters = [];
let pageErrors = [];
let documentStatus = null;

ws.addEventListener('message', (event) => {
    const m = JSON.parse(event.data);
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
    if (m.method === 'Page.loadEventFired') { loadWaiters.forEach((r) => r()); loadWaiters = []; }
    // The status of the PAGE, not of the assets on it. A player asking for an
    // admin route gets a correct 403, and an error page has no shell furniture
    // -- so without this the sweep reported a missing footer and two missing
    // live regions for every route authorization was doing its job on.
    if (m.method === 'Network.responseReceived' && m.params?.type === 'Document') {
        documentStatus = m.params.response?.status ?? null;
    }
    // Anything the page complains about, from either channel.
    if (m.method === 'Runtime.exceptionThrown') {
        pageErrors.push(m.params?.exceptionDetails?.exception?.description
            ?? m.params?.exceptionDetails?.text ?? 'exception');
    }
    if (m.method === 'Log.entryAdded' && m.params?.entry?.level === 'error') {
        pageErrors.push(m.params.entry.text);
    }
});

const send = (method, params = {}) => new Promise((resolve) => {
    const n = ++id;
    pending.set(n, resolve);
    ws.send(JSON.stringify({ id: n, method, params }));
});

const evaluate = async (expression) => {
    const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (r.result?.exceptionDetails) {
        throw new Error(r.result.exceptionDetails.exception?.description ?? 'evaluate failed');
    }
    return r.result?.result?.value;
};

/*
 * Waits for the LOAD EVENT, not for a readyState or a URL.
 *
 * readyState is still "complete" for the previous page for a moment after
 * navigate, so waiting on it runs the next step against the page you just
 * left. Matching on the URL instead breaks whenever a navigation redirects --
 * /login sends a signed-in visitor elsewhere, which is exactly what half these
 * routes do.
 */
const goto = async (url) => {
    const loaded = new Promise((r) => loadWaiters.push(r));
    await send('Page.navigate', { url });
    await Promise.race([loaded, sleep(20000)]);

    // The load event alone is not enough to build on. It can arrive for a
    // frame other than the one asked for, and on the FIRST navigation of a
    // session it can be missed entirely -- which ran the login script against
    // about:blank and reported a missing email field.
    await waitFor('document.readyState === "complete"', 'the document to finish loading');

    // Inertia mounts after load, so give the client a moment to draw.
    await sleep(450);
};

/** Polls until an expression is true, rather than sleeping and hoping. */
async function waitFor(expression, what, timeoutMs = 10000) {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        if (await evaluate(expression)) return true;
        await sleep(150);
    }

    // Says where it actually was. "Timed out waiting for the login form" with
    // no page named sends you looking in the wrong place.
    const where = await evaluate('location.href + " | " + document.title').catch(() => 'unknown');

    throw new Error(`timed out waiting for ${what} — page was ${where}`);
}

await send('Page.enable');
await send('Runtime.enable');
await send('Network.enable');
await send('Log.enable');

/** What only a browser can answer, asked of whatever is on screen. */
const INSPECT = `(() => {
    const ids = Array.from(document.querySelectorAll('[id]')).map(e => e.id);
    const duplicates = ids.filter((v, i) => ids.indexOf(v) !== i);
    const app = document.getElementById('app');

    return {
        title: document.title,
        // An Inertia page is empty until it mounts; a Blade page has no #app.
        mounted: app === null ? 'blade' : (app.children.length > 0 ? 'yes' : 'NO'),
        nested_anchors: document.querySelectorAll('a a').length,
        duplicate_ids: Array.from(new Set(duplicates)),
        overflows: document.documentElement.scrollWidth > window.innerWidth,
        // The shell's furniture. Both live regions must be present on every
        // page, empty, BEFORE any message arrives -- a region created together
        // with its content is unreliably announced.
        polite: document.querySelectorAll('[aria-live="polite"]').length,
        assertive: document.querySelectorAll('[aria-live="assertive"]').length,
        // Nothing should be on screen unprompted.
        stray_toasts: document.querySelectorAll('.toast').length,
        footer: document.querySelectorAll('.site-footer, footer').length,
        // Which shell drew this: the dashboard, the public site, or the guest
        // panel the auth screens use.
        shell: document.querySelector('.guest') ? 'guest'
            : (document.querySelector('.shell') ? 'app' : 'public'),
    };
})()`;

async function signIn(email) {
    await send('Network.clearBrowserCookies');

    if (email === null) return 'guest';

    await goto(`${BASE}/login`);
    await waitFor('!!document.querySelector("input[name=email]")', 'the login form');
    await evaluate(`(() => {
        document.querySelector('input[name=email]').value = ${JSON.stringify(email)};
        document.querySelector('input[name=password]').value = ${JSON.stringify(plan.password)};
        document.querySelector('form').submit();
        return 1;
    })()`);
    await waitFor('location.pathname !== "/login"', `the sign-in as ${email} to land`, 15000);
    await waitFor('document.readyState === "complete"', 'the page after signing in');

    const where = await evaluate('location.pathname');
    if (where === '/login') throw new Error(`could not sign in as ${email}`);

    return email;
}

const failures = [];
let visited = 0;

for (const [label, email] of [['admin', plan.admin], ['player', plan.player], ['guest', null]]) {
    await signIn(email);

    for (const { name, url } of plan.urls) {
        pageErrors = [];
        documentStatus = null;

        try {
            await goto(BASE + url);
        } catch (e) {
            failures.push(`${label} ${url} (${name}) — navigation failed: ${e.message}`);
            continue;
        }

        visited++;
        let seen;

        try {
            seen = await evaluate(INSPECT);
        } catch (e) {
            failures.push(`${label} ${url} (${name}) — could not inspect: ${e.message}`);
            continue;
        }

        const note = (what) => failures.push(`${label} ${url} (${name}) — ${what}`);

        // A server error is always a finding; RouteSmokeTest asserts the same
        // thing, and agreeing with it is the point.
        if (documentStatus !== null && documentStatus >= 500) {
            note(`HTTP ${documentStatus}`);
            continue;
        }

        /*
         * A 4xx is authorization working. A player asking for /users is
         * REFUSED, correctly, and the error page it gets has no shell and no
         * furniture -- so there is nothing further to ask of it.
         */
        if (documentStatus !== null && documentStatus >= 400) continue;

        // A redirect to /login or /dashboard is a correct answer for a role
        // that may not be here, so the checks below are about whatever page we
        // ended on rather than about the one we asked for.
        if (seen.mounted === 'NO') note('the Inertia page never mounted');
        if (!seen.title) note('no <title>');
        if (seen.nested_anchors > 0) note(`${seen.nested_anchors} nested anchor(s)`);
        if (seen.duplicate_ids.length > 0) note(`duplicate id(s): ${seen.duplicate_ids.join(', ')}`);
        if (seen.overflows) note('the page scrolls horizontally');
        if (seen.stray_toasts > 0) note(`${seen.stray_toasts} toast(s) with nothing to announce`);
        if (pageErrors.length > 0) note(`console: ${pageErrors.slice(0, 2).join(' | ')}`);

        /*
         * The guest shell has neither, deliberately: somebody on a sign-in
         * screen has somewhere to be and nowhere else to go, and nothing
         * flashes to those screens. Asked of the shell rather than of a list of
         * URLs, so a new auth screen is covered without being remembered.
         */
        if (seen.shell === 'guest') continue;

        if (seen.polite !== 1) note(`${seen.polite} polite live region(s), expected 1`);
        if (seen.assertive !== 1) note(`${seen.assertive} assertive live region(s), expected 1`);
        if (seen.footer === 0) note('no footer');
    }
}

if (plan.unbound.length > 0) {
    failures.push(`no fixture for: ${plan.unbound.join(', ')} — add it to Tests\\Support\\RouteFixtures`);
}

console.log(`swept ${visited} pages across 3 roles (${plan.urls.length} routes each)`);

if (failures.length > 0) {
    console.log(`\n${failures.length} finding(s):`);
    failures.forEach((f) => console.log(`  ${f}`));
    process.exit(1);
}

console.log('no findings');
process.exit(0);
