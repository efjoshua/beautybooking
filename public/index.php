<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$cats    = repo_categories();
$svcs    = repo_services();
$staff   = repo_staff(true);
$gallery = repo_gallery_showcase(8);
$reviews = repo_reviews(3);
$hours   = repo_business_hours();

/* One representative photo per folder for the collage beside "Why Princess".
   The second file, so it does not repeat the first tile of the strip below.
   Empty folder → null → photo() draws the gradient tile it always drew. */
$collage = [];
foreach (['hair-styles', 'manicure', 'lashes'] as $folder) {
    $shots = repo_folder_images($folder, 2);
    $collage[$folder] = $shots[1] ?? ($shots[0] ?? null);
}

/* Headline numbers, computed rather than asserted. */
$clientCount = (int) db_val('SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL');
$reviewCount = (int) db_val('SELECT COALESCE(SUM(reviews_count), 0) FROM staff WHERE deleted_at IS NULL');
$avgRating   = (float) db_val('SELECT COALESCE(AVG(rating), 5) FROM staff WHERE deleted_at IS NULL AND is_public = 1');

$fromPrice = [];
foreach ($cats as $c) {
    $p = array_map(fn($s) => (float) $s['price'],
         array_filter($svcs, fn($s) => $s['category_slug'] === $c['slug']));
    $fromPrice[$c['slug']] = ['min' => $p ? min($p) : 0, 'n' => count($p)];
}
$todayIdx = (int) date('N') - 1;

public_head([
    'title' => APP_NAME . ' — ' . setting('tagline', ''),
    'description' => t("Yaoundé's home of elegant hair, braids, nails, lashes and spa care. Book online in under two minutes."),
    'page' => 'index.php',
]);
?>

<section class="hero">
  <div class="wrap hero-inner">
    <div>
      <span class="eyebrow t-gold"><?= te('Yaoundé · Since 2019') ?></span>
      <h1><?= t('Where every woman is treated like <em>royalty</em>.') ?></h1>
      <span class="hero-tag"><?= e(setting('tagline', '')) ?></span>
      <p><?= te('Hair, braids, nails, lashes and spa — all under one roof, by specialists who actually listen. Book in under two minutes, straight from your phone.') ?></p>
      <div class="hero-cta">
        <a href="book.php" class="btn btn-primary btn-lg"><?= te('Book an Appointment') ?></a>
        <a href="services.php" class="btn btn-outline-light btn-lg"><?= te('View Services & Prices') ?></a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><b data-count="<?= $clientCount ?>" data-suffix="+">0</b><span><?= te('Happy Clients') ?></span></div>
        <div class="hero-stat"><b data-count="<?= count($svcs) ?>">0</b><span><?= te('Services Offered') ?></span></div>
        <div class="hero-stat"><b data-count="<?= count($staff) ?>">0</b><span><?= te('Expert Stylists') ?></span></div>
        <div class="hero-stat"><b><?= number_format($avgRating, 1) ?></b><span><?= te('Average Rating') ?></span></div>
      </div>
    </div>

    <div class="hero-card">
      <div class="text-center"><?= wordmark('wordmark-lg wordmark-light') ?></div>
      <div class="hero-badges">
        <?php foreach ($cats as $c): ?>
          <a class="b" href="services.php#<?= e($c['slug']) ?>">
            <span class="icon-badge icon-badge-sm" data-icon="<?= e($c['icon']) ?>"></span>
            <span><?= e(tr($c, 'short_name', 'service_categories')) ?></span></a>
        <?php endforeach; ?>
      </div>
      <a href="book.php" class="btn btn-gold btn-block mt-8"><?= te('Reserve Your Chair') ?></a>
      <p class="tiny text-center mt-4 mb-0" style="color:rgba(255,255,255,.5)">
        <?= te('Free cancellation up to :h hours before', ['h' => setting('free_cancel_hours', '4')]) ?> ·
        <?= te('Pay cash, MoMo, Orange Money or card') ?></p>
    </div>
  </div>
</section>

<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): foreach ($cats as $c): ?>
      <span><?= e(tr($c, 'name', 'service_categories')) ?></span><span>◆</span>
    <?php endforeach; endfor; ?>
  </div>
</div>

<section class="section">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow"><?= te('What We Do') ?></span>
      <h2><?= te('Five ways to feel like yourself again') ?></h2>
      <div class="gold-rule"></div>
      <p><?= te('Every service is delivered by a specialist in that craft — never a generalist doing their best. Tap any category to see the full price list.') ?></p>
    </div>
    <div class="grid g-5">
      <?php foreach ($cats as $c): ?>
        <a href="services.php#<?= e($c['slug']) ?>" class="service-card reveal">
          <span class="icon-badge" data-icon="<?= e($c['icon']) ?>"></span>
          <h3><?= e(tr($c, 'name', 'service_categories')) ?></h3>
          <p><?= e(tr($c, 'blurb', 'service_categories')) ?></p>
          <span class="price"><?= te('From') ?> <?= e(money($fromPrice[$c['slug']]['min'])) ?></span>
          <span class="meta"><?= te(':n services available', ['n' => $fromPrice[$c['slug']]['n']]) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-10">
      <a href="services.php" class="link-arrow"><?= te('See all :n services & prices', ['n' => count($svcs)]) ?> →</a></div>
  </div>
