# Mobile money payments — how to switch them on

Everything is built and tested. Turning payments on is **one checkbox**;
collecting real money is **one checkbox plus six constants**. There is no code
to write.

---

## The three states

The app behaves correctly in all three, and it is tested in all three
(`npm run test:payments`, `npm run test:payjourney`).

### 1. Off — where you are now, and a perfectly good place to stay

Settings → Booking rules → **Take payments online** is unticked.

- The booking page never mentions payment.
- Clients book and settle at the salon, exactly as before.
- The Services and Terms pages still quote your deposit policy, because that
  is a statement of what you ask for, not a charge.

### 2. On, but no API keys — the state you will be in the day you flip it

Tick **Take payments online** before you have credentials, and:

- The booking page shows an **MTN MoMo** and an **Orange Money** tile, each
  marked *Not configured*.
- A notice says *"Online payment is not connected yet — you can complete your
  booking without paying, and settle at the salon."*
- **The client completes the booking normally.** Nobody is ever blocked from
  booking by a missing key. No deposit is demanded, no payment row is written.
- Settings shows you the same warning, so you know why nothing is being taken.

This exists so you can see exactly what your clients will see before you spend
anything on a subscription.

### 3. On, with keys — collecting

As soon as one provider has credentials:

- Bookings at or above your threshold show the deposit on the review step.
- Confirming creates the booking **first**, then asks for the deposit.
- MTN MoMo pushes a PIN prompt to the client's handset; Orange Money sends them
  to Orange's own payment page and back.
- A provider with no keys stays visible and greyed, so a client on Orange knows
  the salon takes Orange — just not online yet.

---

## Filling in the keys

Everything lives in **`includes/config.php`**, the only file that differs
between machines. Nothing else changes.

### MTN MoMo

1. Register at <https://momodeveloper.mtn.com> and subscribe to **Collection**.
2. Copy your **Primary Key** → `MOMO_SUBSCRIPTION_KEY`.
3. Create an API User and API Key → `MOMO_API_USER`, `MOMO_API_KEY`.
4. While testing leave `MOMO_ENVIRONMENT` as `'sandbox'`. When MTN approves you
   for production, set it to `'mtncameroon'` and change `MOMO_BASE_URL` to the
   production host they give you.

```php
define('MOMO_SUBSCRIPTION_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('MOMO_API_USER',         'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
define('MOMO_API_KEY',          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('MOMO_ENVIRONMENT',      'mtncameroon');
```

> The sandbox bills in **EUR**, not FCFA — that is MTN's rule, not ours.
> `MOMO_CURRENCY` follows `MOMO_ENVIRONMENT` automatically, so you do not have
> to remember it.

### Orange Money

1. Register at <https://developer.orange.com> and subscribe to **Orange Money
   Web Payment**, country **Cameroon**.
2. Client ID and Client Secret → `OM_CLIENT_ID`, `OM_CLIENT_SECRET`.
3. The merchant key from your Orange Money merchant account →
   `OM_MERCHANT_KEY`.

### Then

Settings → Booking rules → tick **Take payments online**, and tick **Require a
deposit to hold a slot** if you want deposits enforced. Set the threshold and
percentage. That is all.

Run `npm run preflight` to confirm — it reports which providers have
credentials and refuses to call a missing key a working gateway.

---

## Decisions built into this, and why

**The booking is created before the money is asked for.** If the deposit fails,
times out, or MTN is down, the client keeps their slot and you get a pending
booking to chase. Charging first would lose the appointment every time a
network hiccupped and leave you refunding people.

**There is always a "Pay at the salon instead" button.** A payment step that
can trap a client is worse than no payment step.

**A failed payment is kept, never deleted.** When a client says "I paid", you
can answer from the `payments` table without phoning MTN. The provider's own
reason string is stored verbatim.

**`appointments.deposit_paid` is recomputed from the payments ledger**, never
incremented — the same rule as `products.qty_on_hand` and `stock_movements`. A
payment later marked failed correctly reduces the total.

**Callbacks are hints, not instructions.** `api/payment-callback.php` is open to
the internet, so it believes nothing in the request body. It reads only which
payment to ask about, then asks MTN or Orange directly with our own
credentials. A forged "SUCCESSFUL" callback banks nothing — there is a test for
exactly that.

**One live attempt per booking at a time.** Two prompts on one handset for one
booking is how a client ends up paying twice.

**The payer's phone is stored separately from the client's.** People routinely
pay from a spouse's or a colleague's line.

**A number is checked against its network before charging.** An Orange number
entered under MTN MoMo is refused with "that looks like an Orange number"
rather than a charge that fails with an unhelpful PIN error.

---

## What is still manual

- **Refunds.** Nothing in the app sends money back. Refund through your MoMo or
  Orange merchant app and note it against the booking.
- **Reconciliation.** The app records what the provider told it. Check that
  against your merchant statement as you would any till.
- **The balance after the deposit** is taken at the counter and recorded through
  the Take Payment modal, as it always was.

---

## Testing it

```bash
npm run test:payments     # 56 checks — states, phone parsing, ledger rules
npm run test:payjourney   # the booking journey in a browser, in every state
```

`test:payjourney` runs the OFF and UNCONFIGURED journeys always, and adds the
live deposit journey automatically once credentials are present. With sandbox
keys in place it will attempt a real charge against MTN and assert that the
booking survives whatever comes back.
