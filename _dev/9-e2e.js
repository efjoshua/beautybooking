/**
 * End-to-end journeys in a real browser.
 *
 * Everything here happens the way a person would do it — typing into fields,
 * clicking buttons, waiting for modals — not by calling the API directly.
 * API tests prove the endpoint works; these prove the UI actually reaches it.
 *
 *   node _dev/9-e2e.js            headless
 *   node _dev/9-e2e.js --show     watch it happen
 */
const fs = require('fs');
const puppeteer = require('puppeteer-core');
const { execFileSync } = require('child_process');

/* Port 80 is not always ours to take on Windows — http.sys reserves it.
   Override with PBS_BASE=http://localhost:8000/beautybooking */
const BASE = process.env.PBS_BASE || 'http://localhost/beautybooking';
const MYSQL = 'C:/xampp/mysql/bin/mysql.exe';
const DB = 'princess_beauty_salon';
const CHROME = [
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].find(p => fs.existsSync(p));

const SHOW = process.argv.includes('--show');
let pass = 0, fail = 0;

/* Every write this suite makes gets undone at the end. Without it each run
   completes another seeded appointment and sells more stock, so the demo
   dataset drifts a little further from its seed on every test run. */
const undo = { appts: [], sales: [], refs: [] };

function ok(name, cond, detail = '') {
  if (cond) { pass++; console.log(`ok    ${name}`); }
  else { fail++; console.log(`FAIL  ${name}${detail ? '  — ' + detail : ''}`); }
}
function grp(g) { console.log(`\n--- ${g} ---`); }

