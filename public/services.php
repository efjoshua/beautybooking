<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$cats = repo_categories();
$svcs = repo_services();

public_head([
    'title' => 'Services &amp; Prices — ' . APP_NAME,
    'description' => 'Full price list: hair cuts and styling, braids and plating, nails and nail art, facials and lashes, massage and spa.',
    'page' => 'services.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Services') ?></div>
    <h1><?= te('Services & Prices') ?></h1>
    <p><?= te('Everything we offer, what it costs and how long to set aside. Prices are in :cur and include consultation — no hidden extras at the till.', ['cur' => CURRENCY]) ?></p>
    <a href="book.php" class="btn btn-primary mt-4"><?= te('Book an Appointment') ?></a>
  </div>
</section>

<div class="jump-bar" id="jumpBar">
  <div class="wrap"><div class="chips" id="catJump">
    <?php foreach ($cats as $c): ?>
      <a class="chip" href="#<?= e($c['slug']) ?>" data-jump="<?= e($c['slug']) ?>"><?= e(tr($c, 'short_name', 'service_categories')) ?></a>
    <?php endforeach; ?>
  </div></div>
</div>

<section class="section">
  <div class="wrap">
    <?php foreach ($cats as $c):
      $rows = array_values(array_filter($svcs, fn($s) => $s['category_slug'] === $c['slug'])); ?>
      <section class="section-sm" id="<?= e($c['slug']) ?>">
        <div class="flex items-center gap-5 mb-6 wrapflex">
          <span class="icon-badge" data-icon="<?= e($c['icon']) ?>"></span>
          <div style="flex:1;min-width:220px">
            <h2 class="mb-0"><?= e(tr($c, 'name', 'service_categories')) ?></h2>
            <p class="muted mb-0"><?= e(tr($c, 'blurb', 'service_categories')) ?></p></div>
          <span class="badge badge-gold"><?= count($rows) ?> services</span>
        </div>
        <div class="grid" style="gap:12px">
          <?php foreach ($rows as $s): ?>
            <div class="svc-row reveal">
              <span class="icon-badge icon-badge-sm" data-icon="<?= e($c['icon']) ?>"></span>
              <div class="info">
                <h4><?= e(tr($s, 'name', 'services')) ?><?= (int) $s['is_popular']
                  ? ' <span class="badge badge-pink badge-plain tiny">' . te('Popular') . '</span>' : '' ?></h4>
                <p><?= e(tr($s, 'description', 'services')) ?></p>
              </div>
              <div class="pricing">
                <div class="amt"><?= e(money($s['price'])) ?></div>
                <div class="dur"><?= e(duration((int) $s['duration_minutes'])) ?></div>
              </div>
              <a href="book.php?service=<?= e($s['ref']) ?>" class="btn btn-outline btn-sm">Book</a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</section>

<section class="section bg-soft">
  <div class="wrap wrap-narrow">
    <div class="section-head"><span class="eyebrow">Good To Know</span>
      <h2>Before you book</h2><div class="gold-rule"></div></div>
    <div class="grid g-2">
      <div class="card"><h4>Deposits</h4>
        <p class="small muted mb-0">Services over <?= e(money((float) setting('deposit_threshold', '20000'))) ?>
          may require a <?= e(setting('deposit_percent', '30')) ?>% MoMo deposit to hold the slot,
          especially on Saturdays.</p></div>
      <div class="card"><h4>Cancellations</h4>
        <p class="small muted mb-0">Free up to <?= e(setting('free_cancel_hours', '4')) ?> hours before
          your appointment. After that the deposit is retained.</p></div>
      <div class="card"><h4>Bring your own hair</h4>
        <p class="small muted mb-0">Braid prices cover labour. We stock Kanekalon and
          pre-stretched hair at the counter if you'd rather buy here.</p></div>
      <div class="card"><h4>Running late?</h4>
        <p class="small muted mb-0">Message us on WhatsApp. We hold your chair 15 minutes
          before the slot is released.</p></div>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold">Pick Your Treatment</span>
    <h2 class="t-white">Book any service in under two minutes</h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Start Booking') ?></a></div>
</section>

<?php public_foot(['page' => 'services.php',
  'inline' => '<script>document.addEventListener("DOMContentLoaded",function(){PBS.initJumpBar("#jumpBar","#catJump");});</script>']); ?>
