/* ==========================================================================
   Princess Beauty Salon — booking wizard (live)
   Same five steps and the same markup as the prototype, but every list comes
   from the server: eligible stylists, real free slots, and a real POST that
   creates the appointment row.
   ========================================================================== */
(function (w, d) {
  'use strict';
  var PBS = w.PBS;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var API = (w.PBS_BASE || '') + '/api/';

  var CAT = w.PBS_CATEGORIES || [];
  var SERVICES = {};
  CAT.forEach(function (c) { c.services.forEach(function (s) { s.cat = c.slug; SERVICES[s.ref] = s; }); });

  var TOTAL = 5;

  /* Payment posture, decided by the server. When PBS_PAY.state is 'off' or
     'unconfigured' nothing here ever asks anyone for money — the wizard is
     five steps and the sixth is unreachable. */
  var PAY = w.PBS_PAY || { state: 'off', providers: [], required: false };

  /** What this basket would owe up front. 0 whenever nothing is collectable. */
  function depositDue() {
    if (PAY.state !== 'live' || !PAY.required) return 0;
    var total = subtotal();
    if (total < PAY.threshold) return 0;
    return Math.round(total * PAY.percent / 100);
  }
  var state = {
    step: 1, services: [], staff: 'any', date: null, time: null,
    ref: null, booked: false, depositDue: 0, paying: false,
    client: { name: '', phone: '', email: '', note: '', reminder: true }, busy: false
  };

  var chosen = function () { return state.services.map(function (r) { return SERVICES[r]; }).filter(Boolean); };
  var subtotal = function () { return chosen().reduce(function (t, s) { return t + s.price; }, 0); };
  var totalDur = function () { return chosen().reduce(function (t, s) { return t + s.duration; }, 0); };
  var cats = function () {
    var out = []; chosen().forEach(function (s) { if (out.indexOf(s.cat) < 0) out.push(s.cat); }); return out;
  };
  var money = function (n) { return PBS.money(n); };
  var endTime = function (hhmm, mins) {
    if (!hhmm) return '';
    var p = hhmm.split(':'), t = (+p[0]) * 60 + (+p[1]) + mins;
    return String(Math.floor(t / 60) % 24).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
  };

  /* ------------------------------------------------------------- step 1 */
  function renderServices() {
    var host = $('#svcList'); if (!host) return;
    host.innerHTML = CAT.map(function (c) {
      return '<div class="mb-6"><div class="flex items-center gap-3 mb-3">' +
        '<span class="icon-badge icon-badge-sm">' + PBS.icon(c.icon) + '</span>' +
        '<h4 class="mb-0">' + PBS.esc(c.name) + '</h4></div>' +
        '<div class="grid" style="gap:8px">' +
        c.services.map(function (s) {
          var on = state.services.indexOf(s.ref) > -1;
          return '<button type="button" class="pick' + (on ? ' selected' : '') + '" data-svc="' + s.ref + '">' +
            '<span class="tick">✓</span><span class="info"><b>' + PBS.esc(s.name) + '</b>' +
            '<small>' + PBS.dur(s.duration) + ' · ' + PBS.esc(s.desc || '') + '</small></span>' +
            '<span class="price">' + money(s.price) + '</span></button>';
        }).join('') + '</div></div>';
    }).join('');

    $$('[data-svc]', host).forEach(function (b) {
      b.addEventListener('click', function () {
        var ref = b.getAttribute('data-svc'), i = state.services.indexOf(ref);
        if (i > -1) state.services.splice(i, 1); else state.services.push(ref);
        b.classList.toggle('selected', i === -1);
        state.date = null; state.time = null;      // availability depends on the mix
        syncSummary();
      });
    });
  }

  /* ------------------------------------------------------------- step 2 */
  function renderStaff() {
    var host = $('#staffList'); if (!host) return;
    host.innerHTML = '<p class="muted small">Checking who is available…</p>';

    PBS.api(API + 'staff.php?categories=' + encodeURIComponent(cats().join(',')))
      .then(function (r) {
        var list = r.staff || [];
        host.innerHTML =
          '<button type="button" class="pick' + (state.staff === 'any' ? ' selected' : '') + '" data-staff="any">' +
            '<span class="tick">✓</span><span class="avatar avatar-lg">' + PBS.icon('users') + '</span>' +
            '<span class="info"><b>Any available stylist</b>' +
            '<small>Fastest booking — we assign the best free specialist</small></span></button>' +
          list.map(function (s) {
            return '<button type="button" class="pick' + (state.staff === s.ref ? ' selected' : '') +
              '" data-staff="' + s.ref + '">' +
              '<span class="tick">✓</span>' +
              '<span class="avatar avatar-lg' + (s.tone === 'pink' ? ' avatar-pink' : '') + '">' +
                PBS.initials(s.name) + '</span>' +
              '<span class="info"><b>' + PBS.esc(s.name) + '</b><small>' + PBS.esc(s.role) +
                ' · ★ ' + s.rating.toFixed(1) + ' (' + s.reviews + ' reviews)</small></span></button>';
          }).join('') +
          (list.length ? '' :
            '<div class="alert alert-warning mt-3"><span class="ico">' + PBS.icon('alert') + '</span>' +
            '<span><b>No single stylist covers all of these</b><p>Choose “Any available stylist” and we ' +
            'will split the appointment across the team, or book them separately.</p></span></div>');

        $$('[data-staff]', host).forEach(function (b) {
          b.addEventListener('click', function () {
            state.staff = b.getAttribute('data-staff');
            state.date = null; state.time = null;
            $$('[data-staff]', host).forEach(function (x) { x.classList.remove('selected'); });
            b.classList.add('selected');
            syncSummary();
          });
        });
      })
      .catch(function (err) {
        host.innerHTML = '<div class="alert alert-danger"><span class="ico">' + PBS.icon('alert') +
          '</span><span><b>Could not load stylists</b><p>' + PBS.esc(err.message) + '</p></span></div>';
      });
  }

  /* ------------------------------------------------------------- step 3 */
  function renderDates() {
    var host = $('#dateStrip'); if (!host) return;
    host.innerHTML = '<p class="muted small mb-0">Checking availability…</p>';

    PBS.api(API + 'slots.php?services=' + state.services.join(',') +
            '&staff=' + encodeURIComponent(state.staff))
      .then(function (r) {
        host.innerHTML = (r.days || []).map(function (day) {
          var none = day.free === 0;
          return '<button type="button" class="date-pill' +
            (state.date === day.date ? ' selected' : '') + (none ? ' disabled' : '') +
            '" data-date="' + day.date + '"' + (none ? ' aria-disabled="true"' : '') + '>' +
            '<span class="dow">' + day.label + '</span>' +
            '<span class="d">' + day.day + '</span>' +
            '<span class="mo">' + (none ? 'Full' : day.month) + '</span></button>';
        }).join('');

        $$('[data-date]:not(.disabled)', host).forEach(function (b) {
          b.addEventListener('click', function () {
            state.date = b.getAttribute('data-date'); state.time = null;
            $$('[data-date]', host).forEach(function (x) { x.classList.remove('selected'); });
            b.classList.add('selected');
            renderSlots(); syncSummary();
          });
        });
      })
      .catch(function (err) {
        host.innerHTML = '<p class="small t-pink mb-0">' + PBS.esc(err.message) + '</p>';
      });
  }

  function renderSlots() {
    var host = $('#slotGrid'); if (!host) return;
    if (!state.date) {
      host.innerHTML = '<p class="muted small mb-0">Pick a date above to see available times.</p>';
      return;
    }
    host.innerHTML = '<p class="muted small mb-0">Loading times…</p>';

    PBS.api(API + 'slots.php?date=' + state.date + '&services=' + state.services.join(',') +
            '&staff=' + encodeURIComponent(state.staff))
      .then(function (r) {
        if (!r.slots.length) {
          host.innerHTML = '<p class="muted small mb-0">We are closed that day, or the service ' +
            'will not fit before closing. Try another date.</p>';
          return;
        }
        host.innerHTML = r.slots.map(function (s) {
          return '<button type="button" class="slot' + (s.available ? '' : ' taken') +
            (state.time === s.time ? ' selected' : '') + '" data-time="' + s.time + '"' +
            (s.available ? '' : ' aria-disabled="true"') + '>' + s.time + '</button>';
        }).join('');
        $$('[data-time]:not(.taken)', host).forEach(function (b) {
          b.addEventListener('click', function () {
            state.time = b.getAttribute('data-time');
            $$('[data-time]', host).forEach(function (x) { x.classList.remove('selected'); });
            b.classList.add('selected');
            syncSummary();
          });
        });
      })
      .catch(function (err) {
        host.innerHTML = '<p class="small t-pink mb-0">' + PBS.esc(err.message) + '</p>';
      });
  }

  /* ------------------------------------------------------------- step 5 */
  function renderReview() {
    var host = $('#reviewBox'); if (!host) return;
    var stf = state.staff === 'any' ? 'Any available stylist' : (w.PBS_STAFF_NAMES || {})[state.staff] || state.staff;
    host.innerHTML =
      chosen().map(function (s) {
        return '<div class="ticket-row"><span>Service</span><b>' + PBS.esc(s.name) +
          '<br><small class="muted" style="font-weight:400">' + PBS.dur(s.duration) + '</small></b></div>';
      }).join('') +
      '<div class="ticket-row"><span>Stylist</span><b>' + PBS.esc(stf) + '</b></div>' +
      '<div class="ticket-row"><span>Date</span><b>' + (state.date
        ? new Date(state.date + 'T00:00:00').toLocaleDateString('en-GB',
            { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '—') + '</b></div>' +
      '<div class="ticket-row"><span>Time</span><b>' + (state.time || '—') +
        (state.time ? ' – ' + endTime(state.time, totalDur()) : '') + '</b></div>' +
      '<div class="ticket-row"><span>Name</span><b>' + PBS.esc(state.client.name || '—') + '</b></div>' +
      '<div class="ticket-row"><span>Phone</span><b>' + PBS.esc(state.client.phone || '—') + '</b></div>' +
      (state.client.note ? '<div class="ticket-row"><span>Notes</span><b>' + PBS.esc(state.client.note) + '</b></div>' : '') +
      '<div class="ticket-row"><span>Total due</span><b class="t-pink" style="font-size:1.25rem">' +
        money(subtotal()) + '</b></div>';
  }

  /* ------------------------------------------------------------ summary */
  function syncSummary() {
    var box = $('#summaryBody'); if (!box) return;
    var svcs = chosen();
    if (!svcs.length) {
      box.innerHTML = '<p class="empty-note">Nothing selected yet. Start by choosing one or more services.</p>';
    } else {
      var stf = state.staff === 'any' ? 'Any available stylist' : (w.PBS_STAFF_NAMES || {})[state.staff] || state.staff;
      box.innerHTML = svcs.map(function (s) {
        return '<div class="line"><span>' + PBS.esc(s.name) + '<br><small>' + PBS.dur(s.duration) +
          '</small></span><b>' + money(s.price) + '</b></div>';
      }).join('') +
        '<div class="line"><span>Stylist</span><b>' + PBS.esc(stf) + '</b></div>' +
        '<div class="line"><span>When</span><b>' + (state.date
          ? new Date(state.date + 'T00:00:00').toLocaleDateString('en-GB',
              { weekday: 'short', day: 'numeric', month: 'short' }) : '—') +
          (state.time ? '<br>' + state.time + ' – ' + endTime(state.time, totalDur()) : '') + '</b></div>' +
        '<div class="line"><span>Duration</span><b>' + PBS.dur(totalDur()) + '</b></div>';
    }
    var t = $('#summaryTotal'); if (t) t.textContent = money(subtotal());

    var dep = $('#summaryDeposit');
    if (dep) {
      var due = depositDue();
      dep.innerHTML = due > 0
        ? '<div class="line" style="border-top:1px solid rgba(255,255,255,.12);margin-top:8px;padding-top:8px">' +
          '<span>Deposit to pay now</span><b>' + money(due) + '</b></div>' +
          '<div class="line"><span>Balance at the salon</span><b>' + money(subtotal() - due) + '</b></div>'
        : '';
    }
    var c = $('#summaryCount');
    if (c) c.textContent = svcs.length + (svcs.length === 1 ? ' service' : ' services');
  }

  /* Keeps the Payment card on the review step honest about THIS basket:
     a deposit only applies above the threshold, and saying "a deposit is
     required" on a 3,000 FCFA manicure would simply be untrue. */
  function syncPayBlock() {
    var intro = $('#payIntro');
    if (!intro) return;
    var due = depositDue();
    if (due > 0) {
      intro.innerHTML = 'A deposit of <b>' + money(due) + '</b> holds your slot. ' +
        'The balance of ' + money(subtotal() - due) + ' is paid at the salon.';
    } else if (PAY.state === 'live') {
      intro.innerHTML = 'No deposit is needed for this booking — you pay in full at the salon.';
    }
    /* Method choice is pointless when nothing is owed. */
    var methods = $('.pay-methods', $('#payBlock'));
    if (methods) methods.style.display = due > 0 ? '' : 'none';
  }

  /* ------------------------------------------------------------- step 6 */

  function done() {
    w.location.href = 'confirmation.php?ref=' + encodeURIComponent(state.ref) +
      '&phone=' + encodeURIComponent(state.client.phone);
  }

  function renderPayPanel() {
    var host = $('#payPanel'); if (!host) return;
    var ready = (PAY.providers || []).filter(function (p) { return p.ready; });

    host.innerHTML =
      '<div class="pay-due"><span>Deposit to pay</span><b>' + money(state.depositDue) + '</b></div>' +
      '<p class="small muted mt-2">Booking <b>' + PBS.esc(state.ref) + '</b> is held for you.</p>' +
      '<div class="pay-methods mt-4" id="payPick">' +
        ready.map(function (p) {
          return '<label class="pay-method"><input type="radio" name="payWith" value="' + p.code + '">' +
            '<span class="pay-logo pay-' + p.code + '">' + PBS.esc(p.short) + '</span>' +
            '<span class="small muted">' + PBS.esc(p.hint) + '</span></label>';
        }).join('') +
      '</div>' +
      '<div class="field mt-4"><label class="label" for="payPhone">Mobile Money number</label>' +
        '<input class="input" id="payPhone" inputmode="tel" placeholder="6 55 99 88 77" value="' +
          PBS.esc(state.client.phone || '') + '">' +
        '<p class="tiny muted mt-1">The number that will be charged. It does not have to be ' +
          'the one you booked with.</p></div>' +
      '<div id="payStatus" class="mt-4"></div>' +
      '<div class="btn-row mt-4">' +
        '<button class="btn btn-gold" id="payNow">Pay ' + money(state.depositDue) + '</button>' +
        '<button class="btn btn-ghost" id="paySkip">Pay at the salon instead</button>' +
      '</div>';

    var first = $('input[name=payWith]', host);
    if (first) first.checked = true;

    $('#payNow', host).addEventListener('click', startPayment);
    $('#paySkip', host).addEventListener('click', function () {
      /* Never a dead end. The booking already exists, so leaving without
         paying is a legitimate outcome rather than an error. */
      PBS.toast('No problem. Your booking is held, please settle at the salon.', 'info');
      done();
    });
  }

  function paySay(html, kind) {
    var box = $('#payStatus'); if (!box) return;
    box.innerHTML = '<div class="alert alert-' + (kind || 'info') + '">' +
      '<span class="ico" data-icon="' + (kind === 'danger' ? 'alert' : 'clock') + '"></span>' +
      '<span>' + html + '</span></div>';
    if (PBS.paintIcons) PBS.paintIcons(box);
  }

  function startPayment() {
    if (state.paying) return;
    var picked = $('input[name=payWith]:checked');
    if (!picked) { PBS.toast('Choose MTN MoMo or Orange Money.', 'error'); return; }
    var phone = ($('#payPhone') || {}).value || '';
    if (phone.replace(/\D/g, '').length < 9) {
      PBS.toast('Enter the Mobile Money number to charge.', 'error'); return;
    }

    state.paying = true;
    $('#payNow').classList.add('is-disabled');
    paySay('Sending the request to your phone…');

    PBS.api(API + 'payment.php', {
      method: 'POST',
      body: { action: 'start', ref: state.ref, phone: state.client.phone,
              provider: picked.value, payer_phone: phone }
    }).then(function (r) {
      if (r.redirect_url) { w.location.href = r.redirect_url; return; }
      paySay(PBS.esc(r.message || 'Approve the payment on your phone.'));
      pollPayment(r.ref, 0);
    }).catch(function (err) {
      state.paying = false;
      $('#payNow').classList.remove('is-disabled');
      paySay(PBS.esc(err.message) +
        '<p class="mb-0 mt-1">Your booking is still held. You can try again or pay at the salon.</p>',
        'danger');
    });
  }

  function pollPayment(payRef, tries) {
    if (tries > 40) {                       /* about two minutes at 3s */
      state.paying = false;
      $('#payNow').classList.remove('is-disabled');
      paySay('We did not hear back in time. Your booking is held, so try again or pay at the salon.',
             'danger');
      return;
    }
    w.setTimeout(function () {
      PBS.api(API + 'payment.php', {
        method: 'POST', body: { action: 'status', payment_ref: payRef }
      }).then(function (r) {
        if (r.status === 'succeeded') {
          paySay('<b>' + PBS.esc(r.message) + '</b>', 'success');
          w.setTimeout(done, 1200);
          return;
        }
        if (r.status === 'pending') { pollPayment(payRef, tries + 1); return; }
        state.paying = false;
        $('#payNow').classList.remove('is-disabled');
        paySay(PBS.esc(r.message) +
          '<p class="mb-0 mt-1">Your booking is still held.</p>', 'danger');
      }).catch(function () { pollPayment(payRef, tries + 1); });
    }, 3000);
  }

  function renderStepper() {
    var host = $('#stepper'); if (!host) return;
    var labels = ['Service', 'Stylist', 'Date & Time', 'Details', 'Confirm'];
    if (state.booked && state.depositDue > 0) labels.push('Pay');
    host.innerHTML = labels.map(function (l, i) {
      var n = i + 1, cls = n < state.step ? 'done' : (n === state.step ? 'active' : '');
      return '<div class="step ' + cls + '"><span class="step-dot">' + (n < state.step ? '✓' : n) +
        '</span><span class="step-label">' + l + '</span></div>' +
        (n < labels.length ? '<span class="step-line"></span>' : '');
    }).join('');
  }

  function validate(step) {
    if (step === 1 && !state.services.length) { PBS.toast('Please choose at least one service.', 'error'); return false; }
    if (step === 3 && (!state.date || !state.time)) { PBS.toast('Please choose a date and a time slot.', 'error'); return false; }
    if (step === 4) {
      var f = $('#detailsForm');
      if (f && !f.reportValidity()) return false;
      state.client.name = $('#cName').value.trim();
      state.client.phone = $('#cPhone').value.trim();
      state.client.email = $('#cEmail').value.trim();
      state.client.note = $('#cNotes').value.trim();
      state.client.reminder = $('#cReminder').checked;
    }
    return true;
  }

  function goto(n) {
    if (n > state.step && !validate(state.step)) return;
    /* Step 6 exists only after a booking has been made and a deposit is
       actually owed. Clamping to TOTAL otherwise keeps it unreachable. */
    var ceiling = state.booked && state.depositDue > 0 ? 6 : TOTAL;
    state.step = Math.max(1, Math.min(ceiling, n));
    $$('.wizard-step').forEach(function (s) {
      s.classList.toggle('active', +s.getAttribute('data-step') === state.step);
    });
    renderStepper();
    if (state.step === 2) renderStaff();
    if (state.step === 3) { renderDates(); renderSlots(); }
    if (state.step === 5) { renderReview(); syncPayBlock(); }
    if (state.step === 6) renderPayPanel();
    /* On the payment step the wizard is finished: going back would offer to
       rebook a slot that is already held, and the only ways out are paying
       or choosing to settle at the salon. */
    $('#btnBack').classList.toggle('hidden', state.step === 1 || state.step === 6);
    $('#btnNext').classList.toggle('hidden', state.step >= TOTAL);
    $('#btnConfirm').classList.toggle('hidden', state.step !== TOTAL);
    w.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function confirm() {
    if (!validate(4) || state.busy) return;
    state.busy = true;
    var btn = $('#btnConfirm');
    btn.classList.add('is-disabled');
    btn.textContent = 'Booking…';

    PBS.api(API + 'bookings.php', {
      method: 'POST',
      body: {
        services: state.services, staff: state.staff,
        date: state.date, time: state.time,
        name: state.client.name, phone: state.client.phone,
        email: state.client.email, note: state.client.note,
        reminder: state.client.reminder ? 1 : 0
      }
    }).then(function (r) {
      state.ref = r.ref;
      state.booked = true;
      state.depositDue = +r.deposit_due || 0;

      /* The booking is made. Anything that happens from here can fail without
         costing the client their slot. */
      if (state.depositDue > 0) {
        state.busy = false;
        goto(6);
        return;
      }
      done();
    }).catch(function (err) {
      state.busy = false;
      btn.classList.remove('is-disabled');
      btn.textContent = 'Confirm Booking';
      PBS.toast(err.message, 'error');
      /* the slot may have gone while they filled the form — send them back */
      if (/slot|taken|opening hours/i.test(err.message)) {
        state.time = null;
        goto(3);
      }
    });
  }

  d.addEventListener('DOMContentLoaded', function () {
    if (!$('#bookingWizard')) return;

    var q = new URLSearchParams(w.location.search);
    if (q.get('service') && SERVICES[q.get('service')]) state.services.push(q.get('service'));
    if (q.get('cat')) {
      CAT.forEach(function (c) {
        if (c.slug === q.get('cat')) {
          var pop = c.services.filter(function (s) { return s.popular; })[0] || c.services[0];
          if (pop) state.services.push(pop.ref);
        }
      });
    }
    if (q.get('staff')) state.staff = q.get('staff');

    renderServices(); renderStepper(); syncSummary();
    goto(state.services.length ? 2 : 1);

    $('#btnNext').addEventListener('click', function () { goto(state.step + 1); });
    $('#btnBack').addEventListener('click', function () { goto(state.step - 1); });
    $('#btnConfirm').addEventListener('click', confirm);
    $$('[data-goto]').forEach(function (b) {
      b.addEventListener('click', function () { goto(+b.getAttribute('data-goto')); });
    });
  });

})(window, document);
