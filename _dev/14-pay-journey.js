/**
 * The booking journey, in a browser, in every payment state.
 *
 * The states that involve no money are the ones most likely to break quietly,
 * because nobody thinks to test them: a salon with no API subscription is the
 * normal case, and a client must sail through it without ever being blocked.
 * So this runs the journey with payments OFF and with payments ON-but-
 * UNCONFIGURED every time, and adds the live-deposit journey automatically
 * when credentials are present in config.php.
 *
 *   node _dev/14-pay-journey.js
 *   PBS_BASE=http://localhost:8000/beautybooking node _dev/14-pay-journey.js
 */
const fs = require('fs');
const puppeteer = require('puppeteer-core');
const { execFileSync } = require('child_process');

const BASE = process.env.PBS_BASE || 'http://localhost/beautybooking';
const MYSQL = 'C:/xampp/mysql/bin/mysql.exe';
const DB = 'princess_beauty_salon';
const ROOT = 'c:/xampp/htdocs/beautybooking';
const CHROME = ['C:/Program Files/Google/Chrome/Application/chrome.exe',
                'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
                'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe']
               .find(p => fs.existsSync(p));

const sql = q => execFileSync(MYSQL, ['-u', 'root', DB, '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();
const wait = ms => new Promise(r => setTimeout(r, ms));
const threshold = () => parseFloat(sql("SELECT value FROM settings WHERE `key`='deposit_threshold'")) || 20000;

let pass = 0, fail = 0;
const ok = (n, c, d = '') => {
  if (c) { pass++; console.log(`ok    ${n}`); }
  else { fail++; console.log(`FAIL  ${n}${d ? '\n        ' + d : ''}`); }
};
const grp = g => console.log(`\n--- ${g} ---`);

/** Ask the app itself what state it is in — never assume from the settings. */
const appState = () => execFileSync('php',
  ['-r', "require 'includes/payments.php'; setting_forget(); echo payments_state();"],
  { encoding: 'utf8', cwd: ROOT }).trim();

function setPayments(enabled, requireDeposit) {
  sql(`UPDATE settings SET value='${enabled}' WHERE \`key\`='payments_enabled';
       UPDATE settings SET value='${requireDeposit}' WHERE \`key\`='payments_require_deposit';`);
}

/**
 * Wait until the page has stopped moving.
 *
 * The wizard smooth-scrolls to the top on every step change. A click issued
 * while that animation is running is aimed at coordinates the element has
 * already left, so it lands on whatever is underneath — on a phone viewport
 * that is the fixed bottom navigation, whose Book link reloads the page and
 * silently returns the wizard to step 1. Nothing was wrong with the app; the
 * test was racing its own scrolling.
 */
async function settle(page) {
  await page.waitForFunction(() => {
    if (window.__lastY === window.scrollY) return true;
    window.__lastY = window.scrollY;
    return false;
  }, { polling: 120, timeout: 8000 }).catch(() => {});
}

/**
 * Click a control the way a thumb would: wait for it, stop the page moving,
 * bring it into view without another animation, then click its real
 * coordinates. Coordinates rather than el.click() on purpose — a control
 * hidden under the fixed bottom navigation would still pass a DOM click and
 * fail for a real person, and this suite runs at phone width precisely to
 * catch that.
 */
async function tap(page, selectorOrHandle) {
  const el = typeof selectorOrHandle === 'string'
    ? await page.waitForSelector(selectorOrHandle, { visible: true, timeout: 15000 })
    : selectorOrHandle;
  await settle(page);
  await el.evaluate(e => e.scrollIntoView({ block: 'center', behavior: 'instant' }));
  await settle(page);
  const covered = await el.evaluate(e => {
    const r = e.getBoundingClientRect();
    const at = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return !(at && (at === e || e.contains(at) || at.contains(e)));
  });
  if (covered) {
    throw new Error('a control is not clickable at phone width — something overlays it');
  }
  await el.click();
}

/** Wait until the wizard is actually showing the given step, and is still. */
async function atStep(page, n) {
  await page.waitForFunction(step => {
    const a = document.querySelector('.wizard-step.active');
    return a && a.getAttribute('data-step') === String(step);
  }, { timeout: 15000 }, n);
  await settle(page);
}

/**
 * Walk the wizard as far as the review step, the way a person does.
 * Returns the price of the service chosen.
 *
 * Every step waits for the wizard to actually arrive before touching the next
 * control. Clicking Next immediately after choosing a stylist looked fine and
 * silently failed validation, leaving the walk on step 2 waiting forever for
 * date pills that were never going to render.
 */
async function walkToReview(page, name, phone) {
  await page.goto(BASE + '/public/book.php', { waitUntil: 'load' });
  await atStep(page, 1);

  /* Above the deposit threshold, but the SHORTEST such service: the priciest
     is a 150-minute bridal styling whose few long slots are often all taken,
     which made this suite fail for a reason unrelated to payments. */
  const svc = sql(`SELECT ref FROM services WHERE deleted_at IS NULL
                     AND price >= ${threshold()} ORDER BY duration_minutes ASC, price ASC LIMIT 1`);
  const price = parseFloat(sql(`SELECT price FROM services WHERE ref='${svc}'`));

  await tap(page, `[data-svc="${svc}"]`);
  await page.waitForFunction(ref =>
    document.querySelector(`[data-svc="${ref}"]`).classList.contains('selected') ||
    document.querySelector('#summaryTotal').textContent.replace(/\D/g, '') !== '0',
    { timeout: 5000 }, svc);
  await tap(page, '#btnNext');
  await atStep(page, 2);

  await tap(page, '[data-staff="any"]');
  await wait(200);
  await tap(page, '#btnNext');
  await atStep(page, 3);

  /* Choosing a stylist re-runs the availability query, so the strip is rebuilt
     after this step lands and a list of dates read too early is wrong — a pill
     open a moment ago can come back marked Full. Re-read on every attempt. */
  const tried = new Set();
  let picked = null;

  for (let attempt = 0; attempt < 12 && !picked; attempt++) {
    await page.waitForSelector('.date-pill:not(.disabled)', { timeout: 20000 });
    await wait(250);

    const open = await page.$$eval('.date-pill:not(.disabled)',
      els => els.map(e => e.getAttribute('data-date')));
    const next = open.reverse().find(d => !tried.has(d));
    if (!next) break;
    tried.add(next);

    const pill = await page.$(`.date-pill[data-date="${next}"]:not(.disabled)`);
    if (!pill) continue;
    await tap(page, pill);

    try {
      await page.waitForSelector('.slot:not(.taken)', { timeout: 8000 });
      picked = next;
    } catch { /* that day filled up; try another */ }
  }
  if (!picked) {
    throw new Error('no day in the booking window has a free slot for this service '
                    + `(tried ${[...tried].join(', ') || 'none'})`);
  }

  await tap(page, '.slot:not(.taken)');
  await wait(200);
  await tap(page, '#btnNext');
  await atStep(page, 4);

  await page.waitForSelector('#cName', { visible: true, timeout: 10000 });
  await settle(page);
  await page.type('#cName', name);
  await page.type('#cPhone', phone);
  await tap(page, '#btnNext');
  await atStep(page, 5);
  await wait(300);
  return price;
}

function cleanUp(name, phone) {
  const ids = sql(`SELECT a.id FROM appointments a JOIN clients c ON c.id=a.client_id
                    WHERE c.name='${name}'`).split(/\s+/).filter(Boolean);
  for (const id of ids) {
    sql(`DELETE FROM payments WHERE appointment_id=${id}`);
    sql(`DELETE FROM appointment_items WHERE appointment_id=${id}`);
    sql(`DELETE FROM appointments WHERE id=${id}`);
  }
  sql(`DELETE FROM clients WHERE name='${name}'
        AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.client_id = clients.id)`);
}

(async () => {
  if (!CHROME) { console.error('No Chrome/Edge found.'); process.exit(1); }

  const before = {
    enabled: sql(`SELECT value FROM settings WHERE \`key\`='payments_enabled'`),
    required: sql(`SELECT value FROM settings WHERE \`key\`='payments_require_deposit'`),
  };
  const baseline = parseInt(sql('SELECT COUNT(*) FROM appointments'), 10);

  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
                                           args: ['--no-sandbox'], protocolTimeout: 120000 });
  const page = await browser.newPage();
  /* A phone, because that is what almost every client will book on. */
  await page.setViewport({ width: 420, height: 900 });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  /* ================================================================ */
  grp('payments OFF — the client is never shown a payment');
  {
    setPayments(0, 0);
    ok('the app reports state off', appState() === 'off', appState());

    await walkToReview(page, 'PayOff Journey', '+237 677 10 20 30');
    ok('no payment card on the review step', await page.$('#payBlock') === null);
    ok('no payment method is offered', (await page.$$('.pay-method')).length === 0);
    const aside = await page.$eval('#summaryDeposit', el => el.textContent.trim());
    ok('no deposit line in the summary', aside === '', aside);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 20000 }),
      tap(page, '#btnConfirm'),
    ]);
    ok('confirming goes straight to the confirmation page',
       page.url().includes('confirmation.php'), page.url());

    const ref = (page.url().match(/ref=(PBS-[A-Z0-9]+)/) || [])[1];
    ok('the booking exists', !!ref, String(ref));
    ok('with no deposit owed',
       parseFloat(sql(`SELECT deposit_due FROM appointments WHERE ref='${ref}'`)) === 0);
    cleanUp('PayOff Journey', '+237 677 10 20 30');
  }

  /* ================================================================ */
  grp('payments ON but no API keys — shown, explained, never blocking');
  {
    setPayments(1, 1);
    const state = appState();
    const live = state === 'live';
    console.log(`      (config.php credentials ${live ? 'ARE' : 'are NOT'} present — state is ${state})`);

    if (!live) {
      ok('the app reports state unconfigured', state === 'unconfigured', state);

      await walkToReview(page, 'PayUnconf Journey', '+237 677 11 21 31');
      ok('the payment card IS shown', await page.$('#payBlock') !== null);

      const tiles = await page.$$eval('.pay-method', els => els.map(e => ({
        txt: e.textContent.replace(/\s+/g, ' ').trim(),
        off: e.classList.contains('is-off'),
      })));
      ok('MTN MoMo is listed', tiles.some(t => /MTN MoMo/.test(t.txt)), JSON.stringify(tiles));
      ok('Orange Money is listed', tiles.some(t => /Orange Money/.test(t.txt)));
      ok('both are marked not configured', tiles.length > 0 && tiles.every(t => t.off),
         JSON.stringify(tiles));

      const notice = await page.$eval('#payBlock', el => el.textContent.replace(/\s+/g, ' '));
      ok('it says payment is not connected', /not connected yet/i.test(notice), notice.slice(0, 140));
      ok('and tells the client to carry on', /settle at the salon/i.test(notice),
         notice.slice(0, 200));

      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 20000 }),
        tap(page, '#btnConfirm'),
      ]);
      ok('the booking still completes, with no payment step',
         page.url().includes('confirmation.php'), page.url());
      const ref = (page.url().match(/ref=(PBS-[A-Z0-9]+)/) || [])[1];
      ok('and nothing was charged',
         parseFloat(sql(`SELECT deposit_due FROM appointments WHERE ref='${ref}'`)) === 0);
      ok('no payment record was created',
         sql(`SELECT COUNT(*) FROM payments WHERE appointment_id =
               (SELECT id FROM appointments WHERE ref='${ref}')`) === '0');
      cleanUp('PayUnconf Journey', '+237 677 11 21 31');
    }
  }

  /* ================================================================ */
  grp('payments LIVE — the deposit step');
  {
    setPayments(1, 1);
    const state = appState();
    if (state !== 'live') {
      console.log('      SKIPPED: no provider credentials in includes/config.php.');
      console.log('      Put your MTN MoMo or Orange Money keys there and re-run to');
      console.log('      exercise the deposit step end to end.');
    } else {
      const price = await walkToReview(page, 'PayLive Journey', '+237 677 12 22 32');
      const expected = Math.round(price * 0.3);

      const intro = await page.$eval('#payIntro', el => el.textContent.trim());
      ok('the review states the deposit', /deposit of/i.test(intro), intro);

      const tiles = await page.$$eval('.pay-method', els => els.map(e => ({
        txt: e.textContent.replace(/\s+/g, ' ').trim(),
        off: e.classList.contains('is-off'),
      })));
      ok('at least one provider is selectable', tiles.some(t => !t.off), JSON.stringify(tiles));

        await tap(page, '#btnConfirm');
      await wait(3000);

      ok('the wizard moves to the deposit step',
         await page.$eval('[data-step="6"]', el => el.classList.contains('active')));
      ok('it did not navigate away', page.url().includes('book.php'), page.url());

      const ref = await page.$eval('#payPanel', el => (el.textContent.match(/PBS-\d+/) || [''])[0]);
      ok('the booking exists BEFORE any money is asked for', /^PBS-\d+$/.test(ref), ref);
      ok('and is stored as pending',
         sql(`SELECT status FROM appointments WHERE ref='${ref}'`) === 'pending');
      ok('with the deposit recorded as due',
         parseFloat(sql(`SELECT deposit_due FROM appointments WHERE ref='${ref}'`)) === expected,
         `${sql(`SELECT deposit_due FROM appointments WHERE ref='${ref}'`)} vs ${expected}`);
      ok('the stepper gained a Pay step',
         /Pay/.test(await page.$eval('#stepper', el => el.textContent)));
      ok('there is always a way out without paying', await page.$('#paySkip') !== null);

      await tap(page, '#payNow');
      await wait(7000);

      const status = await page.$eval('#payStatus', el => el.textContent.replace(/\s+/g, ' ').trim());
      ok('the client is told what happened', status.length > 0, status);
      ok('the booking survives whatever the provider said',
         sql(`SELECT status FROM appointments WHERE ref='${ref}'`) === 'pending');
      ok('the attempt is recorded either way',
         parseInt(sql(`SELECT COUNT(*) FROM payments WHERE appointment_id =
                        (SELECT id FROM appointments WHERE ref='${ref}')`), 10) === 1);

      const paid = parseFloat(sql(`SELECT deposit_paid FROM appointments WHERE ref='${ref}'`));
      const payStatus = sql(`SELECT status FROM payments WHERE appointment_id =
                              (SELECT id FROM appointments WHERE ref='${ref}')`);
      ok('deposit_paid agrees with the payment outcome',
         payStatus === 'succeeded' ? paid > 0 : paid === 0,
         `payment ${payStatus}, deposit_paid ${paid}`);

      if (payStatus !== 'succeeded') {
        ok('a failure tells the client their booking is safe', /still held/i.test(status), status);
        await tap(page, '#paySkip');
        await wait(2500);
        ok('and "pay at the salon" finishes the journey',
           page.url().includes('confirmation.php'), page.url());
      }
      cleanUp('PayLive Journey', '+237 677 12 22 32');
    }
  }

  /* ================================================================ */
  grp('teardown');
  setPayments(before.enabled, before.required);
  ok('payment settings restored',
     sql(`SELECT value FROM settings WHERE \`key\`='payments_enabled'`) === before.enabled,
     `payments_enabled=${sql(`SELECT value FROM settings WHERE \`key\`='payments_enabled'`)}`);
  ok('no test bookings left behind',
     parseInt(sql('SELECT COUNT(*) FROM appointments'), 10) === baseline,
     `${sql('SELECT COUNT(*) FROM appointments')} vs ${baseline}`);
  ok('no JavaScript errors in any journey', errors.length === 0, errors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  process.exit(fail ? 1 : 0);
})();
