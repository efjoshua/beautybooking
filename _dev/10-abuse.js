/**
 * Abuse probe — attacks the running app the way an attacker would.
 *
 * Every check here is an ATTACK that must FAIL. A check passes when the app
 * refuses. Where a refusal could be faked by a broken endpoint (a 500 also
 * "fails" the attack), the probe asserts the specific status and that the
 * response body contains no leaked data.
 *
 *   node _dev/10-abuse.js
 */
const { execFileSync } = require('child_process');

/* Port 80 is not always ours to take on Windows — http.sys reserves it.
   Override with PBS_BASE=http://localhost:8000/beautybooking */
const BASE = process.env.PBS_BASE || 'http://localhost/beautybooking';
const MYSQL = 'C:/xampp/mysql/bin/mysql.exe';
const DB = 'princess_beauty_salon';

let pass = 0, fail = 0;
const ok = (name, cond, detail = '') => {
  if (cond) { pass++; console.log(`ok    ${name}`); }
  else { fail++; console.log(`FAIL  ${name}${detail ? '\n        ' + detail : ''}`); }
};
const grp = g => console.log(`\n--- ${g} ---`);
const sql = q => execFileSync(MYSQL, ['-u', 'root', DB, '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

/* ------------------------------------------------------------ tiny client */
/** A browser-ish session: keeps cookies, can read the CSRF token off a page. */
function session() {
  const jar = new Map();
  const s = {
    async req(path, opts = {}) {
      const headers = Object.assign({}, opts.headers);
      if (jar.size) headers.Cookie = [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
      const res = await fetch(BASE + path, { ...opts, headers, redirect: 'manual' });
      for (const c of (res.headers.getSetCookie?.() || [])) {
        const [kv] = c.split(';'); const i = kv.indexOf('=');
        jar.set(kv.slice(0, i).trim(), kv.slice(i + 1).trim());
      }
      const body = await res.text();
      return { status: res.status, body, location: res.headers.get('location') || '' };
    },
    async get(p) { return s.req(p); },
    async post(p, fields, useToken = true) {
      const form = new URLSearchParams();
      for (const [k, v] of Object.entries(fields)) form.append(k, v);
      if (useToken && s.csrf) form.append('_csrf', s.csrf);
      return s.req(p, {
        method: 'POST', body: form.toString(),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      });
    },
    /** Pull a fresh CSRF token for this session. */
    async token(from = '/public/contact.php') {
      const r = await s.get(from);
      s.csrf = (r.body.match(/window\.PBS_CSRF\s*=\s*"([^"]+)"/) || [])[1] || null;
      return s.csrf;
    },
    async login(email, password = 'demo1234') {
      const page = await s.get('/admin/login.php');
      const tok = (page.body.match(/name="_csrf" value="([^"]+)"/) || [])[1];
      const r = await s.post('/admin/login.php', { email, password, _csrf: tok }, false);
      await s.token('/admin/index.php');
      return r;
    },
  };
  return s;
}

/**
 * Load the diary in a real browser, open the modal for `ref`, and report
 * whether the stored payload actually ran. Static HTML checks cannot answer
 * this: the modal is built by JavaScript from a JSON response, so the escaping
 * that matters happens client-side, after the page has been served.
 */
async function executes(ref) {
  const fs = require('fs');
  const puppeteer = require('puppeteer-core');
  const chrome = ['C:/Program Files/Google/Chrome/Application/chrome.exe',
                  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
                  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe']
                 .find(p => fs.existsSync(p));
  if (!chrome) return 'no browser available';
  const browser = await puppeteer.launch({ executablePath: chrome, headless: 'new',
                                           args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    let alerted = false;
    page.on('dialog', async d => { alerted = true; await d.dismiss(); });
    await page.goto(BASE + '/admin/login.php', { waitUntil: 'domcontentloaded' });
    if (await page.$('#email')) {
      await page.type('#email', 'aicha@princessbeautysalon.cm');
      await page.type('#pw', 'demo1234');
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    }
    await page.goto(BASE + '/admin/appointments.php?q=' + ref, { waitUntil: 'load' });
    await new Promise(r => setTimeout(r, 500));
    const row = await page.$(`[data-appt="${ref}"]`);
    if (row) { await row.click(); await new Promise(r => setTimeout(r, 900)); }
    const pwned = await page.evaluate(() => window.__pwned);
    return alerted ? 'alert() fired' : (pwned === undefined ? false : pwned);
  } finally {
    await browser.close();
  }
}

/** Does this body carry data that should never have been served? */
const leaks = (body, needles) => needles.filter(n => body.includes(n));

(async () => {
  /* Facts to attack with, taken from the live database. */
  const [ownerRef, stylistRef] = ['ST-01', 'ST-02'];
  const otherAppt = sql(`SELECT a.ref FROM appointments a JOIN staff s ON s.id=a.staff_id
                          WHERE s.ref='${ownerRef}' AND a.status<>'cancelled' LIMIT 1`);
  const ownAppt = sql(`SELECT a.ref FROM appointments a JOIN staff s ON s.id=a.staff_id
                        WHERE s.ref='${stylistRef}' LIMIT 1`);
  const otherApptPhone = sql(`SELECT c.phone FROM appointments a JOIN clients c ON c.id=a.client_id
                               WHERE a.ref='${otherAppt}'`);
  const unservedClient = sql(`SELECT c.ref FROM clients c WHERE c.deleted_at IS NULL
     AND NOT EXISTS (SELECT 1 FROM appointments a JOIN staff s ON s.id=a.staff_id
                      WHERE a.client_id=c.id AND s.ref='${stylistRef}') LIMIT 1`);
  const unservedName = sql(`SELECT name FROM clients WHERE ref='${unservedClient}'`);
  const anyBooking = sql(`SELECT ref FROM appointments WHERE status IN ('pending','confirmed') LIMIT 1`);

  /* ================================================================== */
  grp('unauthenticated access to admin data');
  {
    const s = session();
    for (const [label, path] of [
      ['appointment detail', `/api/appointment.php?ref=${otherAppt}`],
      ['client profile',     `/api/client.php?ref=${unservedClient}`],
    ]) {
      const r = await s.get(path);
      ok(`anon cannot read ${label}`, r.status === 401 || r.status === 403,
         `HTTP ${r.status}: ${r.body.slice(0, 120)}`);
      ok(`anon ${label} response leaks nothing`,
         leaks(r.body, [otherApptPhone, unservedName].filter(Boolean)).length === 0,
         r.body.slice(0, 160));
    }

    for (const page of ['index.php', 'revenue.php', 'clients.php', 'inventory.php',
                        'users.php', 'appointments.php', 'expenses.php', 'settings.php']) {
      const r = await s.get('/admin/' + page);
      ok(`anon is bounced from admin/${page}`,
         r.status === 302 && r.location.includes('login'),
         `HTTP ${r.status} → ${r.location}`);
    }
  }

  /* ================================================================== */
  grp('IDOR — a stylist reaching for what is not theirs');
  {
    const s = session();
    await s.login('blessing@princessbeautysalon.cm');
    ok('stylist signed in', !!s.csrf);

    /* Stylists hold no 'appointments' permission, so the diary API refuses
       them outright — including their own rows, which they read through
       my-schedule.php instead. The ownership rule underneath is exercised by
       the receptionist checks below and by _dev/11-scoping.js. */
    const own = await s.get(`/api/appointment.php?ref=${ownAppt}`);
    ok('stylist is refused the diary API entirely', own.status === 403,
       `HTTP ${own.status}: ${own.body.slice(0, 120)}`);

    const other = await s.get(`/api/appointment.php?ref=${otherAppt}`);
    ok('stylist cannot read another stylist\'s appointment', other.status === 403,
       `HTTP ${other.status}: ${other.body.slice(0, 120)}`);
    ok('and the refusal leaks no client phone',
       !other.body.includes(otherApptPhone), other.body.slice(0, 160));

    const cl = await s.get(`/api/client.php?ref=${unservedClient}`);
    ok('stylist cannot open a client they never served', cl.status === 403,
       `HTTP ${cl.status}: ${cl.body.slice(0, 120)}`);
    ok('and that refusal leaks no name or notes',
       !cl.body.includes(unservedName), cl.body.slice(0, 160));

    /* writes, not just reads */
    const w = await s.post('/api/appointment.php',
      { ref: otherAppt, action: 'status', status: 'cancelled' });
    ok('stylist cannot cancel another stylist\'s appointment', w.status === 403,
       `HTTP ${w.status}: ${w.body.slice(0, 120)}`);
    ok('the appointment really was not cancelled',
       sql(`SELECT status FROM appointments WHERE ref='${otherAppt}'`) !== 'cancelled');

    const pay = await s.post('/api/appointment.php',
      { ref: otherAppt, action: 'payment', method: 'cash', addons: '0', tip: '0' });
    ok('stylist cannot take payment on another\'s appointment', pay.status === 403,
       `HTTP ${pay.status}`);
  }

  /* ================================================================== */
  grp('the front desk can still do its job');
  {
    /* Reception holds 'appointments' but not 'revenue', and has no chair.
       An ownership check written as "not allowed to see revenue ⇒ only your
       own rows" refused her every appointment in the salon. */
    const s = session();
    await s.login('reception@princessbeautysalon.cm');
    ok('receptionist reaches the dashboard', !!s.csrf);

    const page = await s.get('/admin/appointments.php');
    ok('receptionist can load the diary', page.status === 200, `HTTP ${page.status}`);

    const detail = await s.get(`/api/appointment.php?ref=${otherAppt}`);
    ok('receptionist can open any appointment', detail.status === 200,
       `HTTP ${detail.status}: ${detail.body.slice(0, 120)}`);

    const prior = sql(`SELECT status FROM appointments WHERE ref='${anyBooking}'`);
    const w = await s.post('/api/appointment.php',
      { ref: anyBooking, action: 'status', status: 'confirmed' });
    ok('receptionist can confirm a booking', w.status === 200,
       `HTTP ${w.status}: ${w.body.slice(0, 120)}`);
    sql(`UPDATE appointments SET status='${prior}' WHERE ref='${anyBooking}'`);

    /* but she still must not reach the money */
    const rev = await s.get('/admin/revenue.php');
    ok('receptionist is still kept out of revenue',
       rev.status === 403 || rev.status === 302, `HTTP ${rev.status}`);
    const inv = await s.get('/admin/expenses.php');
    ok('receptionist is still kept out of expenses',
       inv.status === 403 || inv.status === 302, `HTTP ${inv.status}`);
  }

  /* ================================================================== */
  grp('privilege escalation');
  {
    const s = session();
    await s.login('blessing@princessbeautysalon.cm');

    const inv = await s.post('/api/stock.php',
      { action: 'sell', lines: JSON.stringify([{ ref: 'P-01', qty: 1 }]), method: 'cash' });
    ok('stylist cannot sell stock (no inventory permission)', inv.status === 403,
       `HTTP ${inv.status}: ${inv.body.slice(0, 120)}`);

    for (const page of ['revenue.php', 'inventory.php', 'users.php', 'settings.php', 'expenses.php']) {
      const r = await s.get('/admin/' + page);
      ok(`stylist is refused admin/${page}`, r.status === 403 || r.status === 302,
         `HTTP ${r.status} → ${r.location}`);
      ok(`admin/${page} refusal serves no report data`,
         !/Net Profit|Gross revenue|Commission owed/i.test(r.body), r.body.slice(0, 100));
    }

    /* mass-assignment: promote myself by POSTing a role I was never offered */
    const before = sql(`SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id
                         WHERE u.email='blessing@princessbeautysalon.cm'`);
    const esc = await s.post('/admin/users.php',
      { action: 'save', id: sql(`SELECT id FROM users WHERE email='blessing@princessbeautysalon.cm'`),
        role_id: sql(`SELECT id FROM roles WHERE name='owner'`), name: 'Blessing' });
    const after = sql(`SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id
                        WHERE u.email='blessing@princessbeautysalon.cm'`);
    ok('stylist cannot promote themselves to owner', after === before,
       `${before} → ${after} (HTTP ${esc.status})`);
  }

  /* ================================================================== */
  grp('CSRF');
  {
    const s = session();
    await s.login('aicha@princessbeautysalon.cm');
    const good = s.csrf;

    const none = await s.post('/api/appointment.php',
      { ref: anyBooking, action: 'status', status: 'cancelled' }, false);
    ok('write with no CSRF token is refused', none.status === 403,
       `HTTP ${none.status}: ${none.body.slice(0, 120)}`);

    s.csrf = 'forged-' + 'a'.repeat(40);
    const forged = await s.post('/api/appointment.php',
      { ref: anyBooking, action: 'status', status: 'cancelled' });
    ok('write with a forged CSRF token is refused', forged.status === 403,
       `HTTP ${forged.status}`);

    /* a token minted for a DIFFERENT session must not work in this one */
    const other = session();
    await other.token();
    s.csrf = other.csrf;
    const crossed = await s.post('/api/appointment.php',
      { ref: anyBooking, action: 'status', status: 'cancelled' });
    ok('another session\'s CSRF token is refused', crossed.status === 403,
       `HTTP ${crossed.status}`);

    ok('after all that, the booking is untouched',
       sql(`SELECT status FROM appointments WHERE ref='${anyBooking}'`) !== 'cancelled');

    /* and the real token still works — otherwise the checks above prove nothing */
    s.csrf = good;
    const real = await s.post('/api/appointment.php',
      { ref: anyBooking, action: 'status', status: 'confirmed' });
    ok('the genuine token IS accepted (control)', real.status === 200,
       `HTTP ${real.status}: ${real.body.slice(0, 120)}`);
  }

  /* ================================================================== */
  grp('SQL injection');
  {
    const s = session();
    await s.login('aicha@princessbeautysalon.cm');
    const payloads = [
      "' OR '1'='1",
      "PBS-1000' UNION SELECT password_hash,1,1 FROM users-- ",
      "'; DROP TABLE appointments-- ",
      "1 OR 1=1",
      "\\' OR 1=1 -- ",
      "%27%20OR%201%3D1",
    ];
    const apptsBefore = sql('SELECT COUNT(*) FROM appointments');

    for (const p of payloads) {
      const r = await s.get('/api/appointment.php?ref=' + encodeURIComponent(p));
      ok(`injection rejected cleanly: ${p.slice(0, 28)}`,
         r.status === 404 && !/SQLSTATE|SQL syntax|PDOException|Fatal error/i.test(r.body),
         `HTTP ${r.status}: ${r.body.slice(0, 140)}`);

      const list = await s.get('/admin/appointments.php?q=' + encodeURIComponent(p));
      ok(`list search survives: ${p.slice(0, 28)}`,
         list.status === 200 && !/SQLSTATE|SQL syntax|PDOException|Fatal error/i.test(list.body),
         `HTTP ${list.status}`);
      ok(`no password hash reached the page: ${p.slice(0, 20)}`,
         !/\$2y\$\d\d\$/.test(list.body));
    }
    ok('appointments table still exists and is intact',
       sql('SELECT COUNT(*) FROM appointments') === apptsBefore);
  }

  /* ================================================================== */
  grp('stored XSS');
  {
    /* Escaping is an OUTPUT concern, so the payload is stored verbatim and
       every place that renders it is checked. Searching the HTML for
       "onerror=alert" proves nothing — that substring survives escaping
       intact. Only an unescaped "<img" or "<script" is an actual hole. */
    const XSS = '<script>window.__pwned=1</script><img src=x onerror=alert(1)>';
    const raw = b => /<script>window\.__pwned/.test(b) || /<img src=x onerror/.test(b);

    const s = session();
    await s.token('/public/contact.php');
    const c = await s.post('/api/contact.php', {
      name: 'Attacker ' + XSS, phone: '+237600000001',
      subject: XSS, message: 'Payload: ' + XSS,
    });
    ok('contact message accepted (so the render can be attacked)', c.status === 200,
       `HTTP ${c.status}: ${c.body.slice(0, 120)}`);
    ok('payload stored verbatim (escaping belongs at output, not input)',
       sql(`SELECT COUNT(*) FROM contact_messages WHERE body LIKE '%onerror%'`) !== '0');

    const admin = session();
    await admin.login('aicha@princessbeautysalon.cm');

    const inbox = await admin.get('/admin/notifications.php');
    ok('the inbox page exists', inbox.status === 200, `HTTP ${inbox.status}`);
    ok('inbox contains the payload', /onerror/.test(inbox.body),
       'if absent, this test is checking nothing');
    ok('inbox renders it escaped, not live', !raw(inbox.body),
       (inbox.body.match(/.{0,70}onerror.{0,40}/) || [''])[0]);

    /* the same payload through a booking, which surfaces in more places */
    const b = session();
    await b.token('/public/book.php');
    const svc = sql(`SELECT ref FROM services WHERE deleted_at IS NULL LIMIT 1`);
    const day = sql(`SELECT booked_on FROM appointments WHERE status='pending'
                      ORDER BY booked_on DESC LIMIT 1`);
    const bk = await b.post('/api/bookings.php', {
      services: svc, staff: 'any', date: day, time: '09:00',
      name: 'XSS' + XSS, phone: '+237600000002', notes: XSS,
    });

    let xssRef = null;
    if (bk.status === 200) {
      xssRef = JSON.parse(bk.body).ref;
      for (const [label, path] of [
        ['diary list',   '/admin/appointments.php?q=' + encodeURIComponent('XSS')],
        ['client list',  '/admin/clients.php?q=' + encodeURIComponent('XSS')],
        ['dashboard',    '/admin/index.php'],
      ]) {
        const r = await admin.get(path);
        ok(`${label} renders the payload escaped`, !raw(r.body),
           (r.body.match(/.{0,70}(<img src=x|<script>window).{0,40}/) || [''])[0]);
      }
      ok('diary list actually contained the payload (test is not vacuous)',
         (await admin.get('/admin/appointments.php?q=' + encodeURIComponent('XSS'))).body
           .includes('onerror'));

      /* The JSON API legitimately returns the raw string — JSON is not HTML.
         What matters is whether the page that consumes it executes the thing. */
      const browserSafe = await executes(xssRef);
      ok('opening the appointment modal does not execute the payload',
         browserSafe === false, `window.__pwned was ${browserSafe}`);
    } else {
      ok('booking with an XSS name failed for a stated reason, not a crash',
         bk.status === 422, `HTTP ${bk.status}: ${bk.body.slice(0, 140)}`);
    }

    sql(`DELETE FROM contact_messages WHERE phone='+237600000001'`);
    if (xssRef) {
      sql(`DELETE FROM appointment_items WHERE appointment_id IN
             (SELECT id FROM appointments WHERE ref='${xssRef}')`);
      sql(`DELETE FROM appointments WHERE ref='${xssRef}'`);
    }
    sql(`DELETE FROM clients WHERE phone='+237600000002'`);
  }

  /* ================================================================== */
  grp('mass assignment through the public booking endpoint');
  {
    const s = session();
    await s.token('/public/book.php');
    const svc = sql(`SELECT ref FROM services WHERE deleted_at IS NULL ORDER BY price DESC LIMIT 1`);
    const price = sql(`SELECT price FROM services WHERE ref='${svc}'`);
    const day = sql(`SELECT booked_on FROM appointments WHERE status='pending'
                      ORDER BY booked_on DESC LIMIT 1`);

    const r = await s.post('/api/bookings.php', {
      services: svc, staff: 'any', date: day, time: '09:00',
      name: 'Mass Assign', phone: '+237600000003',
      /* fields a customer must never be able to set */
      status: 'completed', grand_total: '0', services_total: '0',
      tip_total: '999999', payment_method: 'cash', source: 'walk-in',
      commission_pct: '100', id: '1', ref: 'PBS-0001',
    });

    if (r.status === 200) {
      const ref = JSON.parse(r.body).ref;
      const row = sql(`SELECT status, source, grand_total, services_total, tip_total,
                              payment_method, ref FROM appointments WHERE ref='${ref}'`).split('\t');
      ok('status could not be forced to completed', row[0] === 'pending', row.join('|'));
      ok('source could not be forged',              row[1] === 'online', row[1]);
      ok('total came from the price list, not the request',
         Math.abs(parseFloat(row[2]) - parseFloat(price)) < 0.01,
         `posted 0, stored ${row[2]}, price list ${price}`);
      ok('tip could not be injected',               parseFloat(row[4]) === 0, row[4]);
      ok('payment method could not be pre-set',     !row[5] || row[5] === 'unpaid' || row[5] === 'NULL', row[5]);
      ok('reference was issued by the server',      row[6] !== 'PBS-0001', row[6]);

      sql(`DELETE FROM appointment_items WHERE appointment_id IN
             (SELECT id FROM appointments WHERE ref='${ref}')`);
      sql(`DELETE FROM appointments WHERE ref='${ref}'`);
      sql(`DELETE FROM clients WHERE phone='+237600000003'`);
    } else {
      ok('booking rejected for a legitimate reason, not a crash',
         r.status === 422, `HTTP ${r.status}: ${r.body.slice(0, 140)}`);
    }
  }

  /* ================================================================== */
  grp('price and quantity tampering at the counter');
  {
    const s = session();
    await s.login('aicha@princessbeautysalon.cm');
    const p = sql(`SELECT ref FROM products WHERE deleted_at IS NULL AND qty_on_hand > 5 LIMIT 1`);
    const realPrice = parseFloat(sql(`SELECT retail_price FROM products WHERE ref='${p}'`));
    const stock = parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='${p}'`), 10);

    /* a price sent by the client must be ignored */
    const r = await s.post('/api/stock.php', {
      action: 'sell', method: 'cash',
      lines: JSON.stringify([{ ref: p, qty: 1, price: 1, unit_price: 1, line_total: 1 }]),
    });
    ok('sale accepted', r.status === 200, `HTTP ${r.status}: ${r.body.slice(0, 140)}`);
    const sold = sql(`SELECT total FROM product_sales ORDER BY id DESC LIMIT 1`);
    ok('the server priced it, not the client',
       Math.abs(parseFloat(sold) - realPrice) < 0.01,
       `client said 1, server charged ${sold}, list price ${realPrice}`);

    const saleRef = sql(`SELECT ref FROM product_sales ORDER BY id DESC LIMIT 1`);
    sql(`UPDATE products p JOIN stock_movements m ON m.product_id=p.id
            SET p.qty_on_hand = p.qty_on_hand - m.qty WHERE m.reference='${saleRef}'`);
    sql(`DELETE FROM stock_movements WHERE reference='${saleRef}'`);
    sql(`DELETE FROM product_sale_items WHERE sale_id IN
           (SELECT id FROM product_sales WHERE ref='${saleRef}')`);
    sql(`DELETE FROM product_sales WHERE ref='${saleRef}'`);

    /* a negative quantity must not credit stock or refund money */
    const neg = await s.post('/api/stock.php', {
      action: 'sell', method: 'cash',
      lines: JSON.stringify([{ ref: p, qty: -10 }]),
    });
    const stockNow = parseInt(sql(`SELECT qty_on_hand FROM products WHERE ref='${p}'`), 10);
    ok('a negative quantity cannot inflate stock', stockNow <= stock,
       `${stock} → ${stockNow} (HTTP ${neg.status})`);
    const negTotal = parseFloat(sql(`SELECT COALESCE(MIN(total),0) FROM product_sales`));
    ok('no negative-value sale exists', negTotal >= 0, String(negTotal));

    /* tidy whatever the negative attempt created */
    const negRef = sql(`SELECT ref FROM product_sales WHERE sold_on='${sql("SELECT CURDATE()")}'
                          OR id > 6 ORDER BY id DESC LIMIT 1`);
    if (negRef && negRef !== '') {
      sql(`UPDATE products p JOIN stock_movements m ON m.product_id=p.id
              SET p.qty_on_hand = p.qty_on_hand - m.qty WHERE m.reference='${negRef}'`);
      sql(`DELETE FROM stock_movements WHERE reference='${negRef}'`);
      sql(`DELETE FROM product_sale_items WHERE sale_id IN
             (SELECT id FROM product_sales WHERE ref='${negRef}')`);
      sql(`DELETE FROM product_sales WHERE ref='${negRef}'`);
    }
  }

  /* ================================================================== */
  grp('booking-rule abuse');
  {
    const s = session();
    await s.token('/public/book.php');
    const svc = sql(`SELECT ref FROM services WHERE deleted_at IS NULL LIMIT 1`);
    const today = execFileSync('php', ['-r',
      "require 'includes/config.php'; require 'includes/db.php'; echo app_today();"],
      { encoding: 'utf8', cwd: 'c:/xampp/htdocs/beautybooking' }).trim();
    const past = sql(`SELECT DATE_SUB('${today}', INTERVAL 3 DAY)`);
    const far  = sql(`SELECT DATE_ADD('${today}', INTERVAL 400 DAY)`);

    for (const [label, fields] of [
      ['a date in the past',        { date: past, time: '10:00' }],
      ['a date beyond the window',  { date: far,  time: '10:00' }],
      ['a time outside opening hours', { date: sql(`SELECT DATE_ADD('${today}', INTERVAL 2 DAY)`), time: '03:00' }],
      ['a malformed time',          { date: sql(`SELECT DATE_ADD('${today}', INTERVAL 2 DAY)`), time: 'notatime' }],
      ['no service at all',         { date: sql(`SELECT DATE_ADD('${today}', INTERVAL 2 DAY)`), time: '10:00', services: '' }],
    ]) {
      const body = Object.assign(
        { services: svc, staff: 'any', name: 'Rule Breaker', phone: '+237600000004' }, fields);
      const r = await s.post('/api/bookings.php', body);
      ok(`refused: ${label}`, r.status === 422,
         `HTTP ${r.status}: ${r.body.slice(0, 140)}`);
    }
    ok('none of those created a booking',
       sql(`SELECT COUNT(*) FROM clients WHERE phone='+237600000004'`) === '0');
  }

  /* ================================================================== */
  grp('booking lookup cannot be brute-forced');
  {
    const s = session();
    await s.token('/public/manage-booking.php');
    const ref = sql(`SELECT ref FROM appointments WHERE status IN ('pending','confirmed') LIMIT 1`);
    const name = sql(`SELECT c.name FROM appointments a JOIN clients c ON c.id=a.client_id
                       WHERE a.ref='${ref}'`);

    const noPhone = await s.post('/api/lookup.php', { ref, phone: '' });
    ok('reference alone is not enough', noPhone.status === 404,
       `HTTP ${noPhone.status}`);
    ok('and it leaks no name', !noPhone.body.includes(name), noPhone.body.slice(0, 140));

    const wildcard = await s.post('/api/lookup.php', { ref, phone: '%' });
    ok('a SQL wildcard as the phone does not match', wildcard.status === 404,
       `HTTP ${wildcard.status}: ${wildcard.body.slice(0, 120)}`);

    const cancel = await s.post('/api/lookup.php',
      { ref, phone: '+237600000000', action: 'cancel' });
    ok('cannot cancel someone else\'s booking with a wrong phone', cancel.status === 404,
       `HTTP ${cancel.status}`);
    ok('the booking was not cancelled',
       sql(`SELECT status FROM appointments WHERE ref='${ref}'`) !== 'cancelled');
  }


  /* ================================================================== */
  grp('payments cannot be pushed at other people');
  {
    /* A payment prompt on someone's handset is an unsolicited demand for
       money. Knowing a booking reference must not be enough to send one. */
    const s = session();
    await s.token('/public/book.php');
    const ref = sql(`SELECT ref FROM appointments WHERE status IN ('pending','confirmed') LIMIT 1`);
    const realPhone = sql(`SELECT c.phone FROM appointments a JOIN clients c ON c.id=a.client_id
                            WHERE a.ref='${ref}'`);

    const wrong = await s.post('/api/payment.php', {
      action: 'start', ref, phone: '+237 600 00 00 00',
      provider: 'momo', payer_phone: '677000111',
    });
    ok('cannot start a payment without the booking phone', wrong.status === 404,
       `HTTP ${wrong.status}: ${wrong.body.slice(0, 120)}`);
    ok('and the refusal leaks no phone number', !wrong.body.includes(realPhone),
       wrong.body.slice(0, 140));

    const noCsrf = await s.post('/api/payment.php', {
      action: 'start', ref, phone: realPhone, provider: 'momo', payer_phone: '677000111',
    }, false);
    ok('payment start requires a CSRF token', noCsrf.status === 403,
       `HTTP ${noCsrf.status}`);

    /* With payments off, even a fully valid request must be refused. */
    const offState = sql(`SELECT value FROM settings WHERE \`key\`='payments_enabled'`);
    if (offState === '0') {
      const valid = await s.post('/api/payment.php', {
        action: 'start', ref, phone: realPhone, provider: 'momo', payer_phone: '677000111',
      });
      ok('with payments off, no charge can be started', valid.status === 422,
         `HTTP ${valid.status}: ${valid.body.slice(0, 120)}`);
    }

    /* Status of a payment that is not ours must not be readable by guessing. */
    const bogus = await s.post('/api/payment.php',
      { action: 'status', payment_ref: 'PAY-9999' });
    ok('an unknown payment reference is refused', bogus.status === 404,
       `HTTP ${bogus.status}`);
    const malformed = await s.post('/api/payment.php',
      { action: 'status', payment_ref: "' OR 1=1--" });
    ok('a malformed payment reference is refused cleanly',
       malformed.status === 404 && !/SQLSTATE|SQL syntax/i.test(malformed.body),
       `HTTP ${malformed.status}: ${malformed.body.slice(0, 120)}`);

    ok('no payment rows were created by any of that',
       sql(`SELECT COUNT(*) FROM payments WHERE payer_phone='237677000111'`) === '0');

    /* The callback endpoint is deliberately open, so it must believe nothing. */
    const cb = await s.req('/api/payment-callback.php?provider=momo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: 'PAY-0001', status: 'SUCCESSFUL', amount: 999999 }),
    });
    ok('the callback endpoint never errors at a provider', cb.status === 200,
       `HTTP ${cb.status}`);
    ok('a forged "SUCCESSFUL" callback banks nothing',
       sql(`SELECT COALESCE(SUM(deposit_paid),0) FROM appointments`) === '0.00',
       sql(`SELECT COALESCE(SUM(deposit_paid),0) FROM appointments`));
  }

  /* ================================================================== */
  grp('files that must never be served');
  {
    const s = session();
    for (const path of [
      '/includes/config.php', '/includes/db.php', '/includes/auth.php',
      '/db/migrations/0001_initial_schema.sql', '/db/migrations/0002_seed.sql',
      '/db/migrations/0003_history.sql',
      '/_dev/seed-data.js', '/_dev/8-unit.php', '/_dev/9-e2e.js',
      '/_dev/13-payments.php', '/_dev/12-preflight.php',
      '/package.json', '/README.md', '/db/schema.sql',
      '/PAYMENTS.md', '/includes/README.md',
    ]) {
      const r = await s.get(path);
      const served = r.status === 200 && r.body.length > 0;
      ok(`${path} is not served`, !served, `HTTP ${r.status}, ${r.body.length} bytes`);
      if (served) {
        const found = leaks(r.body, ['$2y$', 'DB_PASS', 'password_hash', 'CREATE TABLE']);
        if (found.length) console.log(`        LEAKED: ${found.join(', ')}`);
      }
    }
  }

  /* ================================================================== */
  grp('errors do not leak internals');
  {
    const s = session();
    for (const path of ['/api/appointment.php?ref=', '/api/client.php?ref=nope',
                        '/api/slots.php?date=garbage', '/public/confirmation.php?ref=nope',
                        '/admin/login.php?next=' + encodeURIComponent('http://evil.example/')]) {
      const r = await s.get(path);
      ok(`no stack trace from ${path.slice(0, 42)}`,
         !/Fatal error|Stack trace|on line \d+|C:\\\\xampp|SQLSTATE/i.test(r.body),
         r.body.slice(0, 160));
    }

    /* an open redirect would let a phish bounce off our own login page */
    const s2 = session();
    const page = await s2.get('/admin/login.php?next=http://evil.example/');
    const tok = (page.body.match(/name="_csrf" value="([^"]+)"/) || [])[1];
    const r = await s2.post('/admin/login.php',
      { email: 'aicha@princessbeautysalon.cm', password: 'demo1234', _csrf: tok,
        next: 'http://evil.example/' }, false);
    ok('login never redirects off-site',
       !r.location.startsWith('http://evil.example'),
       `→ ${r.location}`);
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  process.exit(fail ? 1 : 0);
})();
