# Princess Beauty Salon — Web Platform

**Phase 2: full PHP + MySQL application.** Every figure comes from the database;
the admin panel is guarded server-side.
Brand: black `#0B0B0B` · gold `#C9A227` · hot pink `#E91E8C` · white.
Tagline: *Look Good. Feel Good. Be You.*

## Run it

```
http://localhost/beautybooking/                 public site
http://localhost/beautybooking/admin/login.php  staff portal
```

Requires Apache + MySQL running in XAMPP. First-time setup:

```bash
npm install          # test tooling (jsdom not needed any more; puppeteer-core only)
npm run migrate      # creates princess_beauty_salon and seeds it
```

**Demo accounts** — password `demo1234` for all of them:

| Email | Role | Sees |
|---|---|---|
| `aicha@princessbeautysalon.cm` | Owner | Everything |
| `pauline.manager@…` | Manager | Operations + financials, no users/settings (suspended by default) |
| `reception@…` | Receptionist | Bookings, clients, counter sales |
| `blessing@…` | Staff | Own schedule, clients and performance only |

## Structure

```
beautybooking/
├─ index.php                  redirect → public/index.php
├─ public/                    CUSTOMER SITE (14 pages, all PHP)
│  ├─ index · services · offers · book · confirmation · manage-booking
│  ├─ gallery · team · about · contact · faq · privacy · terms · 404
├─ admin/                     BACK OFFICE (18 files, permission-guarded)
│  ├─ login · logout · index (dashboard)
│  ├─ appointments · clients · services · staff
│  ├─ revenue · expenses · inventory · export (CSV)
│  ├─ users · settings · notifications · profile
│  └─ my-schedule · my-clients · my-performance   (staff portal)
├─ api/                       JSON endpoints
│  ├─ services · staff · slots · bookings · lookup · contact   (public)
│  └─ appointment · client · stock                             (admin)
├─ includes/                  config · db · helpers · auth · repo
│  └─ booking · admin-layout · public-layout       (see its README)
├─ db/
│  ├─ migrate.php             runner with a migrations table
│  ├─ sync-images.php         scans assets/img/ into the gallery table
│  └─ migrations/             0001 schema · 0002 seed · 0003 history
│                             0004 opening stock · 0005 payments · 0006 gallery folders
├─ assets/                    css · js
│  └─ img/                    the photos themselves — see its README
└─ _dev/                      generators + test suites
```

## How it is wired

- **All mock data lives in `assets/js/data.js`.** One array per future MySQL table.
  See `includes/README.md` for the table mapping.
- **Shared chrome is injected by JS**, not copy-pasted. The public header/footer/tab-bar
  come from `layout.js`; the admin sidebar/topbar from `admin-layout.js`. Each of those
  functions becomes a PHP `include` in phase 2 with no markup changes.
- **Photos live in folders, not in the database.** `gallery` stores a folder id
  and a filename; the public path is assembled in `repo_gallery()` and nowhere
  else. Publishing a photo is two steps — drop the file into
  `assets/img/<folder>/`, run `npm run images`. The folder is also what the
  photo *is*: the filter chips on the gallery page and the "book this look"
  category both hang off `gallery_folders`, so `makeup/` can be shown without
  being sellable. Any slot whose file is missing falls back to the gradient tile
  the site shipped with, which is why a half-filled folder never breaks a page.
  See `assets/img/README.md`.
- **Charts are hand-rolled inline SVG** in `charts.js` — no chart library, no CDN.
- **Role-based access is enforced in one place** (`OWNER_ONLY` in `admin-layout.js`).
  A stylist signing in sees only their own schedule and clients; revenue, expenses,
  inventory, staff, services, users and settings are hidden from the menu *and* redirect
  if reached by URL. Owner-only blocks on the dashboard are **removed from the DOM**,
  not just hidden with CSS.
- **Users ≠ staff.** `PBS.users` is logins; `PBS.staff` is people and their craft. A
  receptionist has an account but no chair; a stylist can exist in the diary before
  ever signing in. `admin/users.html` links the two.

## Roles

Four roles ship, defined once in `PBS.roles` and rendered as a live permission matrix
on the Users page:

| Role | Sees |
|---|---|
| **Owner / Admin** | Everything, including revenue, expenses, stock and user accounts |
| **Manager** | Floor operations + financials, but not users or settings |
| **Receptionist** | Bookings, clients and the product counter only |
| **Staff / Stylist** | Own schedule, own clients, own performance — nothing else |

## Mobile

Mobile-first throughout, and **measured in a real browser** at 14 widths from 320px to
1440px — 420 page/viewport combinations, each checked in **every UI state** (wizard
steps, settings panels, calendar view, modals). Zero horizontal overflow, zero clipped
text, zero undersized tap targets, zero controls covered by fixed furniture
(`npm run test:layout`).

