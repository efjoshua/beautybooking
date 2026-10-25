/* ==========================================================================
   Princess Beauty Salon — public site behaviour (backend era)
   The chrome is rendered by PHP; this is the interaction layer that remains.
   ========================================================================== */
(function (w, d) {
  'use strict';
  var PBS = w.PBS || (w.PBS = {});

  function paintIcons(root) {
    (root || d).querySelectorAll('[data-icon]').forEach(function (el) {
      if (el.firstElementChild) return;
      el.innerHTML = PBS.icon ? PBS.icon(el.getAttribute('data-icon')) : '';
    });
  }
  PBS.paintIcons = paintIcons;

  PBS.toast = function (msg, kind) {
    var host = d.querySelector('.toast-host');
    if (!host) { host = d.createElement('div'); host.className = 'toast-host'; d.body.appendChild(host); }
    var t = d.createElement('div');
    t.className = 'toast';
    if (kind === 'error') t.style.borderLeftColor = 'var(--danger)';
    t.textContent = msg;
    host.appendChild(t);
    setTimeout(function () {
      t.style.transition = 'opacity .3s, transform .3s';
      t.style.opacity = 0; t.style.transform = 'translateX(20px)';
      setTimeout(function () { t.remove(); }, 320);
    }, 3400);
  };

  PBS.initReveal = function () {
    var els = d.querySelectorAll('.reveal:not(.in)');
    if (!els.length) return;
    if (!('IntersectionObserver' in w)) {
      els.forEach(function (e) { e.classList.add('in'); }); return;
    }
    var io = new IntersectionObserver(function (en) {
      en.forEach(function (x) {
        if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); }
      });
    }, { threshold: .12, rootMargin: '0px 0px -40px' });
    els.forEach(function (e) { io.observe(e); });
  };

  PBS.api = function (url, options) {
    options = options || {};
    options.headers = Object.assign({
      'X-CSRF-Token': w.PBS_CSRF || '', 'Accept': 'application/json'
    }, options.headers || {});
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    options.credentials = 'same-origin';
    return fetch(url, options).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Unexpected response.' }; })
        .then(function (j) { if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed.'); return j; });
    });
  };

  /* filter chips shared by gallery / team / offers */
  PBS.initFilters = function (chipSel, itemSel) {
    var chips = Array.prototype.slice.call(d.querySelectorAll(chipSel));
    var items = Array.prototype.slice.call(d.querySelectorAll(itemSel));
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        chips.forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        var f = chip.getAttribute('data-filter');
        items.forEach(function (it) {
          it.classList.toggle('hidden', !(f === 'all' || it.getAttribute('data-cat') === f));
        });
      });
    });
  };

  PBS.initCounters = function () {
    var els = Array.prototype.slice.call(d.querySelectorAll('[data-count]'));
    if (!els.length) return;
    if (!('IntersectionObserver' in w)) {
      els.forEach(function (e) { e.textContent = e.getAttribute('data-count'); }); return;
    }
    var io = new IntersectionObserver(function (ent) {
      ent.forEach(function (en) {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        var el = en.target, target = parseFloat(el.getAttribute('data-count')),
            suffix = el.getAttribute('data-suffix') || '', start = performance.now();
        (function step(now) {
          var p = Math.min(1, (now - start) / 1100), k = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * k).toLocaleString('en-US') + suffix;
          if (p < 1) requestAnimationFrame(step);
        })(start);
      });
    }, { threshold: .4 });
    els.forEach(function (e) { io.observe(e); });
  };

  /* sticky category jump bar (services page) */
  PBS.initJumpBar = function (barSel, listSel) {
    var bar = d.querySelector(barSel), list = d.querySelector(listSel);
    if (!bar || !list) return;

    function syncFade() {
      var scrollable = list.scrollWidth > list.clientWidth + 2;
      bar.classList.toggle('is-scrollable', scrollable);
      bar.classList.toggle('at-end',
        scrollable && list.scrollLeft + list.clientWidth >= list.scrollWidth - 2);
    }
    list.addEventListener('scroll', syncFade, { passive: true });
    w.addEventListener('resize', syncFade, { passive: true });
    syncFade();

    var links = Array.prototype.slice.call(list.querySelectorAll('[data-jump]'));
    links.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var t = d.getElementById(a.getAttribute('data-jump'));
        if (!t) return;
        var off = (d.querySelector('.site-header') || {}).offsetHeight || 72;
        off += bar.offsetHeight;
        w.scrollTo({ top: t.getBoundingClientRect().top + w.scrollY - off - 8, behavior: 'smooth' });
      });
    });

    var sections = links.map(function (a) { return d.getElementById(a.getAttribute('data-jump')); })
                        .filter(Boolean);
    if (!sections.length) return;
    function spy() {
      var line = ((d.querySelector('.site-header') || {}).offsetHeight || 72) + bar.offsetHeight + 24;
      var cur = sections[0];
      sections.forEach(function (s) { if (s.getBoundingClientRect().top <= line) cur = s; });
      links.forEach(function (a) { a.classList.toggle('active', a.getAttribute('data-jump') === cur.id); });
    }
    var ticking = false;
    w.addEventListener('scroll', function () {
      if (ticking) return; ticking = true;
      requestAnimationFrame(function () { ticking = false; spy(); });
    }, { passive: true });
    spy();
  };

  /* FAQ accordion */
  PBS.initFaq = function (root) {
    var host = d.querySelector(root || '#faqList');
    if (!host) return;
    host.querySelectorAll('.faq-q').forEach(function (b) {
      b.addEventListener('click', function () {
        var item = b.parentElement, open = item.classList.contains('open');
        host.querySelectorAll('.faq-item').forEach(function (i) { i.classList.remove('open'); });
        item.classList.toggle('open', !open);
      });
    });
  };

  d.addEventListener('DOMContentLoaded', function () {
    paintIcons();
    PBS.initReveal();
    PBS.initCounters();

    var drawer = d.getElementById('drawer'), burger = d.getElementById('hamburger');
    function toggle(open) {
      if (!drawer) return;
      drawer.classList.toggle('open', open);
      if (burger) { burger.classList.toggle('open', open); burger.setAttribute('aria-expanded', String(open)); }
      d.body.style.overflow = open ? 'hidden' : '';
    }
    if (burger) burger.addEventListener('click', function () { toggle(!drawer.classList.contains('open')); });
    d.querySelectorAll('[data-close-drawer]').forEach(function (el) {
      el.addEventListener('click', function () { toggle(false); });
    });

    var hdr = d.getElementById('siteHeader');
    if (hdr) {
      var onScroll = function () { hdr.classList.toggle('scrolled', w.scrollY > 8); };
      w.addEventListener('scroll', onScroll, { passive: true }); onScroll();
    }

    d.addEventListener('click', function (e) {
      var open = e.target.closest('[data-modal]');
      if (open) {
        e.preventDefault();
        var m = d.getElementById(open.getAttribute('data-modal'));
        if (m) { m.classList.add('open'); d.body.style.overflow = 'hidden'; paintIcons(m); }
        return;
      }
      if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal-backdrop')) {
        d.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); });
        d.body.style.overflow = '';
      }
    });
    d.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      toggle(false);
      d.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); });
      d.body.style.overflow = '';
    });
  });

})(window, document);
