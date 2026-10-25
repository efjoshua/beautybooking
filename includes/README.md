# /includes — application core

These are the shared PHP files every page loads. Phase 2 is complete: the
prototype's `data.js` mock arrays are gone from the server side, and every
figure comes from MySQL.

| File | Responsibility |
|---|---|
| `config.php` | DB credentials, base URL, currency, session and security constants. **The only file that changes between environments.** |
| `db.php` | One PDO connection + `db_all/one/val/insert/update/transaction`. Every statement is prepared. |
| `helpers.php` | `e()`, `money()`, `duration()`, badges, `setting()`, reference generators. |
| `auth.php` | Sessions, login with throttling, CSRF, permissions, audit log. |
| `repo.php` | **All reporting SQL.** A figure has exactly one definition here. |
| `booking.php` | Availability computation and booking creation/lookup/cancel. |
| `payments.php` | Mobile money: the three payment states, deposit maths, MTN MoMo and Orange Money drivers. |
| `admin-layout.php` | Server-rendered admin chrome, menu filtered by real permissions. |
| `public-layout.php` | Server-rendered public header, footer, drawer, tab bar. |

## Design decisions worth keeping

**There is one definition of "today": `app_today()`.** Reads once filtered by
it while writes stamped `date('Y-m-d')`, and the moment the two drifted the app
broke quietly — a counter sale rung up "now" landed on a date today's takings
did not cover, and the booking window opened on a week the diary never showed.
Never call `date('Y-m-d')` for a business date.

**Prices are copied, not referenced.** `appointment_items` stores
`service_name` and `unit_price` at the moment of booking. Raising the price of
braids must not silently rewrite last month's takings.

**Nothing is stored pre-aggregated.** There is no `daily_revenue` table.
`repo_revenue_between()` sums the rows every time, because a stored total goes
stale the instant a booking is edited and nobody notices.

**`users` and `staff` are separate tables.** A receptionist has a login but no
chair; a stylist exists in the diary before they ever sign in. The FK is
nullable and `ON DELETE RESTRICT` — removing a stylist must never destroy the
commission history attached to them.

**`stock_movements` is the truth; `products.qty_on_hand` is a cache.** They are
reconciled by a test (`_dev/6-backend.php`). Migration `0004` exists purely
because the seed created quantities without opening-balance movements, so the
two could not be audited against each other.

**Permissions are enforced twice, deliberately.** The sidebar hides links the
role cannot use; `require_permission()` at the top of every page is what
actually stops anyone typing the URL. The prototype only had the first.

## Availability

`booking_slots()` is the real engine. A slot is offered only when:

- the salon is open that weekday, and the service finishes before closing;
- a stylist qualified for **every** chosen category is on shift;
- that stylist is free for the whole duration plus the turnaround buffer;
- the minimum-notice period has not already passed.

`booking_create()` re-checks all of it **inside the transaction**, so two people
submitting the same slot cannot both win. The loser gets
"that slot was taken while you were booking".

## API endpoints

Public (no auth): `services.php`, `staff.php`, `slots.php`, `bookings.php`,
`contact.php`, `lookup.php`.
Admin (permission-checked): `appointment.php`, `client.php`, `stock.php`.

All writes require a CSRF token; a bad one returns **403** (not 419 — Apache
will not emit non-standard codes and it surfaced as a misleading 500).

## Migrations

```bash
npm run migrate          # apply anything pending
npm run migrate:status   # show what would run
npm run migrate:fresh    # DROP and rebuild from zero
npm run seed             # regenerate 0002/0003 from seed-data.js, then migrate
```

`0002_seed.sql` and `0003_history.sql` are **generated**, not hand-written —
see `_dev/gen-seed.js` and `_dev/gen-history.js`. Edit `_dev/seed-data.js` and
regenerate rather than editing the SQL.

## Tests

```bash
npm run test:unit      # 207 assertions — pure PHP, no server needed
npm run test:backend   #  32 — schema, permissions, reports against real rows
npm run test:http      # 285 — every page at 10 viewports, plus leak checks
npm run test:e2e       #  57 — real browser, clicking through real journeys
npm run test:abuse     # 116 — attacks that must fail
npm run test:payments  #  56 — payment states, phone parsing, ledger rules
npm run test:payjourney#  20 — the booking journey in every payment state
npm run test:finance   #  28 — are the NUMBERS right, and do screens agree
npm run test:all
npm run preflight      # deployment posture — run it on the live server
```

