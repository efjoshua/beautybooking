/* ==========================================================================
   Princess Beauty Salon — admin UI behaviour (backend era)
   The chrome is now rendered by PHP. What remains here is genuinely
   client-side: icon painting, the mobile sidebar, modals, toasts, and the
   responsive table labels.
   ========================================================================== */
(function (w, d) {
  'use strict';
  var PBS = w.PBS || (w.PBS = {});

  /* ---- icons: PHP emits <span data-icon="cash">, JS paints it ---------- */
  function paintIcons(root) {
    (root || d).querySelectorAll('[data-icon]').forEach(function (el) {
      if (el.firstElementChild) return;                 // already painted
      el.innerHTML = PBS.icon ? PBS.icon(el.getAttribute('data-icon')) : '';
    });
  }
  PBS.paintIcons = paintIcons;

  /* ---- toast ----------------------------------------------------------- */
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
    }, 3200);
  };

  /* ---- modals ---------------------------------------------------------- */
  PBS.openModal = function (id) {
    var m = typeof id === 'string' ? d.getElementById(id) : id;
    if (m) { m.classList.add('open'); d.body.style.overflow = 'hidden'; paintIcons(m); }
  };
  PBS.closeModals = function () {
    d.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); });
    d.body.style.overflow = '';
  };

  /* ---- responsive table labels (see components.css .table-cards) ------- */
  PBS.labelCells = function (root) {
    (root || d).querySelectorAll('table.table-cards').forEach(function (t) {
      var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) {
        return th.textContent.trim();
      });
      if (!heads.length) return;
      t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) {
          if (!td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i] || '');
        });
      });
    });
  };

  /* ---- scroll reveal --------------------------------------------------- */
  PBS.initReveal = function () {
    var els = d.querySelectorAll('.reveal:not(.in)');
    if (!els.length) return;
    if (!('IntersectionObserver' in w)) {
      els.forEach(function (e) { e.classList.add('in'); }); return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: .12, rootMargin: '0px 0px -40px' });
    els.forEach(function (e) { io.observe(e); });
  };

  /* ---- fetch wrapper that carries the CSRF token ----------------------- */
  PBS.api = function (url, options) {
    options = options || {};
    options.headers = Object.assign({
      'X-CSRF-Token': w.PBS_CSRF || '',
      'Accept': 'application/json'
    }, options.headers || {});
    if (options.body && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      if (typeof options.body !== 'string') options.body = JSON.stringify(options.body);
    }
    options.credentials = 'same-origin';
    return fetch(url, options).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Bad response from server.' }; })
        .then(function (j) {
          if (!r.ok && j.error) throw new Error(j.error);
          return j;
        });
    });
  };

  /* ---- boot ------------------------------------------------------------ */
  d.addEventListener('DOMContentLoaded', function () {
    paintIcons();
    PBS.labelCells();
    PBS.initReveal();

    var sb = d.getElementById('sidebar'),
        scrim = d.getElementById('sidebarScrim'),
        tog = d.getElementById('sidebarToggle');
    function setSb(open) {
      if (!sb) return;
      sb.classList.toggle('open', open);
      if (scrim) scrim.classList.toggle('open', open);
      d.body.style.overflow = open ? 'hidden' : '';
    }
    if (tog) tog.addEventListener('click', function () { setSb(!sb.classList.contains('open')); });
    if (scrim) scrim.addEventListener('click', function () { setSb(false); });

    /* delegated modal open/close */
    d.addEventListener('click', function (e) {
      var open = e.target.closest('[data-modal]');
      if (open) { e.preventDefault(); PBS.openModal(open.getAttribute('data-modal')); return; }
      if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal-backdrop')) {
        PBS.closeModals();
      }
    });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') PBS.closeModals(); });

    /* re-label and repaint whenever a table body is re-rendered */
    if (w.MutationObserver) {
      var pending = false;
      var mo = new MutationObserver(function () {
        if (pending) return;
        pending = true;
        setTimeout(function () { pending = false; PBS.labelCells(); paintIcons(); }, 0);
      });
      d.querySelectorAll('table.table-cards tbody, [data-live]').forEach(function (n) {
        mo.observe(n, { childList: true });
      });
    }

    var gs = d.getElementById('globalSearch');
    if (gs) gs.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && gs.value.trim()) {
        w.location.href = 'appointments.php?q=' + encodeURIComponent(gs.value.trim());
      }
    });

    /* a ?denied= redirect from the permission guard */
    var q = new URLSearchParams(w.location.search);
    if (q.get('denied')) {
      setTimeout(function () {
        PBS.toast('Your role does not have access to ' + q.get('denied') + '.', 'error');
      }, 300);
    }
    if (q.get('saved')) {
      setTimeout(function () { PBS.toast('Saved.'); }, 200);
    }
  });

})(window, document);
