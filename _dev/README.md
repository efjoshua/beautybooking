# /_dev — automated checks

Not part of the site. Nothing here is served or referenced by any page; delete the
folder and the prototype is unaffected.

Four scripts load every page in a headless DOM; the fifth drives real Chrome and
measures actual layout. They caught the real bugs in this build (unsized SVGs
rendering at 300×150, filled glyphs drawn as outlines, a `sessionStorage` throw that
stranded the booking confirm). Keep running them as PHP lands.

## Run

```bash
npm install     # once — pulls jsdom
npm test        # runs all four

# or individually
npm run test:pages
npm run test:interactions
npm run test:new
npm run test:mobile
npm run test:layout    # real Chrome, 14 viewports
npm run test:all       # everything
```

Paths resolve relative to `_dev/`, so the project can be moved or renamed freely.

## What each covers

| Script | Checks |
|---|---|
| `1-pages.js` | Every page loads with no JS error; shared chrome mounts; ~50 containers render non-empty; no `undefined`/`NaN`/`[object Object]` in visible text; no dead internal links; every icon SVG carries an explicit size |
| `2-interactions.js` | Full 5-step booking wizard incl. stylist eligibility and totals; appointment tabs/filters/calendar/detail modal; POS add-and-sell; gallery filter + lightbox; staff-role dashboard; owner-only redirect guard |
| `3-new-pages.js` | Users & roles (filters, add/edit modal, permission matrix, audit log); the three staff-portal screens under a Staff session; owner-only guards on `users`/`expenses`; expenses filters; profile under both roles; offers filter; FAQ accordion; manage-booking lookup |
| `4-mobile.js` | Viewport meta on every page (and that pinch-zoom is not disabled); every wide table opted into `.table-cards` **and** fully labelled; mobile nav present; no fixed width >380px outside a scroll container |
| `5-layout.js` | **Real layout, in real Chrome.** 30 pages × 14 viewports = 420 combinations, each measured in **every UI state**. Horizontal page overflow, elements escaping the viewport, clipped text, sub-36px tap targets, illegible chart labels, oversized phone gaps, fixed furniture covering a control |

### Three things `5-layout.js` learned the hard way

**1. Measure every state, not just the initial paint.** Anything behind
`display:none` has no layout. The booking wizard's steps 2–5, the settings
panels, the calendar view and every modal were invisible to the audit — which is
how a **1378px-wide** date strip survived a "zero issues" sweep and shipped. The
suite now reveals and measures each state in turn, and labels findings with it
(`[wizard step 3] PAGE scrolls horizontally: …`).

**2. Sample across the scroll range.** A sticky bar only collides while it is
*pinned*, which happens mid-scroll — not at the top, and not at the bottom where
it returns to its natural position. An overlap check that only looked at the
bottom of the page passed happily while the floating WhatsApp button sat directly
on top of the wizard's "Continue". It now samples at 25/50/75%.

**3. Some bugs need a scroll, not a measurement.** The site header was declared
`position:sticky`, reported `position: sticky`, produced no overflow and clipped
nothing — and never stuck on any page, because it was injected into a wrapper
exactly its own height and a sticky element can only stick within its containing
block. `stickiness()` scrolls and asserts each sticky element is still parked at
its `top` offset.

Every one of these checks was verified by **re-introducing the bug and confirming
the suite fails**. Two of them were silent no-ops on the first attempt and passed
happily with the bug present. A check that has never been seen to fail is not
evidence of anything.

> Passing a width that is not in `VIEWPORTS` used to test nothing and print
> "0 issues". It now exits with an error listing the valid widths.

`5-layout.js` needs no browser download — it drives the Chrome or Edge already
installed via `puppeteer-core`. Run one width with `node _dev/5-layout.js 360`.

The viewport list deliberately includes the pixel either side of **every**
breakpoint (519/521, 759/768, 1024/1080). That is exactly where cascade-order
bugs hide: an unbounded `max-width` rule placed later in the file also matches
smaller widths and silently wins. One such bug forced two 154px columns onto a
360px phone and was invisible at every "normal" test width.

## Two expected failures

`1-pages.js` and `2-interactions.js` each report one failure on `book.html`:

```
Not implemented: Window's scrollTo() method
Not implemented: navigation to another Document
```

Both are jsdom gaps, not bugs — the wizard calls `window.scrollTo()` between steps and
`location.href` on confirm. Both work in a real browser. Everything else must stay at
zero.

## Limits

jsdom does not do layout, so these verify structure and behaviour, not pixels. Spacing,
overflow and hover states still need a real browser at 360px, 768px and 1440px.