</section>

<section class="section bg-cream">
  <div class="wrap">
    <div class="grid g-2 gap-xl" style="align-items:center">
      <div class="reveal">
        <span class="eyebrow"><?= te('Why Princess') ?></span>
        <h2><?= te('A salon built around your time, not ours') ?></h2>
        <div class="gold-rule" style="margin-left:0"></div>
        <p class="lead"><?= te('We got tired of watching women lose a whole Saturday to a salon that "runs late". So we rebuilt the whole thing around appointments that actually start on time.') ?></p>
        <div class="grid" style="gap:20px;margin-top:32px">
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="clock"></span>
            <div><h4><?= te('Booked slots, honoured') ?></h4>
              <p><?= te('Your stylist is blocked out for your full service length. No double-booking, ever.') ?></p></div></div>
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="crown"></span>
            <div><h4><?= te('Specialists, not generalists') ?></h4>
              <p><?= te('Braiders braid. Nail artists do nails. Lash techs are certified. That is the whole model.') ?></p></div></div>
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="wallet"></span>
            <div><h4><?= te('Pay how you actually pay') ?></h4>
              <p><?= te('Cash, MTN MoMo, Orange Money or card — settled at the chair, no surprises.') ?></p></div></div>
        </div>
        <a href="about.php" class="btn btn-dark mt-8"><?= te('Read Our Story') ?></a>
      </div>
      <div class="grid g-2" style="gap:12px">
        <?= photo(['path' => $collage['hair-styles'], 'tone' => 'ph-gold', 'label' => 'Braids',
                   'alt' => 'Knotless braids by Princess Beauty Salon',
                   'ratio' => '3/4', 'radius' => '16px']) ?>
        <div class="grid" style="gap:12px">
          <?= photo(['path' => $collage['manicure'], 'label' => 'Nails',
                     'alt' => 'Nail art from the salon', 'ratio' => '1', 'radius' => '16px']) ?>
          <?= photo(['path' => $collage['lashes'], 'tone' => 'ph-pink', 'label' => 'Lashes',
                     'alt' => 'Lash set from the salon', 'ratio' => '1', 'radius' => '16px']) ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow"><?= te('Booking') ?></span>
      <h2><?= te("Three taps and you're booked") ?></h2><div class="gold-rule"></div></div>
    <div class="grid g-3">
      <?php foreach ([
        ['01', t('Pick your service'), t("Browse by category, see the exact price and how long it takes. Add more than one if you're making a day of it.")],
        ['02', t('Choose stylist & time'), t('Request your favourite stylist or let us assign the first one free. Real availability, three weeks ahead.')],
        ['03', t('Confirm & arrive'), t('You get a reference code on the spot and a reminder the day before. Then just show up and relax.')],
      ] as [$n, $h, $p]): ?>
        <div class="card card-hover reveal text-center">
          <div class="script t-pink" style="font-size:3rem"><?= $n ?></div>
          <h3><?= $h ?></h3><p class="muted small mb-0"><?= $p ?></p></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-10"><a href="book.php" class="btn btn-primary btn-lg"><?= te('Start Booking') ?></a></div>
  </div>
</section>