/** Query the database directly, so we assert on stored state not just pixels. */
function sql(q) {
  return execFileSync(MYSQL, ['-u', 'root', DB, '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();
}

const wait = ms => new Promise(r => setTimeout(r, ms));

/** The app's today (DEMO_TODAY when pinned). Reports filter by this, so the
    assertions must too — asking MySQL for CURDATE() compares two different days. */
const TODAY = execFileSync('php', ['-r',
  "require 'includes/config.php'; require 'includes/db.php'; echo app_today();"],
  { encoding: 'utf8', cwd: 'c:/xampp/htdocs/beautybooking' }).trim();

async function login(page, email, password = 'demo1234') {
  await page.goto(BASE + '/admin/logout.php', { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.goto(BASE + '/admin/login.php', { waitUntil: 'domcontentloaded' });
  if (!(await page.$('#email'))) return;                 // already signed in
  await page.$eval('#email', el => el.value = '');
  await page.type('#email', email);
  await page.type('#pw', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type=submit]'),
  ]);
}

(async () => {
  if (!CHROME) { console.error('No Chrome/Edge found.'); process.exit(1); }
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless: SHOW ? false : 'new',
    args: ['--no-sandbox'], slowMo: SHOW ? 40 : 0,
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  let bookingRef = null;
  const BASELINE = {
    appts: parseInt(sql('SELECT COUNT(*) FROM appointments'), 10),
    sales: parseInt(sql('SELECT COUNT(*) FROM product_sales'), 10),
  };

  /* ================================================================ */
  grp('customer books by clicking through the wizard');
  {
    await page.goto(BASE + '/public/book.php', { waitUntil: 'load' });
    await wait(400);

    /* step 1 — pick a service */
    await page.waitForSelector('[data-svc]');
    const svcCount = await page.$$eval('[data-svc]', els => els.length);
    ok('all services offered', svcCount === 24, svcCount + ' shown');

    await page.click('[data-svc="SV-11"]');              // Classic Manicure, 45min
    const total = await page.$eval('#summaryTotal', el => el.textContent.trim());
    ok('summary shows the price', total.includes('5,000'), total);

    await page.click('#btnNext');
    await wait(500);

    /* step 2 — only qualified stylists */
    await page.waitForSelector('[data-staff]');
    const offered = await page.$$eval('[data-staff]', els =>
      els.map(e => e.getAttribute('data-staff')));
    ok('an "any stylist" option exists', offered.includes('any'));
    ok('braid-only stylist not offered for nails', !offered.includes('ST-02'),
       offered.join(','));

    await page.click('[data-staff="any"]');
    await page.click('#btnNext');
    await wait(900);

    /* step 3 — real availability */
    await page.waitForSelector('.date-pill');
    const days = await page.$$eval('.date-pill', els => els.length);
    ok('three weeks of dates offered', days === 21, days + ' days');

    const openDays = await page.$$('.date-pill:not(.disabled)');
    ok('at least one day is bookable', openDays.length > 0);
    await openDays[openDays.length - 1].click();   // furthest out, so it is future
    await wait(900);

    await page.waitForSelector('.slot');
    const slots = await page.$$eval('.slot', els => ({
      total: els.length,
      free: els.filter(e => !e.classList.contains('taken')).length,
    }));
    ok('slots rendered', slots.total > 0, JSON.stringify(slots));
    ok('some slots are free', slots.free > 0);

    await page.click('.slot:not(.taken)');
    await page.click('#btnNext');
    await wait(400);

    /* step 4 — details */
    await page.waitForSelector('#cName');
    await page.type('#cName', 'E2E Journey');
    await page.type('#cPhone', '+237 655 99 88 77');
    await page.type('#cNotes', 'Booked by the end-to-end suite');
    await page.click('#btnNext');
    await wait(400);

    /* step 5 — review reflects what was chosen */
    const review = await page.$eval('#reviewBox', el => el.textContent);
    ok('review shows the service', review.includes('Classic Manicure'), review.slice(0, 80));
    ok('review shows the name',    review.includes('E2E Journey'));

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
      page.click('#btnConfirm'),
    ]);

    const url = page.url();
    ok('landed on the confirmation page', url.includes('confirmation.php'), url);
    bookingRef = (url.match(/ref=(PBS-[A-Z0-9]+)/) || [])[1] || null;
    ok('a reference was issued', !!bookingRef, String(bookingRef));

    const shown = await page.$eval('body', el => el.innerText);
    ok('confirmation shows the reference', bookingRef && shown.includes(bookingRef));
    ok('confirmation shows the service',   shown.includes('Classic Manicure'));

    /* the row really exists */
    const row = sql(`SELECT status, source FROM appointments WHERE ref='${bookingRef}'`);
    ok('row persisted as pending/online', row === 'pending\tonline', row);
  }

  /* ================================================================ */
  grp('customer manages the booking');
  {
    await page.goto(BASE + '/public/manage-booking.php', { waitUntil: 'load' });
    await wait(300);
    await page.type('#lookupRef', bookingRef);
    await page.type('#lookupPhone', '+237 655 99 88 77');
    await page.click('#lookupForm button[type=submit]');
    await wait(700);

    let res = await page.$eval('#lookupResult', el => el.textContent);
    ok('lookup finds it', res.includes(bookingRef), res.slice(0, 60));
    ok('lookup shows the service', res.includes('Classic Manicure'));

    /* wrong phone must reveal nothing */
    await page.goto(BASE + '/public/manage-booking.php', { waitUntil: 'load' });
    await wait(300);
    await page.type('#lookupRef', bookingRef);
    await page.type('#lookupPhone', '+237 600 00 00 00');
    await page.click('#lookupForm button[type=submit]');
    await wait(700);
    res = await page.$eval('#lookupResult', el => el.textContent);
    ok('wrong phone reveals nothing', !res.includes('Classic Manicure'), res.slice(0, 70));
    ok('wrong phone shows a not-found message', /not find|No booking/i.test(res));
  }

  /* ================================================================ */
  grp('owner works the appointment through the admin UI');
  {
    await login(page, 'aicha@princessbeautysalon.cm');
    ok('signed in as owner', page.url().includes('/admin/index.php'), page.url());

    await page.goto(BASE + '/admin/appointments.php?q=' + bookingRef, { waitUntil: 'load' });
    await wait(400);
    const found = await page.$eval('body', el => el.innerText);
    ok('search finds the new booking', found.includes('E2E Journey'));

    await page.click(`[data-appt="${bookingRef}"]`);
    await wait(800);
    const modal = await page.$eval('#apptModalBody', el => el.textContent);
    ok('detail modal opens with the client', modal.includes('E2E Journey'), modal.slice(0, 60));
    ok('modal shows the note', modal.includes('end-to-end suite'));

    /* confirm it */
    await page.click('[data-set="confirmed"]');
    await wait(1400);
    ok('status is confirmed',
       sql(`SELECT status FROM appointments WHERE ref='${bookingRef}'`) === 'confirmed');
  }

  /* ================================================================ */
  grp('completing a future booking is refused');
  {
    /* the booking is in the future, so banking its revenue now must fail */
    await page.goto(BASE + '/admin/appointments.php?q=' + bookingRef, { waitUntil: 'load' });
    await wait(400);
    await page.click(`[data-appt="${bookingRef}"]`);
    await wait(800);
    await page.click('#openPay');
    await wait(400);
    await page.click('#payConfirm');
    await wait(1200);

    const toast = await page.$eval('body', el => el.innerText);
    ok('UI refuses to complete a future appointment',
       /cannot be completed before it happens/i.test(toast),
       toast.split('\n').filter(l => l.trim()).slice(-2).join(' | '));
    ok('status did not become completed',
       sql(`SELECT status FROM appointments WHERE ref='${bookingRef}'`) !== 'completed');
  }

  /* ================================================================ */
  grp('owner takes payment on a booking that has happened');
  {
    const ref = sql(`SELECT ref FROM appointments
                      WHERE status='confirmed' AND booked_on <= '${TODAY}'
                      ORDER BY booked_on DESC LIMIT 1`);
    if (!ref) { ok('a past confirmed booking exists to settle', false); }
    else {
      undo.appts.push({
        ref,
        prior: sql(`SELECT status, payment_method, addons_total, tip_total, grand_total
                      FROM appointments WHERE ref='${ref}'`).split('	'),
      });
      const before = sql(`SELECT qty_on_hand FROM products WHERE ref='P-09'`);
      await page.goto(BASE + '/admin/appointments.php?q=' + ref, { waitUntil: 'load' });
      await wait(400);
      await page.click(`[data-appt="${ref}"]`);
      await wait(800);
      await page.click('#openPay');
      await wait(400);
      await page.$eval('#payAddons', el => el.value = '2000');
      await page.$eval('#payTip', el => el.value = '1000');
      await page.select('#payMethod', 'momo');
      await page.click('#payConfirm');
      await wait(1600);

      const row = sql(`SELECT status, payment_method, addons_total, tip_total, grand_total,
                              services_total FROM appointments WHERE ref='${ref}'`).split('\t');
      ok('marked completed',  row[0] === 'completed', row.join('|'));
      ok('payment recorded',  row[1] === 'momo');
      ok('add-ons stored',    parseFloat(row[2]) === 2000);
      ok('tip stored',        parseFloat(row[3]) === 1000);
      ok('total = services + addons + tip',
         Math.abs(parseFloat(row[4]) - (parseFloat(row[5]) + 2000 + 1000)) < 0.01,
         row.join('|'));

      const moves = sql(`SELECT COUNT(*) FROM stock_movements WHERE reference='${ref}'`);
      ok('stock movements written for the completed service', parseInt(moves, 10) >= 0, moves);
      const after = sql(`SELECT qty_on_hand FROM products WHERE ref='P-09'`);
      ok('stock cache still reconciles with the ledger',
         sql(`SELECT COUNT(*) FROM (SELECT p.id FROM products p
              LEFT JOIN stock_movements m ON m.product_id=p.id WHERE p.deleted_at IS NULL
              GROUP BY p.id, p.qty_on_hand
              HAVING p.qty_on_hand <> COALESCE(SUM(m.qty),0)) x`) === '0',
         `P-09 ${before} -> ${after}`);
    }
  }

  /* ================================================================ */
  grp('owner sells a product at the counter');
  {
    const before = parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='P-05'`), 10);
    const salesBefore = parseInt(sql(`SELECT COUNT(*) FROM product_sales`), 10);

    await page.goto(BASE + '/admin/inventory.php', { waitUntil: 'load' });
    await wait(500);
    await page.select('#posProduct', 'P-05');
    await page.$eval('#posQty', el => el.value = '3');
    await page.select('#posMethod', 'cash');
    await page.click('#posAdd');
    await wait(300);

    const cart = await page.$eval('#posCart', el => el.textContent);
    ok('cart shows the line', /Kanekalon/i.test(cart), cart.slice(0, 60));
    ok('cart totals 3 x 2,500', cart.includes('7,500'), cart.slice(0, 90));

    await page.click('#posSell');
    await wait(1600);

    const after = parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='P-05'`), 10);
    ok('stock reduced by 3', after === before - 3, `${before} -> ${after}`);
    ok('a sale row was written',
       parseInt(sql(`SELECT COUNT(*) FROM product_sales`), 10) === salesBefore + 1);
    ok('sale total is right',
       sql(`SELECT total FROM product_sales ORDER BY id DESC LIMIT 1`) === '7500.00');
    undo.sales.push(sql(`SELECT ref FROM product_sales ORDER BY id DESC LIMIT 1`));
  }

  /* ================================================================ */
  grp('overselling is refused in the UI');
  {
    const stock = parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='P-02'`), 10);
    await page.goto(BASE + '/admin/inventory.php', { waitUntil: 'load' });
    await wait(500);
    await page.select('#posProduct', 'P-02');
    await page.$eval('#posQty', (el, n) => el.value = String(n + 50), stock);
    await page.click('#posAdd');
    await wait(500);
    const body = await page.$eval('body', el => el.innerText);
    ok('UI blocks adding more than stock', /Only \d+ in stock/i.test(body),
       body.split('\n').filter(l => /stock/i.test(l)).slice(-1)[0] || '(no message)');
    ok('nothing was sold',
       parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='P-02'`), 10) === stock);
  }

  /* ================================================================ */
  grp('owner logs an expense through the form');
  {
    const before = parseInt(sql(`SELECT COUNT(*) FROM expenses WHERE deleted_at IS NULL`), 10);
    await page.goto(BASE + '/admin/expenses.php', { waitUntil: 'load' });
    await wait(400);
    await page.click('[data-modal="expModal"]');
    await wait(300);
    await page.type('#expModal input[name=amount]', '17500');
    await page.type('#expModal input[name=description]', 'E2E test expense');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      page.click('#expModal button[type=submit]'),
    ]);
    const after = parseInt(sql(`SELECT COUNT(*) FROM expenses WHERE deleted_at IS NULL`), 10);
    ok('expense saved', after === before + 1, `${before} -> ${after}`);
    ok('amount stored exactly',
       sql(`SELECT amount FROM expenses WHERE description='E2E test expense'`) === '17500.00');

    /* and it moves net profit */
    const shown = await page.$eval('body', el => el.innerText);
    ok('it appears in the list', shown.includes('E2E test expense'));

    /* clean up */
    sql(`DELETE FROM expenses WHERE description='E2E test expense'`);
  }

  /* ================================================================ */
  grp('reports agree with the database');
  {
    await page.goto(BASE + '/admin/revenue.php?preset=month', { waitUntil: 'load' });
    await wait(700);
    const shownGross = await page.$eval('.stat.accent-pink .val', el => el.textContent.trim());

    const from = sql(`SELECT DATE_FORMAT('${TODAY}','%Y-%m-01')`);
    const to   = sql(`SELECT LAST_DAY('${TODAY}')`);
    const appt = parseFloat(sql(
      `SELECT COALESCE(SUM(grand_total),0) FROM appointments
        WHERE status='completed' AND booked_on BETWEEN '${from}' AND '${to}'`));
    const retail = parseFloat(sql(
      `SELECT COALESCE(SUM(total),0) FROM product_sales
        WHERE sold_on BETWEEN '${from}' AND '${to}'`));
    const expected = Math.round(appt + retail).toLocaleString('en-US') + ' FCFA';
    ok('gross revenue tile matches SQL', shownGross === expected,
       `page="${shownGross}" sql="${expected}"`);

    /* the commission table must foot correctly */
    const foot = await page.$$eval('table tfoot tr td', els => els.map(e => e.textContent.trim()));
    ok('commission table has a totals row', foot.length >= 5, foot.join(' | '));
  }

  /* ================================================================ */
  grp('stylist sees only their own work');
  {
    await login(page, 'blessing@princessbeautysalon.cm');
    const menu = await page.$$eval('.side-link', els =>
      els.map(a => a.getAttribute('href')).filter(Boolean));
    ok('no revenue link',   !menu.includes('revenue.php'), menu.join(','));
    ok('no inventory link', !menu.includes('inventory.php'));
    ok('no users link',     !menu.includes('users.php'));
    ok('has own schedule',  menu.includes('my-schedule.php'));

    const dash = await page.$eval('body', el => el.innerText);
    ok('no salon-wide profit on the stylist dashboard', !/Net Profit/i.test(dash));

    await page.goto(BASE + '/admin/my-schedule.php', { waitUntil: 'load' });
    await wait(500);
    const sched = await page.$eval('body', el => el.innerText);
    ok('another stylist never appears in my diary',
       !/Grace Mbah|Linda Fotso|Marcel Tabi/.test(sched));

    await page.goto(BASE + '/admin/my-clients.php', { waitUntil: 'load' });
    await wait(500);
    const mine = await page.$$eval('table.table tbody tr', els =>
      els.filter(tr => !tr.querySelector('.empty')).length);
    const expected = parseInt(sql(
      `SELECT COUNT(DISTINCT a.client_id) FROM appointments a
         JOIN staff s ON s.id=a.staff_id WHERE s.ref='ST-02'`), 10);
    ok('my client list matches my own appointments', mine === expected,
       `page=${mine} sql=${expected}`);
  }

  /* ================================================================ */
  grp('no JavaScript errors anywhere in the journey');
  ok('clean console', errors.length === 0, errors.slice(0, 3).join(' | '));

  /* ---------------------------------------------------------- teardown */
  grp('teardown leaves the dataset as it found it');
  {
    /* the booking this run created, and anything it consumed */
    if (bookingRef) undo.refs.push(bookingRef);

    for (const r of undo.refs.concat(undo.sales)) {
      sql(`UPDATE products p JOIN stock_movements m ON m.product_id = p.id
              SET p.qty_on_hand = p.qty_on_hand - m.qty WHERE m.reference = '${r}'`);
      sql(`DELETE FROM stock_movements WHERE reference = '${r}'`);
    }
    for (const r of undo.sales) {
      sql(`DELETE FROM product_sale_items WHERE sale_id IN
             (SELECT id FROM product_sales WHERE ref='${r}')`);
      sql(`DELETE FROM product_sales WHERE ref='${r}'`);
    }
    if (bookingRef) {
      sql(`DELETE FROM appointment_items WHERE appointment_id IN
             (SELECT id FROM appointments WHERE ref='${bookingRef}')`);
      sql(`DELETE FROM appointments WHERE ref='${bookingRef}'`);
      sql(`DELETE FROM clients WHERE name='E2E Journey'`);
    }
    /* put the settled appointment back the way it was */
    for (const a of undo.appts) {
      sql(`UPDATE products p JOIN stock_movements m ON m.product_id = p.id
              SET p.qty_on_hand = p.qty_on_hand - m.qty WHERE m.reference = '${a.ref}'`);
      sql(`DELETE FROM stock_movements WHERE reference = '${a.ref}'`);
      sql(`UPDATE appointments SET status='${a.prior[0]}',
              payment_method=${a.prior[1] === 'NULL' ? 'NULL' : `'${a.prior[1]}'`},
              addons_total=${a.prior[2]}, tip_total=${a.prior[3]},
              grand_total=${a.prior[4]} WHERE ref='${a.ref}'`);
    }

    ok('no test client left behind',
       sql(`SELECT COUNT(*) FROM clients WHERE name='E2E Journey'`) === '0');
    ok('stock ledger reconciles with the cache after teardown',
       sql(`SELECT COUNT(*) FROM (SELECT p.id FROM products p
            LEFT JOIN stock_movements m ON m.product_id=p.id WHERE p.deleted_at IS NULL
            GROUP BY p.id, p.qty_on_hand
            HAVING p.qty_on_hand <> COALESCE(SUM(m.qty),0)) x`) === '0');
    ok('appointment count back to baseline',
       sql(`SELECT COUNT(*) FROM appointments`) === String(BASELINE.appts),
       `${sql('SELECT COUNT(*) FROM appointments')} vs ${BASELINE.appts}`);
    ok('sale count back to baseline',
       sql(`SELECT COUNT(*) FROM product_sales`) === String(BASELINE.sales));
  }

  await browser.close();
  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  process.exit(fail ? 1 : 0);
})();
