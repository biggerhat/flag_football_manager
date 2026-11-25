/*
 * End-to-end check of the main flows in a real browser: accounts and teams,
 * invitations and roles, editing, search, roster, game day, undo, sharing,
 * and PDF downloads.
 *
 *   npm run build   (or keep `composer dev` running) so the pages have assets
 *   npm run e2e
 *
 * It creates a throwaway database and server, so it never touches your data.
 * Set CHROME to a Chromium or headless-shell binary if Playwright has none installed.
 */
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = fs.mkdtempSync(path.join(os.tmpdir(), 'coach-e2e-'));
const env = {
    ...process.env,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(OUT, 'e2e.sqlite'),
    COACH_AUTO_LOGIN: 'false',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    APP_ENV: 'local',
};

const freePort = () =>
    new Promise((resolve) => {
        const server = net.createServer().listen(0, '127.0.0.1', () => {
            const { port } = server.address();
            server.close(() => resolve(port));
        });
    });

fs.writeFileSync(env.DB_DATABASE, '');
execFileSync('php', ['artisan', 'migrate', '--force'], {
    cwd: ROOT,
    env,
    stdio: 'ignore',
});

const port = await freePort();
const BASE = `http://127.0.0.1:${port}`;
const server = spawn(
    'php',
    [
        '-S',
        `127.0.0.1:${port}`,
        path.join(
            ROOT,
            'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        ),
    ],
    { cwd: path.join(ROOT, 'public'), env, stdio: 'ignore' },
);

for (let i = 0; i < 50; i++) {
    try {
        if ((await fetch(`${BASE}/login`)).ok) {
            break;
        }
    } catch {
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
}

const hot = path.join(ROOT, 'public/hot');

if (fs.existsSync(hot)) {
    try {
        await fetch(fs.readFileSync(hot, 'utf8').trim());
    } catch {
        console.error(
            'public/hot points at a Vite server that is not running. Start it (npm run dev) or delete public/hot and run npm run build.',
        );
        server.kill();
        process.exit(2);
    }
}

const browser = await chromium.launch({
    executablePath: process.env.CHROME || undefined,
});
const b = browser;
const errors = [];
let failures = 0;
const watch = (page, tag) => {
    page.on('pageerror', (e) => errors.push(`${tag}: ${e}`));
    page.on(
        'console',
        (m) =>
            m.type() === 'error' && errors.push(`${tag} console: ${m.text()}`),
    );
    page.on('dialog', (d) => {
        errors.push(`${tag} NATIVE DIALOG ${d.message()}`);
        d.dismiss();
    });
};
const ok = (cond, msg) => {
    console.log(cond ? 'PASS' : 'FAIL', msg);

    if (!cond) {
        failures++;
    }
};

try {
    // --- owner registers
    const ownerCtx = await b.newContext({
        viewport: { width: 1400, height: 950 },
    });
    const owner = await ownerCtx.newPage();
    watch(owner, 'owner');
    await owner.goto(`${BASE}/register`);
    await owner.fill('#name', 'Olivia Owner');
    await owner.fill('#email', 'olivia@example.com');
    await owner.fill('#password', 'password-1234');
    await owner.fill('#password_confirmation', 'password-1234');
    await owner.click('button[type=submit]');
    await owner.waitForURL('**/dashboard');
    ok(
        (await owner.locator('[data-test=team-switcher]').count()) === 1,
        'team switcher after register',
    );
    ok(
        (await owner.locator('[data-test=team-switcher]').innerText()).includes(
            'Olivia Owner',
        ),
        'personal team named after owner',
    );

    // --- create a play
    await owner.goto(`${BASE}/plays/create`);
    await owner.waitForSelector('svg');
    await owner.fill('#name', 'Quick slant');
    await owner.getByRole('button', { name: 'Save play' }).click();
    await owner.waitForURL(/plays\/\d+\/edit/);
    ok(true, 'play saved');
    // header buttons
    ok(
        (await owner
            .getByRole('link', { name: 'Back to playbook' })
            .count()) === 1,
        'Back to playbook button in header',
    );
    ok(
        (await owner.getByRole('link', { name: 'New play' }).count()) === 1,
        'New play button in header',
    );

    // keyboard: tab onto a player selects it
    await owner.locator('g[data-kind=player]').first().focus();
    ok(
        (await owner
            .locator('g[data-kind=player]')
            .first()
            .getAttribute('aria-pressed')) === 'true',
        'focusing a player selects it',
    );

    // --- invite a viewer
    await owner.goto(`${BASE}/team`);
    await owner.fill('#invite-email', 'vic@example.com');
    await owner.selectOption('#invite-role', 'viewer');
    await owner.getByRole('button', { name: 'Invite' }).click();
    await owner.getByRole('button', { name: 'Copy link' }).waitFor();
    await ownerCtx
        .grantPermissions(['clipboard-read', 'clipboard-write'], {
            origin: BASE,
        })
        .catch(() => {});
    await owner.getByRole('button', { name: 'Copy link' }).click();
    let link = await owner
        .evaluate(() => navigator.clipboard.readText())
        .catch(() => null);
    if (!link) {
        link = await owner
            .locator('input[aria-label="Invitation link"]')
            .inputValue();
    }
    ok(/\/invitations\//.test(link ?? ''), `invitation link: ${link}`);

    // --- viewer follows the link as a guest, registers, joins
    const viewerCtx = await b.newContext({
        viewport: { width: 1400, height: 950 },
    });
    const viewer = await viewerCtx.newPage();
    watch(viewer, 'viewer');
    await viewer.goto(link);
    ok(
        (await viewer.locator('body').innerText()).includes('as a viewer'),
        'invitation page names the role',
    );
    await viewer.getByRole('link', { name: 'Create account' }).click();
    await viewer.fill('#name', 'Vic Viewer');
    await viewer.fill('#email', 'vic@example.com');
    await viewer.fill('#password', 'password-1234');
    await viewer.fill('#password_confirmation', 'password-1234');
    await viewer.click('button[type=submit]');
    await viewer.waitForURL('**/invitations/**');
    await viewer.getByRole('button', { name: /^Join/ }).click();
    await viewer.waitForURL('**/dashboard');
    ok(
        (
            await viewer.locator('[data-test=team-switcher]').innerText()
        ).includes('Olivia Owner'),
        'viewer is now in the owner team',
    );
    ok(
        (await viewer.getByText('view-only access').count()) === 1,
        'view-only banner shown',
    );

    // viewer sees the play but cannot change things
    await viewer.goto(`${BASE}/playbooks`);
    await viewer.getByRole('link', { name: 'Open' }).first().click();
    await viewer.waitForURL(/playbooks\/\d+/);
    ok(
        (await viewer.getByRole('link', { name: 'New play' }).count()) === 0,
        'viewer has no New play',
    );
    ok(
        (await viewer.getByRole('link', { name: 'View' }).count()) >= 1,
        'viewer sees View instead of Edit',
    );
    await viewer.getByRole('link', { name: 'View' }).first().click();
    await viewer.waitForURL(/plays\/\d+\/edit/);
    ok(
        (await viewer.getByRole('button', { name: 'Save play' }).count()) === 0,
        'viewer has no Save',
    );

    // viewer switcher lists teams including their own
    await viewer.locator('[data-test=team-switcher]').click();
    ok(
        (await viewer.getByRole('menuitem').count()) >= 2,
        'viewer has two teams in switcher',
    );
    await viewer.keyboard.press('Escape');

    // --- owner: global search
    await owner.goto(`${BASE}/dashboard`);
    await owner.keyboard.press('Control+k');
    await owner.getByRole('textbox', { name: 'Search' }).fill('slant');
    await owner.getByRole('option', { name: /Quick slant/ }).waitFor();
    ok(true, 'search finds the play');
    await owner.keyboard.press('Enter');
    await owner.waitForURL(/plays\/\d+\/edit/);

    // --- roster
    await owner.goto(`${BASE}/players`);
    await owner.fill('input[aria-label="Player name"]', 'Marcus');
    await owner.fill('input[aria-label="Jersey number"]', '7');
    await owner.fill('input[aria-label="Position"] >> nth=0', 'X');
    await owner.getByRole('button', { name: 'Add player' }).click();
    await owner.waitForSelector('input[aria-label="Name"]');
    ok(
        (await owner
            .locator('input[aria-label="Name"]')
            .first()
            .inputValue()) === 'Marcus',
        'player added to roster',
    );

    // --- game plan + game day
    await owner.goto(`${BASE}/gameplans/create`);
    await owner.fill('#name', 'Week 1');
    await owner.fill('#opponent', 'Hawks');
    await owner.getByTitle('Add to game plan').first().click();
    await owner.getByRole('button', { name: 'Save game plan' }).click();
    await owner.waitForURL(/gameplans\/\d+\/edit/);
    await owner.getByRole('link', { name: 'Game day' }).click();
    await owner.waitForURL(/\/play$/);
    await owner.getByRole('button', { name: 'Gain', exact: true }).click();
    await owner.getByText('Calls this game').waitFor();
    ok(true, 'game day call logged');
    await owner.locator('input[type=number]').first().fill('14');
    await owner.locator('input[type=number]').nth(1).fill('7');
    await owner.getByRole('button', { name: 'Save score' }).click();
    await owner.goto(`${BASE}/gameplans`);
    ok((await owner.getByText(/W 14/).count()) === 1, 'game result shown');

    // --- calendar
    await owner.goto(`${BASE}/calendar`);
    await owner.waitForSelector('h1');
    ok(true, 'calendar renders');

    // --- delete + undo
    await owner.goto(`${BASE}/playbooks`);
    await owner.getByRole('link', { name: 'Open' }).first().click();
    await owner.waitForURL(/playbooks\/\d+/);
    await owner.getByRole('button', { name: 'Delete' }).first().click();
    await owner
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete' })
        .click();
    await owner.getByRole('button', { name: 'Undo' }).click();
    await owner.waitForTimeout(800);
    ok(
        (await owner.getByText('Quick slant').count()) >= 1,
        'deleted play restored by Undo',
    );

    // --- packet + share
    await owner.getByRole('button', { name: 'Share' }).click();
    await owner.waitForTimeout(600);
    await owner.getByRole('button', { name: 'Sharing on' }).click();
    const shareLink = await owner
        .getByRole('dialog')
        .locator('input')
        .inputValue();
    ok(/\/share\//.test(shareLink), `share link ${shareLink}`);
    await owner
        .getByRole('dialog')
        .getByRole('button', { name: 'Cancel' })
        .click();
    const guestCtx = await b.newContext();
    const guest = await guestCtx.newPage();
    watch(guest, 'guest');
    await guest.goto(shareLink);
    ok(
        (await guest.locator('h1').innerText()).length > 0 &&
            (await guest.getByText('Contents').count()) === 1,
        'public packet renders for a guest',
    );

    // ---- second session: later flows, logged in again as the owner
    const ctx = await b.newContext({ viewport: { width: 1400, height: 950 } });
    const page = await ctx.newPage();
    watch(page, 'second');
    await page.goto(`${BASE}/login`);
    await page.fill('#email', 'olivia@example.com');
    await page.fill('#password', 'password-1234');
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');

    // team page
    await page.goto(`${BASE}/team`);
    await page.screenshot({ path: `${OUT}/t1-team.png` });

    // drill with diagram
    await page.goto(`${BASE}/drills/create`);
    await page.fill('#name', 'Weave');
    await page.getByRole('button', { name: 'Add a diagram' }).click();
    ok(
        (await page.locator('g[data-kind=player]').count()) === 2,
        'drill diagram starts with two players',
    );
    await page.getByRole('button', { name: 'Defender' }).click();
    ok(
        (await page.locator('g[data-kind=player]').count()) === 3,
        'defender added',
    );
    await page.screenshot({ path: `${OUT}/t2-drill.png` });
    await page.getByRole('button', { name: 'Save drill' }).click();
    await page.waitForURL('**/drills');
    await page.getByRole('link', { name: 'Edit' }).last().click();
    await page.waitForSelector('g[data-kind=player]');
    ok(
        (await page.locator('g[data-kind=player]').count()) === 3,
        'drill diagram persisted',
    );

    // play editor overlay
    await page.goto(`${BASE}/plays/create`);
    await page.waitForSelector('#overlay');
    const before = await page.locator('svg [opacity="0.5"] circle').count();
    await page.selectOption('#overlay', { index: 1 });
    const after = await page.locator('svg [opacity="0.5"] circle').count();
    ok(
        before === 0 && after > 0,
        `defense overlay draws ${after} ghost players`,
    );
    await page.screenshot({ path: `${OUT}/t3-overlay.png` });

    // practice attendance
    await page.goto(`${BASE}/practices/create`);
    ok(
        (await page.getByText(/Attendance \(0\/1\)/).count()) === 1,
        'attendance list appears with the roster',
    );
    await page.getByRole('button', { name: 'Everyone here' }).click();
    ok(
        (await page.getByText(/Attendance \(1\/1\)/).count()) === 1,
        'everyone here marks the roster',
    );

    // print with player names
    await page.goto(`${BASE}/playbooks`);
    await page.getByRole('link', { name: 'Open' }).first().click();
    await page.waitForURL(/playbooks\/\d+/);
    const href = await page
        .getByRole('link', { name: /Call sheet/ })
        .getAttribute('href');
    const ids = new URL(href, BASE).searchParams.get('ids');
    await page.goto(`${BASE}/print/plays?ids=${ids}&style=sheets`);
    await page.getByLabel('Player names').check();
    const text = await page.locator('body').innerText();
    ok(/X \(#7 Marcus\)/.test(text), 'print shows "X (#7 Marcus)"');

    // game day + calendar screenshots
    await page.goto(`${BASE}/gameplans`);
    await page.getByRole('link', { name: 'Game day' }).first().click();
    await page.waitForURL(/\/play$/);
    await page.screenshot({ path: `${OUT}/t4-gameday.png` });
    await page.goto(`${BASE}/calendar`);
    await page.screenshot({ path: `${OUT}/t5-calendar.png` });
    await page.goto(`${BASE}/players`);
    await page.screenshot({ path: `${OUT}/t6-roster.png` });

    // --- PDF download of a play sheet
    await page.goto(`${BASE}/playbooks`);
    await page.getByRole('link', { name: 'Open' }).first().click();
    await page.waitForURL(/playbooks\/\d+/);
    const sheetHref = await page
        .getByRole('link', { name: /Call sheet/ })
        .getAttribute('href');
    const pdfIds = new URL(sheetHref, BASE).searchParams.get('ids');
    const pdf = await ctx.request.get(
        `${BASE}/pdf?to=${encodeURIComponent(`/print/plays?ids=${pdfIds}&style=sheets`)}`,
    );
    ok(
        pdf.ok() && (await pdf.body()).subarray(0, 4).toString() === '%PDF',
        'PDF download returns a PDF',
    );
} finally {
    console.log('errors', JSON.stringify(errors));
    await browser.close();
    server.kill();
    fs.rmSync(OUT, { recursive: true, force: true });
}

if (errors.length) {
    failures++;
}

console.log(failures ? `\n${failures} check(s) failed` : '\nAll checks passed');
process.exit(failures ? 1 : 0);
