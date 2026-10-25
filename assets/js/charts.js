/* ==========================================================================
   Princess Beauty Salon — charts.js
   Dependency-free inline-SVG charts for the admin panel.

   COLOR CONTRACT (validated — do not reorder casually)
   ---------------------------------------------------
   Categorical order, fixed, never cycled:
     1 gold  #C9A227   2 pink #E91E8C   3 blue #2F6FED
     4 green #12A150   5 violet #7B54D3
   Adjacent-pair CVD separation on this order clears ΔE 8 on every pair
   (worst: blue↔pink 15.8 protan). Gold sits at 2.36:1 against the white
   surface, which is below 3:1 — so EVERY chart using gold ships visible
   direct labels or an adjacent value table as relief. That is why the bar
   and donut renderers below always emit labels.
   Gold and green must never be adjacent slots (ΔE 6.7 protan).

   Mark rules applied: 2px lines, ≥8px hover markers, 4px rounded bar ends
   anchored to the baseline, 2px surface gap between adjacent/stacked fills,
   recessive grid, legend whenever there are 2+ series.
   ========================================================================== */
(function (w, d) {
  'use strict';
  var PBS = w.PBS;
  var C = PBS.charts = {};

  var INK = '#3A3A40', MUTED = '#77777F', GRID = '#E8E8EC', SURFACE = '#FFFFFF';

  C.PALETTE = ['#C9A227', '#E91E8C', '#2F6FED', '#12A150', '#7B54D3'];

  function ns(tag, attrs) {
    var e = d.createElementNS('http://www.w3.org/2000/svg', tag);
    for (var k in attrs) if (attrs.hasOwnProperty(k)) e.setAttribute(k, attrs[k]);
    return e;
  }
  function nice(max) {
    if (max <= 0) return 10;
    var mag = Math.pow(10, Math.floor(Math.log10(max)));
    return Math.ceil(max / mag * 2) / 2 * mag;
  }

  /* ----------------------------------------------------------------------
     RESPONSIVE GEOMETRY
     The viewBox is scaled to fill its container, so a fixed 760-unit width
     inside a 328px phone column shrinks every label by 0.43 — an 11px axis
     label renders at under 5px and is simply unreadable.
     Sizing the viewBox to the container keeps 1 SVG unit ≈ 1 CSS pixel at
     every width, so text stays legible without per-breakpoint overrides.
     ---------------------------------------------------------------------- */
  function geometry(host, opt) {
    var hostW = Math.round(host.clientWidth || 760);
    var W = Math.max(300, Math.min(760, hostW || 760));
    var narrow = W < 470;
    return {
      W: W,
      H: opt.height || (narrow ? 210 : 260),
      narrow: narrow,
      /* the y-axis gutter has to fit "1.8M"-style labels at real size */
      pad: { t: 16, r: narrow ? 10 : 16, b: narrow ? 26 : 30, l: narrow ? 40 : 52 },
      fs: narrow ? 10 : 11,
      /* how many x labels can fit without colliding */
      maxLabels: narrow ? 4 : 8
    };
  }

  /* Charts must be re-drawn when the column width changes (rotation, resize),
     otherwise the geometry above is computed once against the wrong width. */
  var registry = [];
  function register(host, redraw) {
    for (var i = 0; i < registry.length; i++) {
      if (registry[i].host === host) { registry[i].redraw = redraw; return; }
    }
    registry.push({ host: host, redraw: redraw });
  }
  var resizeTimer, lastW = w.innerWidth;
  w.addEventListener('resize', function () {
    if (w.innerWidth === lastW) return;      /* ignore mobile URL-bar height jitter */
    lastW = w.innerWidth;
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      registry.forEach(function (r) {
        if (r.host.isConnected) r.redraw();
      });
    }, 180);
  }, { passive: true });

  /* Shared tooltip: one floating box per chart container */
  function tipFor(host) {
    var t = host.querySelector('.chart-tip');
    if (!t) {
      t = d.createElement('div');
      t.className = 'chart-tip';
      host.style.position = 'relative';
      host.appendChild(t);
    }
    return t;
  }
  function showTip(host, html, xFrac, yPx) {
    var t = tipFor(host);
    t.innerHTML = html;
    t.classList.add('on');
    var w0 = host.clientWidth, tw = t.offsetWidth;
    var x = Math.max(4, Math.min(w0 - tw - 4, xFrac * w0 - tw / 2));
    t.style.left = x + 'px';
    t.style.top = Math.max(0, yPx - t.offsetHeight - 12) + 'px';
  }
  function hideTip(host) {
    var t = host.querySelector('.chart-tip');
    if (t) t.classList.remove('on');
  }

  /* ======================================================================
     LINE / AREA — change over time, single or few series.
     Single series ships no legend (the card title names it) and a
     crosshair + tooltip on hover.
     ====================================================================== */
  C.line = function (host, opt) {
    if (typeof host === 'string') host = d.querySelector(host);
    if (!host) return;
    register(host, function () { C.line(host, opt); });

    var labels = opt.labels,
        series = opt.series,
        fmt = opt.format || function (v) { return PBS.money(v); },
        g = geometry(host, opt),
        W = g.W, H = g.H, P = g.pad, FS = g.fs,
        iw = W - P.l - P.r, ih = H - P.t - P.b,
        area = opt.area !== false;

    var max = nice(Math.max.apply(null, series.reduce(function (a, s) {
      return a.concat(s.data);
    }, [0])));
    var xAt = function (i) { return P.l + (labels.length === 1 ? iw / 2 : i * iw / (labels.length - 1)); };
    var yAt = function (v) { return P.t + ih - (v / max) * ih; };

    var svg = ns('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img',
      'aria-label': opt.title || 'Trend chart' });

    /* recessive gridlines + y ticks */
    for (var gi = 0; gi <= 4; gi++) {
      var yv = max * gi / 4, y = yAt(yv);
      svg.appendChild(ns('line', { x1: P.l, x2: W - P.r, y1: y, y2: y,
        stroke: GRID, 'stroke-width': 1 }));
      var tx = ns('text', { x: P.l - 7, y: y + 4, 'text-anchor': 'end',
        fill: MUTED, 'font-size': FS });
      tx.textContent = PBS.moneyShort(yv);
      svg.appendChild(tx);
    }

    series.forEach(function (s, si) {
      var col = s.color || C.PALETTE[si];
      var pts = s.data.map(function (v, i) { return xAt(i) + ',' + yAt(v); }).join(' ');
      if (area && series.length === 1) {
        var gid = 'grad' + Math.random().toString(36).slice(2, 7);
        var defs = ns('defs');
        var lg = ns('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 });
        lg.appendChild(ns('stop', { offset: '0%', 'stop-color': col, 'stop-opacity': .22 }));
        lg.appendChild(ns('stop', { offset: '100%', 'stop-color': col, 'stop-opacity': 0 }));
        defs.appendChild(lg); svg.appendChild(defs);
        svg.appendChild(ns('polygon', {
          points: xAt(0) + ',' + (P.t + ih) + ' ' + pts + ' ' + xAt(labels.length - 1) + ',' + (P.t + ih),
          fill: 'url(#' + gid + ')'
        }));
      }
      svg.appendChild(ns('polyline', { points: pts, fill: 'none', stroke: col,
        'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
    });

    /* x labels — thinned to what actually fits at this width */
    var every = Math.ceil(labels.length / g.maxLabels);
    labels.forEach(function (l, i) {
      if (i % every && i !== labels.length - 1) return;
      var t = ns('text', { x: xAt(i), y: H - 8, 'text-anchor': 'middle',
        fill: MUTED, 'font-size': FS });
      t.textContent = l;
      svg.appendChild(t);
    });

    /* hover layer: crosshair, markers, tooltip */
    var cross = ns('line', { y1: P.t, y2: P.t + ih, stroke: '#A8A8B3',
      'stroke-width': 1, 'stroke-dasharray': '3 3', opacity: 0 });
    svg.appendChild(cross);
    var dots = series.map(function (s, si) {
      var dot = ns('circle', { r: 5, fill: s.color || C.PALETTE[si],
        stroke: SURFACE, 'stroke-width': 2, opacity: 0 });
      svg.appendChild(dot); return dot;
    });
    var hit = ns('rect', { x: P.l, y: P.t, width: iw, height: ih, fill: 'transparent' });
    svg.appendChild(hit);

    hit.addEventListener('mousemove', function (e) {
      var r = svg.getBoundingClientRect();
      var frac = (e.clientX - r.left) / r.width;
      var i = Math.round((frac * W - P.l) / (iw / Math.max(1, labels.length - 1)));
      i = Math.max(0, Math.min(labels.length - 1, i));
      cross.setAttribute('x1', xAt(i)); cross.setAttribute('x2', xAt(i));
      cross.setAttribute('opacity', 1);
      dots.forEach(function (dot, si) {
        dot.setAttribute('cx', xAt(i));
        dot.setAttribute('cy', yAt(series[si].data[i]));
        dot.setAttribute('opacity', 1);
      });
      showTip(host, '<b>' + labels[i] + '</b>' + series.map(function (s, si) {
        return '<span class="row"><i style="background:' + (s.color || C.PALETTE[si]) + '"></i>' +
          (series.length > 1 ? s.name + ' ' : '') + '<em>' + fmt(s.data[i]) + '</em></span>';
      }).join(''), xAt(i) / W, (yAt(series[0].data[i]) / H) * host.clientHeight);
    });
    hit.addEventListener('mouseleave', function () {
      cross.setAttribute('opacity', 0);
      dots.forEach(function (dt) { dt.setAttribute('opacity', 0); });
      hideTip(host);
    });

    host.innerHTML = '';
    host.appendChild(svg);
    if (series.length > 1) host.appendChild(legend(series));
  };

  /* ======================================================================
     GROUPED BARS — two measures over the same periods.
     4px rounded top, anchored to the baseline, 2px gap between bars.
     ====================================================================== */
  C.bars = function (host, opt) {
    if (typeof host === 'string') host = d.querySelector(host);
    if (!host) return;
    register(host, function () { C.bars(host, opt); });

    var labels = opt.labels, series = opt.series,
        fmt = opt.format || function (v) { return PBS.money(v); },
        g = geometry(host, opt),
        W = g.W, H = g.H, P = g.pad, FS = g.fs,
        iw = W - P.l - P.r, ih = H - P.t - P.b;

    var max = nice(Math.max.apply(null, series.reduce(function (a, s) { return a.concat(s.data); }, [0])));
    var band = iw / labels.length, GAP = g.narrow ? 1 : 2;
    var bw = Math.max(3, (band * (g.narrow ? .74 : .62) - GAP * (series.length - 1)) / series.length);

    var svg = ns('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img',
      'aria-label': opt.title || 'Bar chart' });

    for (var gi = 0; gi <= 4; gi++) {
      var y = P.t + ih - (gi / 4) * ih;
      svg.appendChild(ns('line', { x1: P.l, x2: W - P.r, y1: y, y2: y, stroke: GRID, 'stroke-width': 1 }));
      var t = ns('text', { x: P.l - 7, y: y + 4, 'text-anchor': 'end', fill: MUTED, 'font-size': FS });
      t.textContent = PBS.moneyShort(max * gi / 4);
      svg.appendChild(t);
    }

    labels.forEach(function (l, i) {
      var groupW = bw * series.length + GAP * (series.length - 1);
      var x0 = P.l + i * band + (band - groupW) / 2;
      series.forEach(function (s, si) {
        var v = s.data[i], h = Math.max(2, (v / max) * ih);
        var x = x0 + si * (bw + GAP);
        var r = ns('rect', { x: x, y: P.t + ih - h, width: bw, height: h,
          rx: 4, fill: s.color || C.PALETTE[si] });
        r.addEventListener('mouseenter', function () {
          showTip(host, '<b>' + l + '</b><span class="row"><i style="background:' +
            (s.color || C.PALETTE[si]) + '"></i>' + s.name + ' <em>' + fmt(v) + '</em></span>',
            (x + bw / 2) / W, ((P.t + ih - h) / H) * host.clientHeight);
        });
        r.addEventListener('mouseleave', function () { hideTip(host); });
        svg.appendChild(r);
      });
      /* 12 monthly labels will not fit across a phone — thin them out */
      if (!(i % Math.ceil(labels.length / g.maxLabels)) || i === labels.length - 1) {
        var tx = ns('text', { x: P.l + i * band + band / 2, y: H - 8,
          'text-anchor': 'middle', fill: MUTED, 'font-size': FS });
        tx.textContent = l;
        svg.appendChild(tx);
      }
    });

    host.innerHTML = '';
    host.appendChild(svg);
    if (series.length > 1) host.appendChild(legend(series));
  };

  /* ======================================================================
     DONUT — parts of a whole (payment mix). Max 5 slices.
     2px surface gap between arcs; legend carries the value of every slice,
     which is also the contrast relief for the gold slot.
     ====================================================================== */
  C.donut = function (host, opt) {
    if (typeof host === 'string') host = d.querySelector(host);
    if (!host) return;
    var slices = opt.slices,
        fmt = opt.format || function (v) { return PBS.money(v); },
        S = 220, cx = S / 2, cy = S / 2, R = 92, r = 60;
    var total = slices.reduce(function (t, s) { return t + s.value; }, 0) || 1;

    var svg = ns('svg', { viewBox: '0 0 ' + S + ' ' + S, role: 'img',
      'aria-label': opt.title || 'Composition chart' });
    svg.setAttribute('style', 'max-width:220px;margin:0 auto');

    var angle = -Math.PI / 2;
    var GAP = 0.028; /* ≈2px of arc at this radius, painted as surface */
    slices.forEach(function (s, i) {
      var frac = s.value / total, sweep = frac * Math.PI * 2;
      var a0 = angle + GAP / 2, a1 = angle + sweep - GAP / 2;
      if (a1 <= a0) a1 = a0 + 0.001;
      var large = (a1 - a0) > Math.PI ? 1 : 0;
      var p = ns('path', {
        d: ['M', cx + R * Math.cos(a0), cy + R * Math.sin(a0),
            'A', R, R, 0, large, 1, cx + R * Math.cos(a1), cy + R * Math.sin(a1),
            'L', cx + r * Math.cos(a1), cy + r * Math.sin(a1),
            'A', r, r, 0, large, 0, cx + r * Math.cos(a0), cy + r * Math.sin(a0), 'Z'].join(' '),
        fill: s.color || C.PALETTE[i]
      });
      p.style.transition = 'opacity .2s';
      p.addEventListener('mouseenter', function () {
        p.style.opacity = .8;
        showTip(host, '<b>' + s.label + '</b><span class="row"><i style="background:' +
          (s.color || C.PALETTE[i]) + '"></i><em>' + fmt(s.value) + '</em> · ' +
          Math.round(frac * 100) + '%</span>', .5, host.clientHeight * .3);
      });
      p.addEventListener('mouseleave', function () { p.style.opacity = 1; hideTip(host); });
      svg.appendChild(p);
      angle += sweep;
    });

    var t1 = ns('text', { x: cx, y: cy - 2, 'text-anchor': 'middle', fill: '#0B0B0B',
      'font-size': 20, 'font-weight': 700, 'font-family': 'Playfair Display, Georgia, serif' });
    t1.textContent = PBS.moneyShort(total);
    svg.appendChild(t1);
    var t2 = ns('text', { x: cx, y: cy + 16, 'text-anchor': 'middle', fill: MUTED,
      'font-size': 10, 'letter-spacing': 1.4 });
    t2.textContent = (opt.centerLabel || 'TOTAL FCFA');
    svg.appendChild(t2);

    host.innerHTML = '';
    host.appendChild(svg);

    /* value legend = direct labels (required relief, not decoration) */
    var lg = d.createElement('div');
    lg.className = 'donut-legend';
    lg.innerHTML = slices.map(function (s, i) {
      return '<div class="dl-row"><span class="sw" style="background:' + (s.color || C.PALETTE[i]) + '"></span>' +
        '<span class="nm">' + s.label + '</span>' +
        '<span class="vl">' + fmt(s.value) + '</span>' +
        '<span class="pc">' + Math.round(s.value / total * 100) + '%</span></div>';
    }).join('');
    host.appendChild(lg);
  };

  /* ======================================================================
     SPARKLINE — trend shape inside a stat tile. No axes, no tooltip.
     ====================================================================== */
  C.spark = function (host, opt) {
    if (typeof host === 'string') host = d.querySelector(host);
    if (!host) return;
    var data = opt.data, col = opt.color || '#E91E8C',
        W = 160, H = 40, P = 4;
    var mn = Math.min.apply(null, data), mx = Math.max.apply(null, data);
    var rng = (mx - mn) || 1;
    var xAt = function (i) { return P + i * (W - P * 2) / (data.length - 1); };
    var yAt = function (v) { return H - P - ((v - mn) / rng) * (H - P * 2); };
    var pts = data.map(function (v, i) { return xAt(i) + ',' + yAt(v); }).join(' ');

    var gid = 'sp' + Math.random().toString(36).slice(2, 7);
    host.innerHTML =
      '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">' +
        '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">' +
          '<stop offset="0%" stop-color="' + col + '" stop-opacity=".26"/>' +
          '<stop offset="100%" stop-color="' + col + '" stop-opacity="0"/>' +
        '</linearGradient></defs>' +
        '<polygon points="' + xAt(0) + ',' + H + ' ' + pts + ' ' + xAt(data.length - 1) + ',' + H +
          '" fill="url(#' + gid + ')"/>' +
        '<polyline points="' + pts + '" fill="none" stroke="' + col +
          '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>' +
        '<circle cx="' + xAt(data.length - 1) + '" cy="' + yAt(data[data.length - 1]) +
          '" r="2.6" fill="' + col + '"/>' +
      '</svg>';
  };

  /* ======================================================================
     HORIZONTAL RANKED BARS — "top services / top stylists".
     Always direct-labelled with name + value, so the gold slot is legal.
     ====================================================================== */
  C.ranked = function (host, rows, opt) {
    if (typeof host === 'string') host = d.querySelector(host);
    if (!host) return;
    opt = opt || {};
    var fmt = opt.format || function (v) { return PBS.money(v); };
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; })) || 1;
    host.innerHTML = rows.map(function (r, i) {
      var rc = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : '';
      return '<div class="bar-row">' +
        '<span class="nm">' + (opt.rank !== false ? '<span class="rank ' + rc + '">' + (i + 1) + '</span>' : '') +
          r.label + (r.sub ? ' <span class="tiny muted">' + r.sub + '</span>' : '') + '</span>' +
        '<span class="vl">' + fmt(r.value) + '</span>' +
        '<span class="track"><i style="width:' + (r.value / max * 100).toFixed(1) + '%;background:' +
          (r.color || C.PALETTE[i % C.PALETTE.length]) + '"></i></span>' +
        '</div>';
    }).join('');
  };

  /* legend used by line/bars */
  function legend(series) {
    var el = d.createElement('div');
    el.className = 'chart-legend';
    el.innerHTML = series.map(function (s, i) {
      return '<span class="li"><span class="sw" style="background:' +
        (s.color || C.PALETTE[i]) + '"></span>' + s.name + '</span>';
    }).join('');
    return el;
  }

})(window, document);
