/**
 * End-to-end HTTP + layout suite for the PHP application.
 *
 * Replaces 1-pages.js / 5-layout.js, which loaded static .html from disk —
 * every page is now server-rendered and most require a session, so the suite
 * drives real Chrome against the real server and signs in like a user would.
 *
 *   node _dev/7-http.js            all viewports
 *   node _dev/7-http.js 360        one width
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

/* Port 80 is not always ours to take on Windows — http.sys reserves it.
   Override with PBS_BASE=http://localhost:8000/beautybooking */
const BASE = process.env.PBS_BASE || 'http://localhost/beautybooking';
const CHROME = [
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].find(p => fs.existsSync(p));

const VIEWPORTS = [
  { name: '320 small phone', width: 320,  height: 700 },
  { name: '360 phone',       width: 360,  height: 780 },
  { name: '414 large phone', width: 414,  height: 896 },
  { name: '519 (bp edge)',   width: 519,  height: 800 },
  { name: '521 (bp edge)',   width: 521,  height: 800 },
  { name: '768 tablet',      width: 768,  height: 1024 },
  { name: '900 tablet wide', width: 900,  height: 1000 },
  { name: '1024 (bp edge)',  width: 1024, height: 800 },
  { name: '1080 (bp edge)',  width: 1080, height: 800 },
  { name: '1440 desk',       width: 1440, height: 900 },
];

const PUBLIC = ['index', 'services', 'offers', 'book', 'manage-booking', 'gallery',
                'team', 'about', 'contact', 'faq', 'privacy', 'terms']
                .map(p => `/public/${p}.php`);
const ADMIN = ['index', 'appointments', 'revenue', 'expenses', 'inventory', 'clients',
               'services', 'staff', 'users', 'settings', 'notifications', 'profile',
               'my-schedule', 'my-clients', 'my-performance']
               .map(p => `/admin/${p}.php`);

const only = process.argv[2] ? Number(process.argv[2]) : null;
let total = 0;

async function signIn(page) {
  await page.goto(BASE + '/admin/login.php', { waitUntil: 'domcontentloaded' });
  /* Cookies are shared across pages in one browser, so after the first
     viewport we are already signed in and login.php redirects to the
     dashboard — there is no form to fill. */
  if (!(await page.$('#email'))) return;
  await page.type('#email', 'aicha@princessbeautysalon.cm');
  await page.type('#pw', 'demo1234');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type=submit]'),
  ]);
}

async function audit(page, vw) {
  return page.evaluate((vw) => {
    const problems = [];
    const seen = new Set();
    const add = m => { if (!seen.has(m)) { seen.add(m); problems.push(m); } };
    const desc = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') +
      (typeof el.className === 'string' && el.className
        ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');

    if (document.documentElement.scrollWidth > vw + 1) {
      add(`PAGE scrolls horizontally: ${document.documentElement.scrollWidth}px > ${vw}px`);
    }

    document.querySelectorAll('body *').forEach(el => {
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || cs.position === 'fixed') return;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) return;
      if (r.right > vw + 1) {
        let p = el.parentElement, contained = false;
        while (p && p !== document.body) {
          if (/auto|scroll|hidden/.test(getComputedStyle(p).overflowX)) { contained = true; break; }
          p = p.parentElement;
        }
        if (!contained) add(`OVERFLOW ${desc(el)} right=${Math.round(r.right)}`);
      }
    });

    /* leaked PHP notices or unrendered template text */
    const txt = document.body.innerText;
    ['Fatal error', 'Warning:', 'Notice:', 'Parse error', '<?php', 'Undefined']
      .forEach(bad => { if (txt.includes(bad)) add(`PHP output leaked: "${bad}"`); });
    ['undefined', 'NaN', '[object Object]']
      .forEach(bad => { if (txt.includes(bad)) add(`literal "${bad}" visible`); });

    /* chart labels must stay legible */
    document.querySelectorAll('svg text').forEach(t => {
      const r = t.getBoundingClientRect();
      if (r.height > 0 && r.height < 7) add(`CHART TEXT ~${r.height.toFixed(1)}px — illegible`);
    });

    /* the sticky header must actually stick */
    const hdr = document.querySelector('.site-header');
    if (hdr && getComputedStyle(hdr).position !== 'sticky') add('site header is not sticky');

    return problems;
  }, vw);
}