<section class="section bg-soft">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow"><?= te('Our Work') ?></span>
      <h2><?= te('Straight from the chair') ?></h2><div class="gold-rule"></div>
      <p><?= te("Real clients, real results — the same feed you'll find on our Instagram and TikTok.") ?></p></div>
    <div class="gallery-grid">
      <?php foreach (array_slice($gallery, 0, 8) as $g): ?>
        <div class="gal-item reveal">
          <?= photo(['path' => $g['image_path'], 'tone' => $g['tone'],
                     'label' => $g['short_name'], 'alt' => $g['title']]) ?>
          <div class="ov"><b><?= e($g['title']) ?></b><span>by <?= e($g['staff_name'] ?: 'the team') ?></span></div></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-8"><a href="gallery.php" class="btn btn-outline"><?= te('View Full Portfolio') ?></a></div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow"><?= te('The Team') ?></span>
      <h2><?= te("The hands you'll be in") ?></h2><div class="gold-rule"></div></div>
    <div class="grid g-4">
      <?php foreach (array_slice($staff, 0, 4) as $s): ?>
        <article class="team-card reveal">
          <?= photo(['path' => staff_photo($s['photo']), 'class' => 'photo',
                     'tone' => $s['tone'] === 'gold' ? 'ph-gold' : 'ph-pink',
                     'label' => initials($s['name']), 'alt' => $s['name']]) ?>
          <div class="body">
            <h4><?= e($s['name']) ?></h4>
            <div class="role"><?= e($s['job_title']) ?></div>
            <div class="stars mt-2"><?= str_repeat('★', (int) round((float) $s['rating'])) ?>
              <span class="tiny muted"><?= number_format((float) $s['rating'], 1) ?>
                (<?= (int) $s['reviews_count'] ?>)</span></div>
            <p class="small muted mt-3 mb-0"><?= e(tr($s, 'bio', 'staff')) ?></p>
            <a href="book.php?staff=<?= e($s['ref']) ?>" class="btn btn-outline btn-sm mt-4 full">
              <?= te('Book with :name', ['name' => explode(' ', $s['name'])[0]]) ?></a>
          </div></article>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-8"><a href="team.php" class="link-arrow"><?= te('Meet the full team') ?> →</a></div>
  </div>
</section>

<section class="section bg-blush">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow"><?= te('Kind Words') ?></span>
      <h2><?= te('What our clients say') ?></h2><div class="gold-rule"></div>
      <div class="stars" style="font-size:1.25rem">★★★★★</div>
      <p class="small muted"><?= te(':r average from :n reviews',
        ['r' => number_format($avgRating, 1), 'n' => number_format($reviewCount)]) ?></p></div>
    <div class="grid g-3">
      <?php foreach ($reviews as $r): ?>
        <div class="testimonial reveal">
          <div class="stars"><?= str_repeat('★', (int) $r['stars']) ?></div>
          <p>“<?= e($r['body']) ?>”</p>
          <div class="who"><span class="avatar avatar-pink"><?= e(initials($r['client_name'])) ?></span>
            <div><b class="small" style="color:var(--black)"><?= e($r['client_name']) ?></b>
              <div class="tiny muted"><?= e($r['service_label']) ?></div></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="grid g-2 gap-lg" style="align-items:start">
      <div class="reveal">
        <span class="eyebrow"><?= te('Find Us') ?></span><h2><?= te('Come through') ?></h2>
        <div class="gold-rule" style="margin-left:0"></div>
        <div class="grid" style="gap:20px;margin-top:28px">
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="pin"></span>
            <div><h4><?= te('Address') ?></h4><p><?= e(setting('address', '')) ?></p></div></div>
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="phone"></span>
            <div><h4><?= te('Call or WhatsApp') ?></h4><p><?= e(setting('phone', '')) ?></p></div></div>
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="mail"></span>
            <div><h4><?= te('Email') ?></h4><p><?= e(setting('email', '')) ?></p></div></div>
        </div>
        <div class="card mt-8"><h4 class="mb-4"><?= te('Opening hours') ?></h4>
          <?php foreach ($hours as $i => $h): ?>
            <div class="hours-row<?= $i === $todayIdx ? ' today' : '' ?>">
              <span><?= e(is_fr() ? fr_days(true)[(int) $h['weekday'] + 1] : $h['day_name']) ?><?= $i === $todayIdx ? ' · ' . t('Today') : '' ?></span>
              <b><?= (int) $h['is_closed'] ? te('Closed')
                 : e(fmt_time($h['opens_at']) . ' – ' . fmt_time($h['closes_at'])) ?></b></div>
          <?php endforeach; ?>
        </div>
        <a href="contact.php" class="btn btn-outline mt-6"><?= te('Get Directions') ?></a>
      </div>
      <div class="map-embed reveal"><div class="map-pin"><span class="dot"></span>
        <b><?= e(APP_NAME) ?></b><div class="tiny muted"><?= e(setting('address', '')) ?></div></div></div>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap">
    <span class="eyebrow t-gold"><?= te('Ready When You Are') ?></span>
    <h2 class="t-white" style="font-size:clamp(1.9rem,4vw,2.75rem)"><?= te('Your chair is waiting') ?></h2>
    <p style="max-width:52ch;margin:0 auto 32px;color:rgba(255,255,255,.7)">
      <?= te("Weekend slots fill up by Wednesday. Book now and we'll hold your spot.") ?></p>
    <div class="flex gap-3 wrapflex" style="justify-content:center">
      <a href="book.php" class="btn btn-primary btn-lg"><?= te('Book an Appointment') ?></a>
      <a href="tel:<?= e(preg_replace('/\s/', '', setting('phone', ''))) ?>" class="btn btn-outline-gold btn-lg"><?= te('Call the Salon') ?></a>
    </div>
  </div>
</section>

<?php public_foot(['page' => 'index.php']); ?>
