<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$hours = repo_business_hours();
$faqs  = array_slice(repo_faqs(), 0, 4);
$todayIdx = (int) date('N') - 1;
$phone = setting('phone', '');
$digits = preg_replace('/\s/', '', $phone);

public_head([
    'title' => 'Contact &amp; Location — ' . APP_NAME,
    'description' => 'Find Princess Beauty Salon at Carrefour Obili, Yaoundé. Call, WhatsApp or send us a message.',
    'page' => 'contact.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Contact') ?></div>
    <h1><?= te('Get in Touch') ?></h1>
    <p><?= te('Quickest answer is always WhatsApp — we reply within about two hours during opening times.') ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="grid g-2 gap-lg" style="align-items:start">
      <div>
        <span class="eyebrow">Reach Us</span>
        <h2>Three ways to talk to us</h2>
        <div class="gold-rule" style="margin-left:0"></div>

        <div class="grid" style="gap:12px;margin-top:28px">
          <a href="https://wa.me/<?= e(setting('whatsapp', '')) ?>" target="_blank" rel="noopener" class="card card-hover">
            <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="whatsapp"></span>
              <div><h4>WhatsApp</h4><p><?= e($phone) ?> — fastest for booking changes</p></div></div></a>
          <a href="tel:<?= e($digits) ?>" class="card card-hover">
            <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="phone"></span>
              <div><h4>Call the salon</h4><p><?= e($phone) ?> — during opening hours</p></div></div></a>
          <a href="mailto:<?= e(setting('email', '')) ?>" class="card card-hover">
            <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="mail"></span>
              <div><h4>Email</h4><p><?= e(setting('email', '')) ?> — for bookings of 3+ people</p></div></div></a>
        </div>

        <div class="card mt-6"><h4 class="mb-4"><?= te('Opening hours') ?></h4>
          <?php foreach ($hours as $i => $h): ?>
            <div class="hours-row<?= $i === $todayIdx ? ' today' : '' ?>">
              <span><?= e(is_fr() ? fr_days(true)[(int) $h['weekday'] + 1] : $h['day_name']) ?><?= $i === $todayIdx ? ' · ' . t('Today') : '' ?></span>
              <b><?= (int) $h['is_closed'] ? te('Closed')
                 : e(fmt_time($h['opens_at']) . ' – ' . fmt_time($h['closes_at'])) ?></b></div>
          <?php endforeach; ?>
        </div>

        <div class="card mt-6"><h4 class="mb-3">Follow along</h4>
          <p class="small muted">New work goes up daily on Instagram and TikTok.</p>
          <div class="flex gap-2 wrapflex">
            <a href="https://instagram.com/" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Instagram</a>
            <a href="https://tiktok.com/" target="_blank" rel="noopener" class="btn btn-outline btn-sm">TikTok</a>
            <a href="https://facebook.com/" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Facebook</a>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <h3 class="mb-2">Send us a message</h3>
          <p class="small muted">For anything that isn't urgent. To book, use
            <a href="book.php" class="t-pink">the booking page</a> — it's faster.</p>
          <form id="contactForm" class="mt-5">
            <div class="field-row">
              <div class="field"><label class="label" for="fName">Your name <span class="req">*</span></label>
                <input class="input" id="fName" name="name" required placeholder="Full name"></div>
              <div class="field"><label class="label" for="fPhone">Phone <span class="req">*</span></label>
                <input class="input" id="fPhone" name="phone" type="tel" required placeholder="+237 6 XX XX XX XX"></div>
            </div>
            <div class="field"><label class="label" for="fEmail">Email</label>
              <input class="input" id="fEmail" name="email" type="email" placeholder="you@example.com"></div>
            <div class="field"><label class="label" for="fSubject">What's this about?</label>
              <select class="select" id="fSubject" name="subject">
                <option>General enquiry</option><option>Change or cancel a booking</option>
                <option>Group / bridal party booking</option><option>Pricing question</option>
                <option>Working with us</option><option>Feedback or complaint</option>
              </select></div>
            <div class="field"><label class="label" for="fMessage">Message <span class="req">*</span></label>
              <textarea class="textarea" id="fMessage" name="message" required
                placeholder="Tell us what you need…"></textarea></div>
            <label class="check mb-5"><input type="checkbox" name="opt_in" value="1" checked>
              Send me occasional offers on WhatsApp (no more than twice a month)</label>
            <button type="submit" class="btn btn-primary btn-block"><?= te('Send Message') ?></button>
          </form>
        </div>

        <div class="map-embed mt-6"><div class="map-pin"><span class="dot"></span>
          <b><?= e(APP_NAME) ?></b><div class="tiny muted"><?= e(setting('address', '')) ?></div></div></div>
        <div class="grid g-2 mt-3" style="gap:10px">
          <a href="https://maps.google.com/?q=<?= rawurlencode(setting('address', '')) ?>"
             target="_blank" rel="noopener" class="btn btn-dark btn-sm">Open in Google Maps</a>
          <a href="book.php" class="btn btn-outline btn-sm"><?= te('Book an Appointment') ?></a>
        </div>
        <p class="tiny muted mt-3 mb-0">Parking available along the main road. We're on the
          first floor — look for the gold crown sign.</p>
      </div>
    </div>
  </div>
</section>

<section class="section bg-soft">
  <div class="wrap wrap-narrow">
    <div class="section-head"><span class="eyebrow">FAQ</span>
      <h2>Quick answers</h2><div class="gold-rule"></div></div>
    <div class="grid" style="gap:12px">
      <?php foreach ($faqs as $f): ?>
        <div class="card"><h4><?= e($f['question']) ?></h4>
          <p class="small muted mb-0"><?= e($f['answer']) ?></p></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-6"><a href="faq.php" class="link-arrow"><?= te('See all questions') ?> →</a></div>
  </div>
</section>

<?php public_foot(['page' => 'contact.php', 'inline' => '<script>
document.getElementById("contactForm").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var btn = this.querySelector("button[type=submit]");
  btn.classList.add("is-disabled"); btn.textContent = "Sending…";
  PBS.api(' . json_encode(url('api/contact.php')) . ', { method: "POST", body: new FormData(this) })
    .then(function (r) {
      PBS.toast(r.message);
      document.getElementById("contactForm").reset();
    })
    .catch(function (err) { PBS.toast(err.message, "error"); })
    .then(function () { btn.classList.remove("is-disabled"); btn.textContent = "Send Message"; });
});
</script>']); ?>