(async () => {
  if (!CHROME) { console.error('No Chrome/Edge found.'); process.exit(1); }
  const selected = VIEWPORTS.filter(v => !only || v.width === only);
  if (!selected.length) {
    console.error(`No viewport of width ${only}. Available: ` + VIEWPORTS.map(v => v.width).join(', '));
    process.exit(2);
  }

  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });

  /* ------------------------------------------------------------------
     SECURITY: nothing internal may be fetched, and nothing served may
     carry client PII.
     data.js used to ship 58KB of mock database to every visitor —
     client names, phone numbers, emails and private stylist notes.
     0002_seed.sql was downloadable and contained the bcrypt hash for
     every account. Both are fixed; this keeps them fixed.
     ------------------------------------------------------------------ */
  {
    console.log(String.fromCharCode(10)+"########## security ##########");
    const probe = await browser.newPage();
    const mustDeny = [
      'includes/config.php', 'includes/auth.php',
      'db/migrations/0001_initial_schema.sql', 'db/migrations/0002_seed.sql',
      '_dev/seed-data.js', '_dev/gen-seed.js', '_dev/6-backend.php',
      'package.json', 'README.md',
    ];
    for (const p of mustDeny) {
      const r = await probe.goto(BASE + '/' + p, { waitUntil: 'domcontentloaded' })
        .catch(() => null);
      const code = r ? r.status() : 0;
      const ok = code === 403 || code === 404;
      if (!ok) total++;
      console.log(`${ok ? 'ok  ' : 'FAIL'}  denied: ${p} (HTTP ${code})`);
    }

    /* no client PII in any served asset or public page */
    /* Full distinctive strings only. Two-word fragments such as "Sensitive
       scalp" also occur as legitimate UI copy — the booking form uses exactly
       that phrase as a placeholder example — so matching on them reports the
       form rather than a leak. */
    const PII = [
      'no tight edges. Prefers Saturday mornings',
      'marlyse.n@gmail.com',
      '+237 6 90 11 22 33',
      'aicha@princessbeautysalon.cm',
      'password_hash', 'PBS.clients', 'PBS.appointments',
    ];
    const served = ['assets/js/ui.js', 'assets/js/public-ui.js', 'assets/js/charts.js',
                    'public/index.php', 'public/book.php', 'public/team.php'];
    for (const p of served) {
      const r = await probe.goto(BASE + '/' + p, { waitUntil: 'domcontentloaded' });
      const body = await r.text();
      const hits = PII.filter(x => body.includes(x));
      if (hits.length) total += hits.length;
      console.log(`${hits.length ? 'FAIL' : 'ok  '}  no PII: ${p}` +
                  (hits.length ? '  -> ' + hits.join(', ') : ''));
    }
    await probe.close();
  }


  for (const vp of selected) {
    console.log(`\n########## ${vp.name} (${vp.width}px) ##########`);
    const page = await browser.newPage();
    await page.setViewport({
      width: vp.width, height: vp.height,
      isMobile: vp.width <= 430, hasTouch: vp.width <= 430,
    });
    const jsErrors = [];
    page.on('pageerror', e => jsErrors.push(e.message));

    for (const url of PUBLIC.concat(ADMIN)) {
      if (url === ADMIN[0]) await signIn(page);
      jsErrors.length = 0;
      const res = await page.goto(BASE + url, { waitUntil: 'load' });
      await new Promise(r => setTimeout(r, 250));

      const problems = await audit(page, vp.width);
      const status = res.status();
      if (status >= 400) problems.unshift(`HTTP ${status}`);
      jsErrors.forEach(e => problems.push('JS: ' + e.slice(0, 120)));

      total += problems.length;
      console.log(`${problems.length ? 'FAIL' : 'ok  '}  ${url}`);
      problems.slice(0, 6).forEach(p => console.log('        ' + p));
    }
    await page.close();
  }

  await browser.close();
  console.log('\n=== total issues: ' + total + ' ===');
  process.exit(total ? 1 : 0);
})();