`11-finance.php` recomputes every reported figure from first principles and
cross-checks the screens against each other. It also raises three judgement
calls it will not decide for you: tips sit inside gross revenue and therefore
inside net profit; completed-but-uncollected work counts as revenue; and the
commission table states what is *owed*, while net profit subtracts what was
actually *paid* under Salaries & commissions.

`12-preflight.php` answers "can this go live". A failure there is a
configuration gap, not a bug.

`9-e2e.js` drives the UI the way a person does: it books by clicking the
wizard step by step, signs in by typing, settles a bill through the payment
modal, sells at the counter and logs an expense on the form — then undoes
every write, so the demo dataset is byte-for-byte where it started.

`10-abuse.js` attacks the running app: IDOR across stylists, privilege
escalation, CSRF (absent, forged, and borrowed from another session), SQL
injection, stored XSS, mass assignment, price and quantity tampering, booking
brute force, and direct fetches of files that must never be served. Each check
passes only when the app *refuses*, and every refusal is asserted to carry no
leaked data — a 500 must not be mistaken for a defence.

### What real testing actually found

| Bug | Effect |
|---|---|
| Writes used the wall clock, reads used `app_today()` | A sale made "now" never appeared in today's takings; bookings landed in a week the diary did not show. |
| `<input type="number" min="1" step="100">` on the expense amount | Valid values were 1, 101, 201… so **every round figure the owner would type was rejected**, with the message "the two nearest valid values are 17401 and 17501". Same trap on tips, add-ons, prices and restock costs. |
| `assert_owns()` gated on `can('revenue')` | The **receptionist could open the diary and then not a single appointment on it** — her `staff_id` is NULL, which cast to 0 and matched no stylist. Her entire job was blocked. |
| `money_short()` ran `rtrim(…, '0')` | 10,000,000 rendered as "1M" — a figure understated tenfold. |
| No guard on completing a future booking | Tomorrow's revenue could be banked today and its stock deducted early. |
| Login lockout keyed on email alone | Anyone knowing the owner's address could lock her out of her own salon. |
| The revenue trend chart summed appointments only | The chart footed to 907,000 while the tile directly above it read 978,000 — the same screen disagreeing with itself by exactly the month's retail takings. |
| `commission_basis` was read nowhere | The settings page promises "the commission report recalculates from this basis every time it loads". It did not. Every basis paid on service price alone, short-changing a stylist on "service + add-ons + tips" by 19,625 FCFA a month. |
| `default_commission_pct` was read nowhere | Both the form and its JavaScript hardcoded 35%. |
| `terms.php` hardcoded the deposit policy | It stated 20,000 FCFA / 30% / 4 hours as flat text, so changing the settings left the published terms contradicting the enforced rules. |
| Five dead `.html` links | "Book an Appointment" on the terms page 404'd — leftovers from the phase-1 prototype. |

Two of these could only be found by using the app rather than testing its
functions: the `step` bug lives entirely in browser validation, and the
receptionist lockout needed a session in that specific role.

## Hardening

`includes/`, `db/` and `_dev/` each carry a deny-all `.htaccess`, and the root
one blocks `package.json`, `README.md` and any stray `.sql`/`.log`/`.bak`.

This was not paranoia. Before it was added:

- **`db/migrations/0002_seed.sql` was downloadable** — 95KB containing the full
  schema, every client's phone number and private note, and the bcrypt hash for
  every account.
- **`assets/js/data.js` was loaded on every public page** — 58KB of phase-1 mock
  database, including client emails, phone numbers, stylist notes and staff
  login addresses. Only six of its forty-seven exports were ever used.

`data.js` is now `_dev/seed-data.js` (read only by the generators, denied over
HTTP) and served pages load `assets/js/ui.js` — 8KB of icons and formatters,
carrying no data at all. `_dev/7-http.js` asserts both, and both assertions were
verified by reintroducing the leak and watching them fail.

## Payments

See **PAYMENTS.md**. Three states — off, on-without-keys, on-with-keys — all
supported and all tested. The governing rule is that **a missing API key must
never stop anyone booking**: with payments on but unconfigured, the client sees
the MoMo and Orange tiles marked *Not configured*, is told plainly that payment
is not connected, and completes the booking anyway.

The booking is created *before* the deposit is requested, so a failed or
timed-out charge costs nobody their slot. `appointments.deposit_paid` is
recomputed from the `payments` ledger rather than incremented, the same rule
that governs `products.qty_on_hand`.

## Not built

WhatsApp/email sending,
image upload, and two-factor enforcement. Each is flagged in the UI where a
user would expect it rather than silently pretending to work.