- **Public**: hamburger drawer + a fixed bottom tab bar with a raised Book button.
- **Admin**: sidebar collapses to a drawer, plus its own role-aware bottom tab bar.
- **Wide data tables become cards.** Below 760px `.table-cards` hides the header row and
  each cell prints its own column name. Those labels are stamped at runtime from the
  table's own `<thead>` (`PBS.labelCells`) and re-applied on every filter/re-render via
  a MutationObserver — so they can never drift out of sync with the headers.
- **Charts size their viewBox to their container.** A fixed 760-unit viewBox inside a
  328px phone column scales every label by 0.43, rendering 11px axis text at under 5px.
  Matching the viewBox to the container keeps 1 SVG unit ≈ 1 CSS pixel at any width, and
  x-labels thin out to what actually fits. Charts redraw on resize/rotate.
- Tap targets clear 40px on coarse pointers — scoped to navigation and actions. Links
  inside a sentence are left alone; padding those to 44px would wreck the prose, and
  WCAG exempts inline text links for that reason.
- The booking summary moves *above* the wizard controls on narrow screens.

### Four CSS traps worth knowing about

All four are invisible without real layout measurement — the computed styles all
look correct:

1. **`min-width:auto` on grid/flex items.** An item never shrinks below its content by
   default, so one form input's intrinsic width forces its whole track wider than the
   viewport. `min-width:0` fixes it — but it must be applied to **every** grid container,
   not just `.grid`. The booking wizard's `1fr` track took its minimum from 21 date pills
   and resolved to **1378px inside a 328px shell**, so the page scrolled sideways and the
   date strip never scrolled internally. Apply narrowly, though: a blanket
   `.flex > *{min-width:0}` lets text collapse to *zero* width, which is worse.
2. **Cascade order between breakpoints.** An unbounded `@media (max-width:1024px)` rule
   placed after a `max-width:520px` rule also matches at 360px, and wins by being later.
   That forced two 154px columns onto a phone. Bound such rules at both ends:
   `@media (min-width:521px) and (max-width:1024px)`.
3. **Unbreakable strings.** An email address cannot wrap at a space, so in a narrow
   footer column it simply overflows. `overflow-wrap:anywhere`.
4. **`position:sticky` needs room to stick.** A sticky element can only stick within
   its containing block. `layout.js` injects the header into `#header-slot`, a wrapper
   exactly the header's own height — so the header scrolled away on every page despite
   reporting `position: sticky`. `#header-slot{display:contents}` removes the wrapper's
   box so the containing block becomes `<body>`.

## Demo notes

- Any credentials sign you in. The **role picker on the login page** is what changes
  what you see — sign in as Owner for the full financial view, as Staff for the
  restricted one. The ⧉ user icon in the admin topbar switches roles mid-demo.
- The admin panel is pinned to a fixed "today" (**20 August 2026**) so every figure
  across every screen tells one consistent story.
- Prices are FCFA; payment methods include MTN MoMo and Orange Money.
- Anything that would write to a database shows a toast saying so instead.

## Chart colour contract

The categorical palette is fixed and validated for colour-vision deficiency
(worst adjacent pair ΔE 15.8 under protanopia):

```
1 gold #C9A227   2 pink #E91E8C   3 blue #2F6FED   4 green #12A150   5 violet #7B54D3
```

Gold measures 2.36:1 against white, below the 3:1 threshold, so **every chart using
gold ships visible value labels** (ranked bars and the donut legend) as the required
relief. Gold and green must never occupy adjacent slots — that pair drops to ΔE 6.7.
Details are in the header comment of `assets/js/charts.js`.

## Tests

```bash
npm run test:all      # backend suite + 270 page/viewport combinations
npm run test:backend  # schema, integrity, auth, permissions, reconciliation
npm run test:http     # real Chrome against the real server, 10 viewports
```

`test:http` signs in as the owner and drives every page. It checks horizontal
overflow, leaked PHP notices, `undefined`/`NaN` in visible text, illegible chart
labels, and that the sticky header actually sticks.

## Not in this phase

No payment gateway (deposits are quoted, never charged), no WhatsApp or email
sending, no image upload, and two-factor is a flag rather than an enforced
second factor. Each is flagged in the UI where a user would expect it, rather
than silently pretending to work. Photography is still gradient placeholders.

## Before this goes near a real salon

- Set `APP_ENV = 'production'` in `includes/config.php` — errors then log
  instead of printing to the page.
- Give MySQL a real user and password; `root` with no password is a XAMPP
  development default, not a deployment setting.
- Put it behind HTTPS. `auth.php` already sets the `secure` cookie flag
  automatically once it sees TLS.
- Change every seeded password. They are all `demo1234`.
- The deny-all `.htaccess` files in `includes/`, `db/` and `_dev/` rely on
  `AllowOverride` being enabled. On a host that ignores `.htaccess`, move those
  directories outside the web root instead — `npm run test:http` will tell you
  if they are reachable.
