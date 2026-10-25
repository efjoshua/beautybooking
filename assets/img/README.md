# Images

**The folders in here are the source of truth.** The database never stores a
picture — only the folder a file sits in and the filename inside it. The public
path is assembled at read time as `assets/img/<folder>/<filename>`.

```
assets/img/
├─ hair-styles/   braids and protective styles   → Braids & Plating
├─ haircut/       cuts, twists, locs             → Hair Cuts & Styling
├─ makeup/        makeup looks                   → no bookable category (yet)
├─ manicure/      hands and nail art             → Nails & Nail Art
├─ pedicure/      feet                           → Nails & Nail Art
├─ lashes/        lashes and brows               → Facials & Lashes
├─ massage/       spa and body work              → Massage & Spa
├─ backgrounds/   interiors and section art — never shown as a gallery tile
└─ team/          stylist portraits, named by staff ref: ST-01.jpeg …
```

## Publishing a photo

1. Drop the file into the right folder.
2. `npm run images`

That is the whole workflow. The scanner inserts a row for each new file,
deletes the rows whose file has gone, and leaves the rest alone — run it as
often as you like. `npm run images:dry` shows what it would do first.

## Naming

Lower case, no spaces. A descriptive name becomes the caption on the tile —
`smoky-makeup.jpeg` shows as **Smoky Makeup**. A name that says nothing
(`p1.jpeg`, `pedicure-3.jpeg`) falls back to the folder's label and its
position, e.g. **Pedicure 03**.

`.jpg`, `.jpeg`, `.png`, `.webp` and `.avif` are picked up; anything else is
ignored. Keep each file under ~300 KB — they are served as-is.

## Adding a new folder

Register it in `gallery_folders` (folder, slug, label, and the
`service_categories` row it books into, or NULL if the salon does not sell it
yet), then run the scanner. It appears as a filter chip on the gallery page in
`sort_order`.

## While a folder is empty

Every photo slot falls back to the gradient tile the site shipped with, so a
missing file is never a broken image. `team/` is empty today — the stylist
cards show initials on gold and pink until portraits land there.
