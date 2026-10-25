/* ==========================================================================
   Princess Beauty Salon — ui.js
   Icon library and display formatters. Nothing else.

   WHY THIS REPLACED data.js
   data.js was the phase-1 mock database, and it was still being loaded on
   every public page: 58KB carrying client names, phone numbers, email
   addresses and private stylist notes ("sensitive scalp"), plus every staff
   email address — readable by anyone who opened the site. Only six of its
   forty-seven exports were ever used by served code.

   Real data now comes from MySQL through PHP. This file carries none.
   data.js survives in _dev/ purely because the seed generators read it, and
   _dev/ is denied over HTTP.
   ========================================================================== */
(function (w) {
  'use strict';
  var PBS = w.PBS = w.PBS || {};

  /* Injected by PHP so it follows config.php rather than being hardcoded. */
  var CURRENCY = w.PBS_CURRENCY || 'FCFA';

  /* ------------------------------------------------------------ formatters */

  PBS.money = function (n, opts) {
    opts = opts || {};
    var s = (Math.round(Number(n) || 0)).toLocaleString('en-US');
    return opts.bare ? s : s + ' ' + CURRENCY;
  };

  PBS.moneyShort = function (n) {
    var v = Number(n) || 0;
    if (v >= 1000000) return (v / 1000000).toFixed(v >= 10000000 ? 0 : 1) + 'M';
    if (v >= 1000) return Math.round(v / 1000) + 'k';
    return String(v);
  };

  PBS.dur = function (mins) {
    var h = Math.floor(mins / 60), m = mins % 60;
    return ((h ? h + 'h' : '') + (h && m ? ' ' : '') + (m ? m + 'min' : '')) || '0min';
  };

  PBS.initials = function (name) {
    var parts = String(name || '').split(/\s+/).filter(Boolean).slice(0, 2);
    return parts.map(function (p) { return p.charAt(0).toUpperCase(); }).join('') || '?';
  };

  PBS.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /* ---------------------------------------------------------------- icons */
  var I = PBS.ICONS = {
    /* service category icons (the logo motif) */
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

  /* These two are solid glyphs, not line icons — they must be filled. */
  var FILLED = { facebook: 1, tiktok: 1 };

  PBS.icon = function (name, cls) {
    var p = I[name] || I.crown;
    var solid = FILLED[name];
    var cn = (solid ? 'ico-filled ' : '') + (cls || '');
    /* width/height are a FALLBACK: without them an SVG that lands somewhere
       with no CSS size rule renders at the browser default 300x150. Any CSS
       rule still overrides these attributes. */
    return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"' +
      (cn.trim() ? ' class="' + cn.trim() + '"' : '') +
      ' fill="' + (solid ? 'currentColor' : 'none') + '"' +
      ' stroke="' + (solid ? 'none' : 'currentColor') + '"' +
      ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
  };

})(window);
