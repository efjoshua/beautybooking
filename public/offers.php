<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$cats   = repo_categories();
$offers = repo_offers();

public_head([
    'title' => 'Offers &amp; Packages — ' . APP_NAME,
    'description' => 'Bridal packages, braids-and-nails combos, self-care Sundays and loyalty rewards.',
    'page' => 'offers.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Offers') ?></div>
    <h1><?= te('Offers & Packages') ?></h1>
    <p><?= te('Bundles that genuinely save you money, not just services listed together. Mention the offer when you book — we apply it at the till.') ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="chips chips-scroll mb-8" id="offerFilters">
      <button class="chip active" data-filter="all"><?= te('All Offers') ?></button>
      <?php foreach ($cats as $c): ?>
        <button class="chip" data-filter="<?= e($c['slug']) ?>"><?= e(tr($c, 'name', 'service_categories')) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="grid g-3" id="offerGrid">
      <?php foreach ($offers as $o): $priced = (float) $o['price'] > 0; ?>
        <article class="offer-card reveal" data-cat="<?= e((string) $o['category_slug']) ?>">
          <div class="offer-top">
            <?php if ($o['badge']): ?><span class="offer-badge"><?= e($o['badge']) ?></span><?php endif; ?>
            <span class="icon-badge icon-badge-sm mb-4" data-icon="<?= e($o['category_icon'] ?: 'crown') ?>"></span>
            <h3><?= e($o['title']) ?></h3>
            <p class="small mb-0" style="color:rgba(255,255,255,.66)"><?= e($o['blurb']) ?></p>
            <?php if ($priced): ?>
              <div class="offer-price"><b><?= e(money($o['price'])) ?></b>
                <s><?= e(money($o['was_price'])) ?></s></div>
              <span class="tiny" style="color:var(--pink-light)">You save
                <?= e(money((float) $o['was_price'] - (float) $o['price'])) ?></span>
            <?php else: ?>
              <div class="offer-price"><b>Ask in salon</b></div>
            <?php endif; ?>
          </div>
          <div class="offer-body">
            <span class="eyebrow" style="margin-bottom:8px">Includes</span>
            <ul><?php foreach ($o['items'] as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul>
            <p class="tiny muted">Valid until <?= e($o['valid_until']) ?></p>
            <a href="book.php<?= $o['category_slug'] ? '?cat=' . e($o['category_slug']) : '' ?>"
               class="btn btn-primary btn-block">Book this offer</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="alert alert-info mt-10" style="max-width:760px;margin-inline:auto">
      <span class="ico" data-icon="alert"></span>
      <span><b>How offers work</b>
        <p>One offer per visit. Packages are booked as a single appointment so your stylists
           can work in parallel where possible. Loyalty counts are tracked against your phone number.</p></span>
    </div>
  </div>
</section>

<section class="section bg-cream">
  <div class="wrap wrap-narrow text-center">
    <span class="eyebrow">Gift Cards</span>
    <h2>Give someone a very good day</h2>
    <div class="gold-rule"></div>
    <p class="lead">Gift cards start at 10,000 <?= e(CURRENCY) ?> and never expire. Buy one at
       the salon or ask us on WhatsApp and we will send a digital card.</p>
    <div class="flex gap-3 wrapflex mt-6" style="justify-content:center">
      <a href="https://wa.me/<?= e(setting('whatsapp', '')) ?>" target="_blank" rel="noopener"
         class="btn btn-dark">Ask about gift cards</a>
      <a href="contact.php" class="btn btn-outline">Contact us</a>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold">Ready?</span>
    <h2 class="t-white">Book a package before the weekend fills up</h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Book an Appointment') ?></a></div>
</section>

<?php public_foot(['page' => 'offers.php', 'inline' => '<script>
document.addEventListener("DOMContentLoaded", function () {
  PBS.initFilters("#offerFilters .chip", "#offerGrid .offer-card");
});
</script>']); ?>
