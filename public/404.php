<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';
http_response_code(404);

public_head(['title' => 'Page Not Found — ' . APP_NAME, 'page' => '404.php', 'noindex' => true]);
?>
<section class="section text-center">
  <div class="wrap wrap-narrow">
    <div class="err-code">404</div>
    <span class="script t-pink" style="font-size:clamp(1.5rem,4vw,2.25rem);display:block;margin-bottom:16px">
      This chair is empty</span>
    <div class="gold-rule"></div>
    <p class="lead">The page you were looking for has moved or never existed.
       Let's get you somewhere useful.</p>
    <div class="flex gap-3 wrapflex mt-8" style="justify-content:center">
      <a href="index.php" class="btn btn-primary"><?= te('Back to Home') ?></a>
      <a href="book.php" class="btn btn-outline"><?= te('Book an Appointment') ?></a>
    </div>
    <div class="grid g-3 mt-12" style="text-align:left">
      <?php foreach ([
        ['services.php', 'grid', 'Services & Prices', 'Every service with prices and durations'],
        ['gallery.php', 'image', 'Gallery', 'Real work from our chairs'],
        ['team.php', 'users', 'Our Stylists', 'Meet the specialists'],
        ['offers.php', 'tag', 'Offers', 'Packages and loyalty rewards'],
        ['manage-booking.php', 'calendar', 'Manage Booking', 'Check, move or cancel an appointment'],
        ['faq.php', 'crown', 'FAQ', 'Booking, payment and policy answers'],
      ] as [$href, $icon, $t, $s]): ?>
        <a href="<?= e($href) ?>" class="card card-hover">
          <span class="icon-badge icon-badge-sm mb-3" data-icon="<?= e($icon) ?>"></span>
          <h4 class="mb-1"><?= e($t) ?></h4>
          <p class="small muted mb-0"><?= e($s) ?></p></a>
      <?php endforeach; ?>
    </div>
    <p class="small muted mt-10">Think something is broken? Tell us on
      <a href="https://wa.me/<?= e(setting('whatsapp', '')) ?>" target="_blank" rel="noopener" class="t-pink">WhatsApp</a>.</p>
  </div>
</section>
<?php public_foot(['page' => '404.php']); ?>
