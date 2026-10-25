/* ==========================================================================
   Princess Beauty Salon — data.js
   SINGLE SOURCE OF MOCK DATA + shared helpers.
   PHASE 2 NOTE: every array below maps 1:1 to a MySQL table. When the PHP
   backend lands, replace each array with a fetch() to /api/<name>.php that
   returns the exact same JSON shape — no UI code needs to change.
   ========================================================================== */
(function (w) {
  'use strict';

  var PBS = w.PBS = w.PBS || {};

  /* ----------------------------------------------------------------------
     SALON PROFILE  (→ table: settings)
     ---------------------------------------------------------------------- */
  PBS.salon = {
    name: 'Princess Beauty Salon',
    tagline: 'Look Good. Feel Good. Be You.',
    phone: '+237 6 78 45 12 90',
    whatsapp: '237678451290',
    email: 'hello@princessbeautysalon.cm',
    address: 'Carrefour Obili, Yaoundé, Centre Region, Cameroon',
    currency: 'FCFA',
    currencyCode: 'XAF',
    taxRate: 0,
    socials: {
      instagram: '@princessbeauty_cm',
      tiktok: '@princessbeauty_cm',
      facebook: 'PrincessBeautySalonCM'
    },
    hours: [
      { day: 'Monday',    open: '09:00', close: '19:00', closed: false },
      { day: 'Tuesday',   open: '09:00', close: '19:00', closed: false },
      { day: 'Wednesday', open: '09:00', close: '19:00', closed: false },
      { day: 'Thursday',  open: '09:00', close: '20:00', closed: false },
      { day: 'Friday',    open: '09:00', close: '20:00', closed: false },
      { day: 'Saturday',  open: '08:00', close: '21:00', closed: false },
      { day: 'Sunday',    open: '12:00', close: '17:00', closed: false }
    ]
  };

  /* ----------------------------------------------------------------------
     HELPERS
     ---------------------------------------------------------------------- */
  PBS.money = function (n, opts) {
    opts = opts || {};
    var v = Math.round(Number(n) || 0);
    var s = v.toLocaleString('en-US');
    return opts.bare ? s : s + ' ' + PBS.salon.currency;
  };
  PBS.moneyShort = function (n) {
    var v = Number(n) || 0;
    if (v >= 1000000) return (v / 1000000).toFixed(v >= 10000000 ? 0 : 1) + 'M';
    if (v >= 1000) return Math.round(v / 1000) + 'k';
    return String(v);
  };
  PBS.dur = function (mins) {
    var h = Math.floor(mins / 60), m = mins % 60;
    return (h ? h + 'h' : '') + (h && m ? ' ' : '') + (m ? m + 'min' : '');
  };
  PBS.initials = function (name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map(function (p) {
      return p[0].toUpperCase();
    }).join('');
  };
  PBS.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /* ----------------------------------------------------------------------
     ICON LIBRARY — gold line icons for the circular logo badges + UI glyphs
     ---------------------------------------------------------------------- */
  var I = PBS.ICONS = {
    /* service category icons (logo motif) */
    scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88M14.47 14.48L20 20M8.12 8.12L12 12"/>',
    braid: '<path d="M9 2c0 3 6 3 6 6s-6 3-6 6 6 3 6 6"/><path d="M15 2c0 3-6 3-6 6s6 3 6 6-6 3-6 6"/>',
    nails: '<path d="M8 12V6a4 4 0 0 1 8 0v6"/><path d="M8 12v3a4 4 0 0 0 8 0v-3z"/><path d="M6 20h12"/><path d="M10 9h4"/>',
    lashes: '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/><path d="M5 6l1.6 2M12 3.5V6M19 6l-1.6 2"/>',
    spa: '<path d="M12 21c0-4.5 3-8 7-8-.5 4.5-3.5 8-7 8z"/><path d="M12 21c0-4.5-3-8-7-8 .5 4.5 3.5 8 7 8z"/><path d="M12 21c-2-3.5-2-8 0-11 2 3 2 7.5 0 11z"/><path d="M12 10V3"/>',
    crown: '<path d="M3 8l3.5 4L12 5l5.5 7L21 8l-1.6 10H4.6z"/><circle cx="12" cy="3" r="1"/>',
    /* ui glyphs */
    home: '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 3-5.8 6.5-5.8S15.5 16.4 15.5 20"/><path d="M17 7.2a3.5 3.5 0 0 1 0 6.6M18 20c0-2.6-.9-4.3-2.3-5.3"/>',
    image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="M21 16l-5-5-9 9"/>',
    phone: '<path d="M5 3h4l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2.2 2A17 17 0 0 1 3 5.2 2 2 0 0 1 5 3z"/>',
    mail: '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/>',
    pin: '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
    star: '<path d="M12 3l2.7 5.7 6.3.8-4.6 4.3 1.2 6.2L12 17l-5.6 3 1.2-6.2L3 9.5l6.3-.8z"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>',
    bell: '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10.5 19a1.8 1.8 0 0 0 3 0"/>',
    cash: '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.8"/><path d="M6 10v4M18 10v4"/>',
    chart: '<path d="M3 3v18h18"/><path d="M7 15l4-5 3.5 3L21 6"/>',
    pie: '<path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M14.5 3.4A9 9 0 0 1 20.6 9.5H14.5z"/>',
    box: '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
    tag: '<path d="M3 11.5V4a1 1 0 0 1 1-1h7.5L21 12.5 12.5 21z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.9 19.4l-.1.1A2 2 0 1 1 5 16.7l.1-.1A1.6 1.6 0 0 0 4 13.9H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 7.9l-.1-.1A2 2 0 1 1 7.3 5l.1.1a1.6 1.6 0 0 0 2.7-1.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1A2 2 0 1 1 19.7 7l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.2z"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
    check: '<path d="M4 12.5l5.5 5.5L20 6.5"/>',
    x: '<path d="M6 6l12 12M18 6L6 18"/>',
    arrowUp: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    arrowDown: '<path d="M12 5v14M19 12l-7 7-7-7"/>',
    arrowRight: '<path d="M5 12h14M13 5l7 7-7 7"/>',
    chevL: '<path d="M15 5l-7 7 7 7"/>',
    chevR: '<path d="M9 5l7 7-7 7"/>',
    download: '<path d="M12 3v12M7 11l5 5 5-5"/><path d="M4 19.5h16"/>',
    filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
    alert: '<path d="M12 3l9.5 17H2.5z"/><path d="M12 9.5v4M12 17h.01"/>',
    receipt: '<path d="M5 3h14v18l-2.3-1.6L14.4 21l-2.4-1.6L9.6 21l-2.3-1.6L5 21z"/><path d="M9 8h6M9 12h6"/>',
    wallet: '<path d="M3 7a2 2 0 0 1 2-2h12v4"/><rect x="3" y="7" width="18" height="12" rx="2"/><circle cx="17" cy="13" r="1.3"/>',
    instagram: '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/>',
    facebook: '<path d="M14 8.5V6.8c0-.8.4-1.3 1.4-1.3H17V2.5h-2.6C11.8 2.5 11 4 11 6.4v2.1H9v3h2V21.5h3V11.5h2.3l.4-3z"/>',
    tiktok: '<path d="M15 3c.6 2.6 2.2 4 4.6 4.2v3.1c-1.7.1-3.3-.4-4.6-1.4v6.4A6.3 6.3 0 1 1 8.7 9v3.2A3.1 3.1 0 1 0 12 15.3V3z"/>',
    whatsapp: '<path d="M20.5 11.6a8.4 8.4 0 0 1-12.4 7.4L3.5 20.5l1.6-4.4a8.4 8.4 0 1 1 15.4-4.5z" stroke-width="1.6"/><path d="M9 8.5c.3 3 2.5 5.2 5.5 5.6l1-1.4 2 1-.6 1.6c-3.5.6-7.7-3.2-8.4-6.6L9.7 8z" stroke-width="1.4"/>'
  };
  /* These two are solid glyphs, not line icons — they must be filled, never stroked.
     The .ico-filled class flips fill/stroke and beats any inherited svg rule. */
  var FILLED = { facebook: 1, tiktok: 1 };

  PBS.icon = function (name, cls) {
    var p = I[name] || I.crown;
    var solid = FILLED[name];
    var cn = (solid ? 'ico-filled ' : '') + (cls || '');
    /* width/height are a FALLBACK: without them an SVG that lands in a context
       with no CSS size rule renders at the browser default 300x150. Any CSS
       rule (.btn svg, .icon-badge svg, …) still overrides these attributes. */
    return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"' +
      (cn.trim() ? ' class="' + cn.trim() + '"' : '') +
      ' fill="' + (solid ? 'currentColor' : 'none') + '"' +
      ' stroke="' + (solid ? 'none' : 'currentColor') + '"' +
      ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
  };

  /* ----------------------------------------------------------------------
     SERVICE CATEGORIES  (→ table: service_categories)
     ---------------------------------------------------------------------- */
  PBS.categories = [
    { id: 'hair',     name: 'Hair Cuts & Styling',  short: 'Hair',     icon: 'scissors', color: '#C9A227',
      blurb: 'Precision cuts, silk presses, blowouts and glam styling for every hair type.' },
    { id: 'braids',   name: 'Braids & Plating',     short: 'Braids',   icon: 'braid',    color: '#E91E8C',
      blurb: 'Knotless braids, cornrows, twists and protective styles that last for weeks.' },
    { id: 'nails',    name: 'Nails & Nail Art',     short: 'Nails',    icon: 'nails',    color: '#2F6FED',
      blurb: 'Manicures, pedicures, gel, acrylics and hand-painted nail art.' },
    { id: 'facials',  name: 'Facials & Lashes',     short: 'Facials',  icon: 'lashes',   color: '#12A150',
      blurb: 'Deep-cleanse facials, brow shaping, tinting and classic to volume lash sets.' },
    { id: 'massage',  name: 'Massage & Spa',        short: 'Spa',      icon: 'spa',      color: '#7B54D3',
      blurb: 'Relaxing full-body massage, hot stone therapy and body scrubs.' }
  ];
  PBS.category = function (id) {
    return PBS.categories.filter(function (c) { return c.id === id; })[0] || PBS.categories[0];
  };

  /* ----------------------------------------------------------------------
     SERVICES  (→ table: services)
     price = FCFA, duration = minutes, uses = inventory product ids consumed
     ---------------------------------------------------------------------- */
  PBS.services = [
    { id: 'SV-01', cat: 'hair',    name: 'Signature Wash & Blow Dry', price: 6000,  duration: 45,  popular: true,  uses: ['P-01', 'P-03'], desc: 'Deep cleanse, condition and a smooth bouncy blow dry.' },
    { id: 'SV-02', cat: 'hair',    name: 'Precision Cut & Style',     price: 9000,  duration: 60,  popular: true,  uses: ['P-01'],         desc: 'Consultation, shaping cut and finish styling.' },
    { id: 'SV-03', cat: 'hair',    name: 'Silk Press',                price: 12000, duration: 90,  popular: true,  uses: ['P-01', 'P-02'], desc: 'Sleek, frizz-free press with heat protection.' },
    { id: 'SV-04', cat: 'hair',    name: 'Relaxer / Retouch',         price: 15000, duration: 105, popular: false, uses: ['P-04'],         desc: 'Gentle relaxer application with scalp protection.' },
    { id: 'SV-05', cat: 'hair',    name: 'Bridal & Event Styling',    price: 35000, duration: 150, popular: false, uses: ['P-02', 'P-03'], desc: 'Full glam updo with accessories for your big day.' },

    { id: 'SV-06', cat: 'braids',  name: 'Knotless Box Braids',       price: 25000, duration: 240, popular: true,  uses: ['P-05', 'P-06'], desc: 'Lightweight knotless braids, medium length included.' },
    { id: 'SV-07', cat: 'braids',  name: 'Cornrows / Ghana Weaving',  price: 12000, duration: 120, popular: true,  uses: ['P-05'],         desc: 'Clean straight-back or patterned cornrows.' },
    { id: 'SV-08', cat: 'braids',  name: 'Twist Out / Passion Twists',price: 20000, duration: 180, popular: false, uses: ['P-06'],         desc: 'Soft, full passion twists with a natural finish.' },
    { id: 'SV-09', cat: 'braids',  name: 'Kids Braiding',             price: 8000,  duration: 90,  popular: false, uses: ['P-05'],         desc: 'Gentle, patient styling for little princesses.' },
    { id: 'SV-10', cat: 'braids',  name: 'Wig Install & Styling',     price: 18000, duration: 120, popular: true,  uses: ['P-07'],         desc: 'Lace melt install, customisation and styling.' },

    { id: 'SV-11', cat: 'nails',   name: 'Classic Manicure',          price: 5000,  duration: 45,  popular: true,  uses: ['P-08', 'P-10'], desc: 'Shape, cuticle care, buff and polish.' },
    { id: 'SV-12', cat: 'nails',   name: 'Spa Pedicure',              price: 7000,  duration: 60,  popular: true,  uses: ['P-08', 'P-11'], desc: 'Soak, scrub, callus care, massage and polish.' },
    { id: 'SV-13', cat: 'nails',   name: 'Gel Overlay',               price: 9000,  duration: 60,  popular: true,  uses: ['P-09'],         desc: 'Long-lasting gel colour with high shine.' },
    { id: 'SV-14', cat: 'nails',   name: 'Acrylic Full Set',          price: 15000, duration: 105, popular: false, uses: ['P-09', 'P-12'], desc: 'Sculpted acrylic extensions, any length.' },
    { id: 'SV-15', cat: 'nails',   name: 'Custom Nail Art (per set)', price: 6000,  duration: 45,  popular: false, uses: ['P-10'],         desc: 'Hand-painted designs, chrome, gems and French tips.' },

    { id: 'SV-16', cat: 'facials', name: 'Deep Cleanse Facial',       price: 15000, duration: 60,  popular: true,  uses: ['P-13'],         desc: 'Cleanse, exfoliate, extract, mask and hydrate.' },
    { id: 'SV-17', cat: 'facials', name: 'Glow Brightening Facial',   price: 20000, duration: 75,  popular: false, uses: ['P-13', 'P-14'], desc: 'Vitamin C treatment for even, glowing skin.' },
    { id: 'SV-18', cat: 'facials', name: 'Classic Lash Extensions',   price: 18000, duration: 90,  popular: true,  uses: ['P-15'],         desc: 'Natural 1:1 lash set, lasts 3-4 weeks.' },
    { id: 'SV-19', cat: 'facials', name: 'Volume Lash Set',           price: 25000, duration: 120, popular: true,  uses: ['P-15'],         desc: 'Full, fluffy volume fans for maximum drama.' },
    { id: 'SV-20', cat: 'facials', name: 'Brow Shaping & Tint',       price: 6000,  duration: 30,  popular: false, uses: ['P-14'],         desc: 'Threading or waxing plus tint to frame your face.' },

    { id: 'SV-21', cat: 'massage', name: 'Full Body Relaxation',      price: 20000, duration: 60,  popular: true,  uses: ['P-16'],         desc: 'Swedish-style full body massage to unwind.' },
    { id: 'SV-22', cat: 'massage', name: 'Hot Stone Therapy',         price: 28000, duration: 90,  popular: false, uses: ['P-16'],         desc: 'Heated basalt stones to melt deep tension.' },
    { id: 'SV-23', cat: 'massage', name: 'Back, Neck & Shoulders',    price: 12000, duration: 30,  popular: true,  uses: ['P-16'],         desc: 'Targeted pressure where you hold stress most.' },
    { id: 'SV-24', cat: 'massage', name: 'Body Scrub & Polish',       price: 18000, duration: 60,  popular: false, uses: ['P-17'],         desc: 'Exfoliating scrub followed by a shea butter finish.' }
  ];
  PBS.service = function (id) {
    return PBS.services.filter(function (s) { return s.id === id; })[0];
  };

  /* ----------------------------------------------------------------------
     STAFF  (→ table: staff)   commission = % of service revenue
     ---------------------------------------------------------------------- */
  PBS.staff = [
    { id: 'ST-01', name: 'Aïcha Ngo',      role: 'Owner / Master Stylist', roleKey: 'owner',
      cats: ['hair', 'braids'], commission: 0, rating: 5.0, reviews: 214, since: '2019',
      bio: 'Founder of Princess Beauty Salon. 12 years behind the chair, specialising in silk presses and bridal glam.',
      hours: 'Mon-Sat · 09:00 - 19:00', tone: 'gold' },
    { id: 'ST-02', name: 'Blessing Etonde', role: 'Senior Braider',        roleKey: 'staff',
      cats: ['braids'], commission: 40, rating: 4.9, reviews: 168, since: '2020',
      bio: 'Knotless braid specialist. Fast hands, zero tension, styles that last six weeks.',
      hours: 'Mon-Sat · 08:00 - 18:00', tone: 'pink' },
    { id: 'ST-03', name: 'Grace Mbah',      role: 'Nail Technician',       roleKey: 'staff',
      cats: ['nails'], commission: 35, rating: 4.8, reviews: 142, since: '2021',
      bio: 'Nail artist with a steady hand for chrome, French tips and hand-painted detail.',
      hours: 'Tue-Sun · 10:00 - 19:00', tone: 'gold' },
    { id: 'ST-04', name: 'Linda Fotso',     role: 'Esthetician & Lash Pro', roleKey: 'staff',
      cats: ['facials'], commission: 38, rating: 5.0, reviews: 96, since: '2022',
      bio: 'Certified esthetician. Volume lash sets and problem-skin facials are her thing.',
      hours: 'Mon-Fri · 09:00 - 18:00', tone: 'pink' },
    { id: 'ST-05', name: 'Marcel Tabi',     role: 'Massage Therapist',     roleKey: 'staff',
      cats: ['massage'], commission: 42, rating: 4.9, reviews: 78, since: '2022',
      bio: 'Deep tissue and hot stone therapist trained in Douala. Ten years of practice.',
      hours: 'Wed-Sun · 11:00 - 20:00', tone: 'gold' },
    { id: 'ST-06', name: 'Sandra Ekwe',     role: 'Junior Stylist',        roleKey: 'staff',
      cats: ['hair', 'nails'], commission: 30, rating: 4.7, reviews: 51, since: '2024',
      bio: 'Rising talent — blow dries, gel overlays and a great playlist.',
      hours: 'Mon-Sat · 12:00 - 20:00', tone: 'pink' }
  ];
  PBS.staffById = function (id) {
    return PBS.staff.filter(function (s) { return s.id === id; })[0];
  };

  /* ----------------------------------------------------------------------
     CLIENTS  (→ table: clients)
     ---------------------------------------------------------------------- */
  PBS.clients = [
    { id: 'CL-001', name: 'Marlyse Nkeng',   phone: '+237 6 90 11 22 33', email: 'marlyse.n@gmail.com',   visits: 24, spend: 486000, lastVisit: '2026-08-18', fav: 'braids',  favStaff: 'ST-02', joined: '2023-02-14', notes: 'Sensitive scalp — no tight edges. Prefers Saturday mornings.', tier: 'VIP' },
    { id: 'CL-002', name: 'Josiane Bella',   phone: '+237 6 55 78 09 12', email: 'josiane.bella@yahoo.fr', visits: 18, spend: 342000, lastVisit: '2026-08-19', fav: 'nails',   favStaff: 'ST-03', joined: '2023-06-02', notes: 'Loves chrome finishes. Allergic to acetone-heavy remover.', tier: 'VIP' },
    { id: 'CL-003', name: 'Ndeye Fall',      phone: '+237 6 77 34 56 78', email: 'ndeye.fall@gmail.com',  visits: 15, spend: 298000, lastVisit: '2026-08-15', fav: 'facials', favStaff: 'ST-04', joined: '2023-09-21', notes: 'Combination skin, monthly deep cleanse.', tier: 'VIP' },
    { id: 'CL-004', name: 'Chantal Mbarga',  phone: '+237 6 99 45 67 01', email: 'chantal.m@gmail.com',   visits: 12, spend: 224000, lastVisit: '2026-08-12', fav: 'hair',    favStaff: 'ST-01', joined: '2024-01-08', notes: 'Silk press every 3 weeks. Always books ahead.', tier: 'Regular' },
    { id: 'CL-005', name: 'Rachel Ayuk',     phone: '+237 6 71 90 88 44', email: 'rachel.ayuk@gmail.com', visits: 11, spend: 205000, lastVisit: '2026-08-20', fav: 'braids',  favStaff: 'ST-02', joined: '2024-03-17', notes: 'Bringing her daughter for kids braiding too.', tier: 'Regular' },
    { id: 'CL-006', name: 'Sylvie Ondoa',    phone: '+237 6 82 13 45 67', email: 'sylvie.o@outlook.com',  visits: 9,  spend: 178000, lastVisit: '2026-08-11', fav: 'massage', favStaff: 'ST-05', joined: '2024-05-29', notes: 'Prefers evening slots after work.', tier: 'Regular' },
    { id: 'CL-007', name: 'Brenda Achu',     phone: '+237 6 64 22 90 15', email: 'brenda.achu@gmail.com', visits: 8,  spend: 152000, lastVisit: '2026-08-09', fav: 'nails',   favStaff: 'ST-03', joined: '2024-08-03', notes: '', tier: 'Regular' },
    { id: 'CL-008', name: 'Emilienne Tchana',phone: '+237 6 58 76 34 20', email: 'emi.tchana@gmail.com',  visits: 7,  spend: 141000, lastVisit: '2026-08-16', fav: 'facials', favStaff: 'ST-04', joined: '2024-11-11', notes: 'Lash fills every 3 weeks.', tier: 'Regular' },
    { id: 'CL-009', name: 'Patience Nkemi',  phone: '+237 6 93 44 12 87', email: 'patience.n@gmail.com',  visits: 5,  spend: 96000,  lastVisit: '2026-08-14', fav: 'hair',    favStaff: 'ST-06', joined: '2025-03-22', notes: '', tier: 'Regular' },
    { id: 'CL-010', name: 'Yolande Kome',    phone: '+237 6 76 55 21 09', email: 'yolande.k@gmail.com',   visits: 3,  spend: 58000,  lastVisit: '2026-08-17', fav: 'braids',  favStaff: 'ST-02', joined: '2026-06-30', notes: 'Referred by Marlyse Nkeng.', tier: 'New' },
    { id: 'CL-011', name: 'Divine Awah',     phone: '+237 6 51 08 77 63', email: 'divine.awah@gmail.com', visits: 2,  spend: 33000,  lastVisit: '2026-08-19', fav: 'nails',   favStaff: 'ST-03', joined: '2026-07-28', notes: 'First-time gel client.', tier: 'New' },
    { id: 'CL-012', name: 'Nadège Simo',     phone: '+237 6 88 90 34 55', email: 'nadege.simo@gmail.com', visits: 1,  spend: 20000,  lastVisit: '2026-08-20', fav: 'massage', favStaff: 'ST-05', joined: '2026-08-20', notes: 'Walk-in, converted to booking.', tier: 'New' }
  ];
  PBS.clientById = function (id) {
    return PBS.clients.filter(function (c) { return c.id === id; })[0];
  };

  /* ----------------------------------------------------------------------
     APPOINTMENTS  (→ table: appointments + appointment_items)
     status: pending | confirmed | completed | cancelled | no-show
     pay:    cash | momo | om | card | unpaid
     ---------------------------------------------------------------------- */
  PBS.appointments = [
    // ---- TODAY (2026-08-20) ----
    { id: 'AP-1042', client: 'CL-005', staff: 'ST-02', svc: ['SV-06'],          date: '2026-08-20', time: '08:30', status: 'completed', pay: 'momo',   addons: 2000, tip: 1000, note: 'Knotless, waist length' },
    { id: 'AP-1043', client: 'CL-002', staff: 'ST-03', svc: ['SV-13', 'SV-15'], date: '2026-08-20', time: '09:00', status: 'completed', pay: 'cash',   addons: 0,    tip: 500,  note: 'Chrome accent nails' },
    { id: 'AP-1044', client: 'CL-012', staff: 'ST-05', svc: ['SV-21'],          date: '2026-08-20', time: '10:00', status: 'completed', pay: 'om',     addons: 0,    tip: 2000, note: 'First visit' },
    { id: 'AP-1045', client: 'CL-004', staff: 'ST-01', svc: ['SV-03'],          date: '2026-08-20', time: '11:00', status: 'completed', pay: 'card',   addons: 1500, tip: 0,    note: '' },
    { id: 'AP-1046', client: 'CL-011', staff: 'ST-03', svc: ['SV-11'],          date: '2026-08-20', time: '12:00', status: 'completed', pay: 'momo',   addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1047', client: 'CL-008', staff: 'ST-04', svc: ['SV-18'],          date: '2026-08-20', time: '13:00', status: 'confirmed', pay: 'unpaid', addons: 0,    tip: 0,    note: 'Lash fill' },
    { id: 'AP-1048', client: 'CL-001', staff: 'ST-02', svc: ['SV-07'],          date: '2026-08-20', time: '14:00', status: 'confirmed', pay: 'unpaid', addons: 0,    tip: 0,    note: 'Loose at the edges please' },
    { id: 'AP-1049', client: 'CL-003', staff: 'ST-04', svc: ['SV-16'],          date: '2026-08-20', time: '15:30', status: 'confirmed', pay: 'unpaid', addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1050', client: 'CL-009', staff: 'ST-06', svc: ['SV-01'],          date: '2026-08-20', time: '16:00', status: 'pending',   pay: 'unpaid', addons: 0,    tip: 0,    note: 'Awaiting confirmation' },
    { id: 'AP-1051', client: 'CL-006', staff: 'ST-05', svc: ['SV-23'],          date: '2026-08-20', time: '17:30', status: 'confirmed', pay: 'unpaid', addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1052', client: 'CL-007', staff: 'ST-03', svc: ['SV-12'],          date: '2026-08-20', time: '18:00', status: 'cancelled', pay: 'unpaid', addons: 0,    tip: 0,    note: 'Client rescheduling to Friday' },
    { id: 'AP-1053', client: 'CL-010', staff: 'ST-02', svc: ['SV-09'],          date: '2026-08-20', time: '18:30', status: 'no-show',   pay: 'unpaid', addons: 0,    tip: 0,    note: 'No answer on phone' },

    // ---- YESTERDAY & THIS WEEK (completed history) ----
    { id: 'AP-1030', client: 'CL-002', staff: 'ST-03', svc: ['SV-14'],          date: '2026-08-19', time: '10:00', status: 'completed', pay: 'momo',   addons: 3000, tip: 1000, note: '' },
    { id: 'AP-1031', client: 'CL-011', staff: 'ST-03', svc: ['SV-13'],          date: '2026-08-19', time: '12:30', status: 'completed', pay: 'cash',   addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1032', client: 'CL-001', staff: 'ST-02', svc: ['SV-06'],          date: '2026-08-19', time: '09:00', status: 'completed', pay: 'om',     addons: 2000, tip: 2000, note: '' },
    { id: 'AP-1033', client: 'CL-006', staff: 'ST-05', svc: ['SV-22'],          date: '2026-08-19', time: '15:00', status: 'completed', pay: 'card',   addons: 0,    tip: 3000, note: '' },
    { id: 'AP-1034', client: 'CL-004', staff: 'ST-01', svc: ['SV-02'],          date: '2026-08-18', time: '11:00', status: 'completed', pay: 'cash',   addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1035', client: 'CL-001', staff: 'ST-01', svc: ['SV-03'],          date: '2026-08-18', time: '13:00', status: 'completed', pay: 'momo',   addons: 1500, tip: 1000, note: '' },
    { id: 'AP-1036', client: 'CL-003', staff: 'ST-04', svc: ['SV-19'],          date: '2026-08-18', time: '14:00', status: 'completed', pay: 'momo',   addons: 0,    tip: 1500, note: '' },
    { id: 'AP-1037', client: 'CL-008', staff: 'ST-04', svc: ['SV-17'],          date: '2026-08-17', time: '10:30', status: 'completed', pay: 'cash',   addons: 0,    tip: 0,    note: '' },
    { id: 'AP-1038', client: 'CL-010', staff: 'ST-02', svc: ['SV-08'],          date: '2026-08-17', time: '09:00', status: 'completed', pay: 'om',     addons: 0,    tip: 1000, note: '' },
    { id: 'AP-1039', client: 'CL-009', staff: 'ST-06', svc: ['SV-01', 'SV-11'], date: '2026-08-16', time: '15:00', status: 'completed', pay: 'cash',   addons: 0,    tip: 500,  note: '' },
    { id: 'AP-1040', client: 'CL-005', staff: 'ST-02', svc: ['SV-10'],          date: '2026-08-16', time: '11:00', status: 'completed', pay: 'momo',   addons: 2500, tip: 0,    note: '' },
    { id: 'AP-1041', client: 'CL-007', staff: 'ST-03', svc: ['SV-12', 'SV-11'], date: '2026-08-15', time: '16:00', status: 'completed', pay: 'card',   addons: 0,    tip: 1000, note: '' },
    { id: 'AP-1029', client: 'CL-012', staff: 'ST-01', svc: ['SV-05'],          date: '2026-08-15', time: '08:00', status: 'completed', pay: 'momo',   addons: 5000, tip: 5000, note: 'Bridal party of 3' },
    { id: 'AP-1028', client: 'CL-003', staff: 'ST-04', svc: ['SV-20'],          date: '2026-08-14', time: '17:00', status: 'completed', pay: 'cash',   addons: 0,    tip: 0,    note: '' },

    // ---- UPCOMING ----
    { id: 'AP-1054', client: 'CL-002', staff: 'ST-03', svc: ['SV-13'],          date: '2026-08-21', time: '10:00', status: 'confirmed', pay: 'unpaid', addons: 0, tip: 0, note: '' },
    { id: 'AP-1055', client: 'CL-004', staff: 'ST-01', svc: ['SV-03'],          date: '2026-08-21', time: '12:00', status: 'confirmed', pay: 'unpaid', addons: 0, tip: 0, note: '' },
    { id: 'AP-1056', client: 'CL-001', staff: 'ST-02', svc: ['SV-06'],          date: '2026-08-22', time: '08:30', status: 'confirmed', pay: 'unpaid', addons: 0, tip: 0, note: 'Full day booking' },
    { id: 'AP-1057', client: 'CL-006', staff: 'ST-05', svc: ['SV-21'],          date: '2026-08-22', time: '16:00', status: 'pending',   pay: 'unpaid', addons: 0, tip: 0, note: '' },
    { id: 'AP-1058', client: 'CL-008', staff: 'ST-04', svc: ['SV-19'],          date: '2026-08-23', time: '11:00', status: 'confirmed', pay: 'unpaid', addons: 0, tip: 0, note: '' },
    { id: 'AP-1059', client: 'CL-005', staff: 'ST-06', svc: ['SV-01'],          date: '2026-08-24', time: '13:00', status: 'pending',   pay: 'unpaid', addons: 0, tip: 0, note: '' },

    // ---- OUTSTANDING (completed but not paid) ----
    { id: 'AP-1020', client: 'CL-007', staff: 'ST-03', svc: ['SV-14'],          date: '2026-08-13', time: '14:00', status: 'completed', pay: 'unpaid', addons: 0, tip: 0, note: 'Promised to pay by MoMo Friday' },
    { id: 'AP-1021', client: 'CL-009', staff: 'ST-06', svc: ['SV-13'],          date: '2026-08-11', time: '15:30', status: 'completed', pay: 'unpaid', addons: 0, tip: 0, note: 'Balance 4,000 outstanding' },
    { id: 'AP-1022', client: 'CL-010', staff: 'ST-02', svc: ['SV-07'],          date: '2026-08-08', time: '10:00', status: 'completed', pay: 'unpaid', addons: 0, tip: 0, note: 'Invoice sent on WhatsApp' }
  ];

  /* appointment computed helpers */
  PBS.apptTotal = function (a) {
    var svc = (a.svc || []).reduce(function (t, id) {
      var s = PBS.service(id); return t + (s ? s.price : 0);
    }, 0);
    return svc + (a.addons || 0) + (a.tip || 0);
  };
  PBS.apptServiceTotal = function (a) {
    return (a.svc || []).reduce(function (t, id) {
      var s = PBS.service(id); return t + (s ? s.price : 0);
    }, 0);
  };
  PBS.apptDuration = function (a) {
    return (a.svc || []).reduce(function (t, id) {
      var s = PBS.service(id); return t + (s ? s.duration : 0);
    }, 0);
  };
  PBS.apptNames = function (a) {
    return (a.svc || []).map(function (id) {
      var s = PBS.service(id); return s ? s.name : id;
    }).join(' + ');
  };
  PBS.apptCat = function (a) {
    var s = PBS.service((a.svc || [])[0]);
    return s ? s.cat : 'hair';
  };

  PBS.STATUS = {
    pending:   { label: 'Pending',   cls: 'badge-warning' },
    confirmed: { label: 'Confirmed', cls: 'badge-info' },
    completed: { label: 'Completed', cls: 'badge-success' },
    cancelled: { label: 'Cancelled', cls: 'badge-danger' },
    'no-show': { label: 'No-show',   cls: 'badge' }
  };
  PBS.PAY = {
    cash:   { label: 'Cash',         cls: 'badge-plain badge' },
    momo:   { label: 'MTN MoMo',     cls: 'badge-plain badge-warning' },
    om:     { label: 'Orange Money', cls: 'badge-plain badge-danger' },
    card:   { label: 'Card',         cls: 'badge-plain badge-info' },
    unpaid: { label: 'Unpaid',       cls: 'badge-plain badge-danger' }
  };

  /* ----------------------------------------------------------------------
     INVENTORY  (→ table: products)
     ---------------------------------------------------------------------- */
  PBS.products = [
    { id: 'P-01', name: 'Shampoo (Sulfate-free) 1L',   cat: 'hair',    qty: 14, unit: 'bottle', cost: 4500,  sell: 0,     reorder: 6,  supplier: 'Beauté Import CM', retail: false },
    { id: 'P-02', name: 'Heat Protectant Spray',       cat: 'hair',    qty: 3,  unit: 'bottle', cost: 3800,  sell: 7000,  reorder: 5,  supplier: 'Beauté Import CM', retail: true  },
    { id: 'P-03', name: 'Deep Conditioner 500ml',      cat: 'hair',    qty: 9,  unit: 'jar',    cost: 5200,  sell: 9000,  reorder: 4,  supplier: 'Beauté Import CM', retail: true  },
    { id: 'P-04', name: 'Relaxer Kit (Regular)',       cat: 'hair',    qty: 2,  unit: 'kit',    cost: 6000,  sell: 0,     reorder: 5,  supplier: 'Yaoundé Wholesale', retail: false },
    { id: 'P-05', name: 'Braiding Hair — Kanekalon',   cat: 'braids',  qty: 42, unit: 'pack',   cost: 1200,  sell: 2500,  reorder: 20, supplier: 'Marché Mokolo',     retail: true  },
    { id: 'P-06', name: 'Pre-stretched Braid Hair XL', cat: 'braids',  qty: 8,  unit: 'pack',   cost: 2200,  sell: 4000,  reorder: 15, supplier: 'Marché Mokolo',     retail: true  },
    { id: 'P-07', name: 'Lace Glue / Melt Kit',        cat: 'braids',  qty: 6,  unit: 'kit',    cost: 4800,  sell: 8500,  reorder: 4,  supplier: 'Douala Hair Supply', retail: true  },
    { id: 'P-08', name: 'Cuticle Oil 100ml',           cat: 'nails',   qty: 11, unit: 'bottle', cost: 2100,  sell: 4000,  reorder: 5,  supplier: 'Nail Pro CM',       retail: true  },
    { id: 'P-09', name: 'Gel Polish Assorted',         cat: 'nails',   qty: 26, unit: 'bottle', cost: 2800,  sell: 5500,  reorder: 12, supplier: 'Nail Pro CM',       retail: true  },
    { id: 'P-10', name: 'Nail Art Kit (gems, chrome)', cat: 'nails',   qty: 4,  unit: 'kit',    cost: 7500,  sell: 0,     reorder: 3,  supplier: 'Nail Pro CM',       retail: false },
    { id: 'P-11', name: 'Pedicure Salt & Scrub',       cat: 'nails',   qty: 7,  unit: 'tub',    cost: 3200,  sell: 6000,  reorder: 4,  supplier: 'Spa Essentials',    retail: true  },
    { id: 'P-12', name: 'Acrylic Powder 250g',         cat: 'nails',   qty: 1,  unit: 'jar',    cost: 9000,  sell: 0,     reorder: 3,  supplier: 'Nail Pro CM',       retail: false },
    { id: 'P-13', name: 'Facial Cleanser Pro 500ml',   cat: 'facials', qty: 5,  unit: 'bottle', cost: 8000,  sell: 14000, reorder: 3,  supplier: 'DermaLine',         retail: true  },
    { id: 'P-14', name: 'Vitamin C Serum',             cat: 'facials', qty: 12, unit: 'bottle', cost: 6500,  sell: 12000, reorder: 5,  supplier: 'DermaLine',         retail: true  },
    { id: 'P-15', name: 'Lash Tray + Adhesive',        cat: 'facials', qty: 2,  unit: 'set',    cost: 11000, sell: 0,     reorder: 4,  supplier: 'Lash Lab Douala',   retail: false },
    { id: 'P-16', name: 'Massage Oil — Sweet Almond',  cat: 'massage', qty: 6,  unit: 'bottle', cost: 5500,  sell: 10000, reorder: 4,  supplier: 'Spa Essentials',    retail: true  },
    { id: 'P-17', name: 'Shea Body Butter 500g',       cat: 'massage', qty: 18, unit: 'jar',    cost: 3000,  sell: 6500,  reorder: 6,  supplier: 'Local Co-op',       retail: true  },
    { id: 'P-18', name: 'Disposable Towels (pack 50)', cat: 'general', qty: 3,  unit: 'pack',   cost: 4000,  sell: 0,     reorder: 6,  supplier: 'Yaoundé Wholesale', retail: false }
  ];
  PBS.productById = function (id) {
    return PBS.products.filter(function (p) { return p.id === id; })[0];
  };
  PBS.stockState = function (p) {
    if (p.qty === 0) return 'out';
    if (p.qty <= p.reorder) return 'low';
    return 'ok';
  };
  PBS.lowStock = function () {
    return PBS.products.filter(function (p) { return p.qty <= p.reorder; })
      .sort(function (a, b) { return (a.qty / a.reorder) - (b.qty / b.reorder); });
  };
  PBS.stockValue = function () {
    return PBS.products.reduce(function (t, p) { return t + p.qty * p.cost; }, 0);
  };

  /* STOCK MOVEMENTS  (→ table: stock_movements) type: in | out | sale | adjust */
  PBS.stockLog = [
    { id: 'SM-231', date: '2026-08-20', product: 'P-09', type: 'out',   qty: 2,  ref: 'AP-1043', note: 'Used in Gel Overlay',        by: 'ST-03' },
    { id: 'SM-230', date: '2026-08-20', product: 'P-05', type: 'out',   qty: 6,  ref: 'AP-1042', note: 'Used in Knotless Box Braids', by: 'ST-02' },
    { id: 'SM-229', date: '2026-08-20', product: 'P-17', type: 'sale',  qty: 2,  ref: 'POS-118', note: 'Retail sale — Rachel Ayuk',   by: 'ST-01' },
    { id: 'SM-228', date: '2026-08-20', product: 'P-16', type: 'out',   qty: 1,  ref: 'AP-1044', note: 'Used in Full Body Relaxation', by: 'ST-05' },
    { id: 'SM-227', date: '2026-08-19', product: 'P-05', type: 'in',    qty: 30, ref: 'PO-045',  note: 'Restock — Marché Mokolo',     by: 'ST-01' },
    { id: 'SM-226', date: '2026-08-19', product: 'P-09', type: 'in',    qty: 12, ref: 'PO-045',  note: 'Restock — Nail Pro CM',       by: 'ST-01' },
    { id: 'SM-225', date: '2026-08-19', product: 'P-14', type: 'sale',  qty: 1,  ref: 'POS-117', note: 'Retail sale — Ndeye Fall',    by: 'ST-04' },
    { id: 'SM-224', date: '2026-08-19', product: 'P-15', type: 'out',   qty: 1,  ref: 'AP-1036', note: 'Used in Volume Lash Set',     by: 'ST-04' },
    { id: 'SM-223', date: '2026-08-18', product: 'P-02', type: 'out',   qty: 1,  ref: 'AP-1035', note: 'Used in Silk Press',          by: 'ST-01' },
    { id: 'SM-222', date: '2026-08-18', product: 'P-12', type: 'adjust', qty: -1, ref: '—',      note: 'Spillage written off',        by: 'ST-01' },
    { id: 'SM-221', date: '2026-08-17', product: 'P-03', type: 'sale',  qty: 1,  ref: 'POS-116', note: 'Retail sale — Chantal Mbarga', by: 'ST-06' },
    { id: 'SM-220', date: '2026-08-17', product: 'P-06', type: 'out',   qty: 4,  ref: 'AP-1038', note: 'Used in Passion Twists',      by: 'ST-02' }
  ];

  /* RETAIL SALES  (→ table: product_sales) — POS revenue */
  PBS.productSales = [
    { id: 'POS-118', date: '2026-08-20', client: 'CL-005', product: 'P-17', qty: 2, unitPrice: 6500,  pay: 'momo', by: 'ST-01' },
    { id: 'POS-117', date: '2026-08-19', client: 'CL-003', product: 'P-14', qty: 1, unitPrice: 12000, pay: 'cash', by: 'ST-04' },
    { id: 'POS-116', date: '2026-08-17', client: 'CL-004', product: 'P-03', qty: 1, unitPrice: 9000,  pay: 'om',   by: 'ST-06' },
    { id: 'POS-115', date: '2026-08-16', client: 'CL-001', product: 'P-05', qty: 4, unitPrice: 2500,  pay: 'cash', by: 'ST-02' },
    { id: 'POS-114', date: '2026-08-15', client: 'CL-008', product: 'P-13', qty: 1, unitPrice: 14000, pay: 'card', by: 'ST-04' }
  ];

  /* ----------------------------------------------------------------------
     EXPENSES  (→ table: expenses)
     ---------------------------------------------------------------------- */
  PBS.expenses = [
    { id: 'EX-088', date: '2026-08-19', cat: 'Restock',    label: 'Braiding hair + gel polish (PO-045)', amount: 69600, pay: 'momo', by: 'ST-01' },
    { id: 'EX-087', date: '2026-08-15', cat: 'Utilities',  label: 'ENEO electricity — August',           amount: 42000, pay: 'cash', by: 'ST-01' },
    { id: 'EX-086', date: '2026-08-15', cat: 'Utilities',  label: 'Water + internet',                    amount: 25000, pay: 'momo', by: 'ST-01' },
    { id: 'EX-085', date: '2026-08-10', cat: 'Salaries',   label: 'Staff commissions — 1st half August', amount: 218000, pay: 'momo', by: 'ST-01' },
    { id: 'EX-084', date: '2026-08-05', cat: 'Rent',       label: 'Shop rent — August',                  amount: 150000, pay: 'cash', by: 'ST-01' },
    { id: 'EX-083', date: '2026-08-04', cat: 'Marketing',  label: 'Instagram ads + flyers',              amount: 30000, pay: 'card', by: 'ST-01' },
    { id: 'EX-082', date: '2026-08-02', cat: 'Restock',    label: 'Facial + lash supplies',              amount: 55000, pay: 'om',   by: 'ST-04' },
    { id: 'EX-081', date: '2026-08-01', cat: 'Maintenance',label: 'Dryer chair repair',                  amount: 18000, pay: 'cash', by: 'ST-01' }
  ];

  /* ----------------------------------------------------------------------
     REVENUE TIME SERIES  (→ derived from appointments + product_sales)
     Pre-aggregated here so the dashboard charts have believable shapes.
     ---------------------------------------------------------------------- */
  PBS.revenue = {
    today:        {value: 148500, prev: 132000, appts: 12},
    week:         {value: 782000, prev: 703500, appts: 41},
    month:        {value: 3145000, prev: 2872000, appts: 168},
    year:         {value: 24680000, prev: 19340000, appts: 1284},

    /* last 14 days — [label, gross] */
    daily: [
      ['07 Aug', 96000], ['08 Aug', 128500], ['09 Aug', 174000], ['10 Aug', 88000],
      ['11 Aug', 112000], ['12 Aug', 143500], ['13 Aug', 156000], ['14 Aug', 121000],
      ['15 Aug', 198000], ['16 Aug', 176500], ['17 Aug', 92000], ['18 Aug', 134000],
      ['19 Aug', 167500], ['20 Aug', 148500]
    ],
    /* 12 months — [label, gross, expenses] */
    monthly: [
      ['Sep', 1685000, 1080000], ['Oct', 1820000, 1140000], ['Nov', 2140000, 1265000],
      ['Dec', 2960000, 1520000], ['Jan', 1740000, 1105000], ['Feb', 2310000, 1290000],
      ['Mar', 2085000, 1215000], ['Apr', 2450000, 1340000], ['May', 2688000, 1425000],
      ['Jun', 2872000, 1490000], ['Jul', 3015000, 1560000], ['Aug', 3145000, 1607600]
    ],
    /* by service category, current month */
    byCategory: [
      { cat: 'braids',  gross: 1042000, bookings: 47 },
      { cat: 'hair',    gross: 806000,  bookings: 52 },
      { cat: 'nails',   gross: 598000,  bookings: 71 },
      { cat: 'facials', gross: 452000,  bookings: 28 },
      { cat: 'massage', gross: 247000,  bookings: 16 }
    ],
    /* by stylist, current month */
    byStaff: [
      { id: 'ST-02', gross: 968000, appts: 44, rating: 4.9 },
      { id: 'ST-01', gross: 812000, appts: 38, rating: 5.0 },
      { id: 'ST-03', gross: 634000, appts: 58, rating: 4.8 },
      { id: 'ST-04', gross: 452000, appts: 27, rating: 5.0 },
      { id: 'ST-05', gross: 189000, appts: 12, rating: 4.9 },
      { id: 'ST-06', gross: 90000,  appts: 11, rating: 4.7 }
    ],
    /* by payment method, current month */
    byPayment: [
      { key: 'momo', label: 'MTN MoMo',     amount: 1352000, txns: 74, color: '#C9A227' },
      { key: 'cash', label: 'Cash',         amount: 921000,  txns: 52, color: '#0B0B0B' },
      { key: 'om',   label: 'Orange Money', amount: 587000,  txns: 29, color: '#E91E8C' },
      { key: 'card', label: 'Card',         amount: 285000,  txns: 13, color: '#2F6FED' }
    ],
    /* top services, current month */
    topServices: [
      { id: 'SV-06', gross: 525000, bookings: 21 },
      { id: 'SV-03', gross: 288000, bookings: 24 },
      { id: 'SV-19', gross: 250000, bookings: 10 },
      { id: 'SV-13', gross: 234000, bookings: 26 },
      { id: 'SV-07', gross: 216000, bookings: 18 },
      { id: 'SV-11', gross: 155000, bookings: 31 }
    ],
    /* expense split, current month */
    expenseSplit: [
      { label: 'Salaries & commissions', amount: 436000, color: '#0B0B0B' },
      { label: 'Product restock',        amount: 124600, color: '#C9A227' },
      { label: 'Rent',                   amount: 150000, color: '#E91E8C' },
      { label: 'Utilities',              amount: 67000,  color: '#2F6FED' },
      { label: 'Marketing',              amount: 30000,  color: '#12A150' },
      { label: 'Maintenance',            amount: 18000,  color: '#A8A8B3' }
    ]
  };

  PBS.kpi = {
    newClientsWeek: 4,
    newClientsMonth: 17,
    returningRate: 68,
    avgTicket: 18720,
    utilisation: 74,
    outstandingCount: 3,
    outstandingAmount: 36000,
    cancelRate: 6.2
  };

  /* ----------------------------------------------------------------------
     GALLERY  (→ table: gallery)
     ---------------------------------------------------------------------- */
  PBS.gallery = [
    { id: 'G-01', cat: 'braids',  title: 'Knotless Waist-Length', by: 'ST-02', tone: 'ph' },
    { id: 'G-02', cat: 'nails',   title: 'Chrome French Set',     by: 'ST-03', tone: 'ph-pink' },
    { id: 'G-03', cat: 'hair',    title: 'Mirror Silk Press',     by: 'ST-01', tone: 'ph-gold' },
    { id: 'G-04', cat: 'facials', title: 'Volume Lash Set',       by: 'ST-04', tone: 'ph' },
    { id: 'G-05', cat: 'massage', title: 'Hot Stone Suite',       by: 'ST-05', tone: 'ph-cream' },
    { id: 'G-06', cat: 'braids',  title: 'Ghana Weaving Pattern', by: 'ST-02', tone: 'ph-pink' },
    { id: 'G-07', cat: 'hair',    title: 'Bridal Glam Updo',      by: 'ST-01', tone: 'ph-gold' },
    { id: 'G-08', cat: 'nails',   title: 'Hand-painted Florals',  by: 'ST-03', tone: 'ph' },
    { id: 'G-09', cat: 'facials', title: 'Glow Facial Result',    by: 'ST-04', tone: 'ph-cream' },
    { id: 'G-10', cat: 'braids',  title: 'Passion Twists',        by: 'ST-02', tone: 'ph' },
    { id: 'G-11', cat: 'hair',    title: 'Layered Cut & Curls',   by: 'ST-06', tone: 'ph-pink' },
    { id: 'G-12', cat: 'nails',   title: 'Almond Acrylic Set',    by: 'ST-03', tone: 'ph-gold' },
    { id: 'G-13', cat: 'facials', title: 'Brow Shape & Tint',     by: 'ST-04', tone: 'ph' },
    { id: 'G-14', cat: 'massage', title: 'Body Polish Ritual',    by: 'ST-05', tone: 'ph-cream' },
    { id: 'G-15', cat: 'braids',  title: 'Kids Beaded Braids',    by: 'ST-02', tone: 'ph-gold' },
    { id: 'G-16', cat: 'hair',    title: 'Wig Install & Melt',    by: 'ST-01', tone: 'ph-pink' }
  ];

  /* ----------------------------------------------------------------------
     TESTIMONIALS  (→ table: reviews)
     ---------------------------------------------------------------------- */
  PBS.testimonials = [
    { name: 'Marlyse N.', service: 'Knotless Box Braids', stars: 5,
      text: 'Blessing did my knotless braids in under four hours and my scalp never hurt once. Six weeks later they still looked fresh. This is the only place I trust now.' },
    { name: 'Josiane B.', service: 'Gel Overlay & Nail Art', stars: 5,
      text: 'Grace is an artist. I showed her a blurry screenshot and she recreated it better than the picture. The salon itself feels like a little palace.' },
    { name: 'Ndeye F.', service: 'Deep Cleanse Facial', stars: 5,
      text: 'My skin has completely changed since I started monthly facials with Linda. She explains every step and never rushes you.' },
    { name: 'Sylvie O.', service: 'Hot Stone Therapy', stars: 5,
      text: 'I booked on my phone in two minutes on a Sunday night. Walked in Monday after work and left feeling brand new. Highly recommend.' },
    { name: 'Chantal M.', service: 'Silk Press', stars: 5,
      text: 'Aïcha gives the sleekest silk press in Yaoundé — no heat damage, and it lasts me three full weeks even in the rain.' },
    { name: 'Rachel A.', service: 'Wig Install', stars: 5,
      text: 'The melt was invisible. My colleagues genuinely thought it was my own hairline. Booking again for December already.' }
  ];

  /* ----------------------------------------------------------------------
     USER ACCOUNTS  (→ table: users)
     Distinct from PBS.staff: `staff` is the person and their craft, `users`
     is the login that person uses. Not every staff member needs an account,
     and an account (e.g. a receptionist) need not be a stylist.
     ---------------------------------------------------------------------- */
  PBS.roles = [
    { id: 'owner',        name: 'Owner / Admin',  color: '#C9A227',
      desc: 'Unrestricted. Sees financials, inventory, staff and settings.',
      perms: ['dashboard', 'appointments', 'clients', 'services', 'staff', 'revenue', 'expenses', 'inventory', 'users', 'settings'] },
    { id: 'manager',      name: 'Manager',        color: '#2F6FED',
      desc: 'Runs the floor day to day. Everything except users and settings.',
      perms: ['dashboard', 'appointments', 'clients', 'services', 'staff', 'revenue', 'expenses', 'inventory'] },
    { id: 'receptionist', name: 'Receptionist',   color: '#7B54D3',
      desc: 'Front desk: books clients in, takes payment, sells products.',
      perms: ['dashboard', 'appointments', 'clients', 'inventory'] },
    { id: 'staff',        name: 'Staff / Stylist', color: '#E91E8C',
      desc: 'Own schedule, own clients and own performance only.',
      perms: ['dashboard', 'my-schedule', 'my-clients', 'my-performance'] }
  ];
  PBS.roleById = function (id) {
    return PBS.roles.filter(function (r) { return r.id === id; })[0] || PBS.roles[3];
  };

  PBS.users = [
    { id: 'U-01', staffId: 'ST-01', name: 'Aïcha Ngo',       email: 'aicha@princessbeautysalon.cm',
      role: 'owner',        status: 'active',   lastLogin: '2026-08-20 07:42', created: '2019-03-01', twoFA: true  },
    { id: 'U-02', staffId: 'ST-02', name: 'Blessing Etonde', email: 'blessing@princessbeautysalon.cm',
      role: 'staff',        status: 'active',   lastLogin: '2026-08-20 08:05', created: '2020-06-14', twoFA: false },
    { id: 'U-03', staffId: 'ST-03', name: 'Grace Mbah',      email: 'grace@princessbeautysalon.cm',
      role: 'staff',        status: 'active',   lastLogin: '2026-08-20 09:31', created: '2021-02-09', twoFA: false },
    { id: 'U-04', staffId: 'ST-04', name: 'Linda Fotso',     email: 'linda@princessbeautysalon.cm',
      role: 'staff',        status: 'active',   lastLogin: '2026-08-19 17:12', created: '2022-05-03', twoFA: false },
    { id: 'U-05', staffId: 'ST-05', name: 'Marcel Tabi',     email: 'marcel@princessbeautysalon.cm',
      role: 'staff',        status: 'active',   lastLogin: '2026-08-18 11:58', created: '2022-09-21', twoFA: false },
    { id: 'U-06', staffId: 'ST-06', name: 'Sandra Ekwe',     email: 'sandra@princessbeautysalon.cm',
      role: 'staff',        status: 'invited',  lastLogin: '—',                created: '2026-08-18', twoFA: false },
    { id: 'U-07', staffId: null,    name: 'Estelle Manga',   email: 'reception@princessbeautysalon.cm',
      role: 'receptionist', status: 'active',   lastLogin: '2026-08-20 08:00', created: '2025-01-15', twoFA: false },
    { id: 'U-08', staffId: null,    name: 'Pauline Nji',     email: 'pauline.manager@princessbeautysalon.cm',
      role: 'manager',      status: 'suspended', lastLogin: '2026-07-30 19:20', created: '2024-04-02', twoFA: true }
  ];
  PBS.userById = function (id) {
    return PBS.users.filter(function (u) { return u.id === id; })[0];
  };
  PBS.userForStaff = function (staffId) {
    return PBS.users.filter(function (u) { return u.staffId === staffId; })[0];
  };

  /* AUDIT LOG  (→ table: activity_log) */
  PBS.activity = [
    { id: 'A-412', user: 'U-01', action: 'Marked AP-1045 completed and took 13,500 FCFA by card', time: '2026-08-20 11:58', type: 'appointment' },
    { id: 'A-411', user: 'U-03', action: 'Recorded a counter sale — POS-118, 13,000 FCFA',        time: '2026-08-20 11:20', type: 'sale' },
    { id: 'A-410', user: 'U-07', action: 'Created a booking for Patience Nkemi (AP-1050)',        time: '2026-08-20 10:44', type: 'appointment' },
    { id: 'A-409', user: 'U-01', action: 'Invited Sandra Ekwe as Staff / Stylist',                time: '2026-08-18 16:02', type: 'user' },
    { id: 'A-408', user: 'U-01', action: 'Suspended the account of Pauline Nji',                  time: '2026-08-18 15:47', type: 'user' },
    { id: 'A-407', user: 'U-01', action: 'Restocked braiding hair ×30 (PO-045)',                  time: '2026-08-19 09:15', type: 'stock' },
    { id: 'A-406', user: 'U-02', action: 'Marked AP-1053 as a no-show',                           time: '2026-08-20 18:52', type: 'appointment' },
    { id: 'A-405', user: 'U-01', action: 'Changed the commission rate for Marcel Tabi to 42%',    time: '2026-08-15 12:30', type: 'settings' }
  ];

  /* ----------------------------------------------------------------------
     OFFERS & PACKAGES  (→ table: offers)
     ---------------------------------------------------------------------- */
  PBS.offers = [
    { id: 'OF-01', title: 'Bridal Glow Package', cat: 'hair', save: 12000,
      price: 58000, was: 70000, until: '2026-12-31', badge: 'Most booked',
      includes: ['Bridal & Event Styling', 'Glow Brightening Facial', 'Classic Manicure'],
      blurb: 'Everything for the big day, booked as one appointment and finished together.' },
    { id: 'OF-02', title: 'Braids + Nails Combo', cat: 'braids', save: 5000,
      price: 30000, was: 35000, until: '2026-10-31', badge: 'Save 14%',
      includes: ['Knotless Box Braids', 'Gel Overlay'],
      blurb: 'Do both in one sitting — your braider and nail tech work at the same time.' },
    { id: 'OF-03', title: 'Self-Care Sunday', cat: 'massage', save: 8000,
      price: 39000, was: 47000, until: '2026-11-30', badge: 'Sundays only',
      includes: ['Full Body Relaxation', 'Deep Cleanse Facial', 'Spa Pedicure'],
      blurb: 'Three hours to yourself. Available on Sunday afternoons only.' },
    { id: 'OF-04', title: 'Lash & Brow Duo', cat: 'facials', save: 4000,
      price: 20000, was: 24000, until: '2026-12-31', badge: 'New client favourite',
      includes: ['Classic Lash Extensions', 'Brow Shaping & Tint'],
      blurb: 'The fastest way to look put together with zero daily effort.' },
    { id: 'OF-05', title: 'First Visit — 15% Off', cat: 'hair', save: 0,
      price: 0, was: 0, until: '2026-12-31', badge: 'New clients',
      includes: ['Any single service'],
      blurb: 'Never been to us? Mention this offer when you book your first appointment.' },
    { id: 'OF-06', title: 'Loyalty: 6th Visit Free', cat: 'nails', save: 0,
      price: 0, was: 0, until: 'Ongoing', badge: 'Loyalty',
      includes: ['Classic Manicure', 'Spa Pedicure'],
      blurb: 'Every sixth manicure or pedicure is on the house. We track it automatically.' }
  ];

  /* ----------------------------------------------------------------------
     FAQ  (→ table: faqs)
     ---------------------------------------------------------------------- */
  PBS.faqs = [
    { cat: 'Booking', q: 'Do I need an appointment or can I walk in?',
      a: 'Booked clients always come first, and Saturdays are almost always full. We do take walk-ins when there is a genuine gap, but we cannot promise one — booking online takes about two minutes and guarantees the chair.' },
    { cat: 'Booking', q: 'How far ahead can I book?',
      a: 'Three weeks. If you need something further out — a wedding, a trip — message us on WhatsApp and we will put it in the diary manually.' },
    { cat: 'Booking', q: 'Can I book more than one service at once?',
      a: 'Yes. Select everything you want in the first step of the booking flow and we will block out enough time for all of it. Where two services can run at the same time, such as braids and nails, we assign two stylists.' },
    { cat: 'Booking', q: 'How do I change or cancel?',
      a: 'Use Manage Booking with your reference code, or call the salon. Cancellation is free up to four hours before your slot.' },
    { cat: 'Payment', q: 'What payment methods do you accept?',
      a: 'Cash, MTN Mobile Money, Orange Money and card. You settle at the chair after your service — nothing is charged when you book online.' },
    { cat: 'Payment', q: 'Do I have to pay a deposit?',
      a: 'Only for services over 20,000 FCFA, and mostly on Saturdays. It is 30% by MoMo and it comes off your final bill.' },
    { cat: 'Payment', q: 'Are your prices final?',
      a: 'The price on the site is what you pay. If a style needs extra product or extra time, we quote you before we start — never after.' },
    { cat: 'Services', q: 'Do I bring my own hair for braids?',
      a: 'Braid prices cover labour, so you can bring your own. We also sell Kanekalon and pre-stretched hair at the counter if it is easier.' },
    { cat: 'Services', q: 'How long do knotless braids take?',
      a: 'Around four hours for a full head at medium length. Longer or smaller braids take more — the booking flow shows the exact time when you pick the service.' },
    { cat: 'Services', q: 'Will a relaxer or silk press damage my hair?',
      a: 'Not the way we do it. We use heat protection on every press and we will tell you honestly if a look is going to damage your hair rather than just taking the money.' },
    { cat: 'Visit', q: 'What if I am running late?',
      a: 'Message us on WhatsApp. We hold your chair for 15 minutes; after that the slot may go to someone else and the booking is marked a no-show.' },
    { cat: 'Visit', q: 'Can I bring my children?',
      a: 'Of course — and we braid for them too. Kids braiding is a service in its own right, so book them a slot rather than adding them on the day.' },
    { cat: 'Visit', q: 'Is there parking?',
      a: 'Yes, along the main road right outside. We are on the first floor — look for the gold crown sign above the entrance.' }
  ];

  /* ----------------------------------------------------------------------
     ADMIN NOTIFICATIONS  (→ table: notifications)
     ---------------------------------------------------------------------- */
  PBS.notifications = [
    { type: 'warning', title: '4 products at or below reorder level', text: 'Acrylic Powder, Lash Tray, Relaxer Kit, Heat Protectant', time: '10 min ago', href: 'inventory.html' },
    { type: 'info',    title: 'New booking request',                  text: 'Patience Nkemi · Wash & Blow Dry · today 16:00',       time: '32 min ago', href: 'appointments.html' },
    { type: 'danger',  title: 'No-show recorded',                     text: 'Yolande Kome · Kids Braiding · 18:30',                 time: '1 hr ago',   href: 'appointments.html' },
    { type: 'success', title: 'Daily target reached',                 text: "Today's revenue passed 140,000 FCFA",                  time: '2 hrs ago',  href: 'revenue.html' }
  ];

  /* ----------------------------------------------------------------------
     SESSION (mock auth — PHASE 2: replace with PHP session)
     ---------------------------------------------------------------------- */
  /* Storage access can throw outright — an opaque file:// origin, private mode,
     or blocked cookies all raise SecurityError on the very first access. Every
     call below is guarded so a storage failure degrades to "no saved state"
     instead of throwing and stranding the user mid-flow. */
  function store(kind) {
    try { return w[kind]; } catch (e) { return null; }
  }
  function readJSON(kind, key) {
    try {
      var s = store(kind);
      return s ? JSON.parse(s.getItem(key) || 'null') : null;
    } catch (e) { return null; }
  }
  function writeJSON(kind, key, val) {
    try {
      var s = store(kind);
      if (s) { s.setItem(key, JSON.stringify(val)); return true; }
    } catch (e) {}
    return false;
  }
  function drop(kind, key) {
    try { var s = store(kind); if (s) s.removeItem(key); } catch (e) {}
  }

  PBS.session = {
    get: function () { return readJSON('localStorage', 'pbs_session'); },
    set: function (obj) { return writeJSON('localStorage', 'pbs_session', obj); },
    clear: function () { drop('localStorage', 'pbs_session'); },
    current: function () {
      return PBS.session.get() || { role: 'owner', staffId: 'ST-01', name: 'Aïcha Ngo' };
    },
    isOwner: function () { return PBS.session.current().role === 'owner'; }
  };

  /* Booking draft passed between book.html → confirmation.html.
     If sessionStorage is unavailable we keep the draft in memory: the handoff
     still works for the normal same-tab navigation used by the wizard. */
  var memDraft = null;
  PBS.draft = {
    save: function (o) { memDraft = o; return writeJSON('sessionStorage', 'pbs_booking', o); },
    load: function () { return readJSON('sessionStorage', 'pbs_booking') || memDraft; },
    clear: function () { memDraft = null; drop('sessionStorage', 'pbs_booking'); }
  };

})(window);
