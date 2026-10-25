<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$faqs = repo_faqs();
$groups = [];
foreach ($faqs as $f) $groups[$f['category']][] = $f;

$cat = (string) ($_GET['cat'] ?? 'all');

public_head([
    'title' => 'FAQ — ' . APP_NAME,
    'description' => 'Answers on booking, payment, deposits, braid prices, running late and bringing your own hair.',
    'page' => 'faq.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('FAQ') ?></div>
    <h1><?= te('Questions, Answered') ?></h1>
    <p><?= te('The things clients ask us most. If yours is not here, WhatsApp us — we reply within about two hours during opening times.') ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow">
    <div class="chips chips-scroll mb-8">
      <a class="chip<?= $cat === 'all' ? ' active' : '' ?>" href="?cat=all">Everything</a>
      <?php foreach (array_keys($groups) as $g): ?>
        <a class="chip<?= $cat === $g ? ' active' : '' ?>" href="?cat=<?= e($g) ?>"><?= e($g) ?></a>
      <?php endforeach; ?>
    </div>

    <div id="faqList">
      <?php foreach ($groups as $g => $items):
        if ($cat !== 'all' && $cat !== $g) continue; ?>
        <section class="mb-8">
          <h3 class="mb-4"><?= e($g) ?></h3>
          <?php foreach ($items as $f): ?>
            <div class="faq-item">
              <button class="faq-q" type="button"><?= e(tr($f, 'question', 'faqs')) ?><span class="mark">+</span></button>
              <div class="faq-a"><?= e(tr($f, 'answer', 'faqs')) ?></div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="card mt-10 text-center">
      <span class="icon-badge mb-4" data-icon="whatsapp"></span>
      <h3>Still stuck?</h3>
      <p class="muted">Message us and a real person will answer — usually within two hours.</p>
      <div class="flex gap-3 wrapflex mt-4" style="justify-content:center">
        <a href="https://wa.me/<?= e(setting('whatsapp', '')) ?>" target="_blank" rel="noopener"
           class="btn btn-primary">WhatsApp us</a>
        <a href="contact.php" class="btn btn-outline"><?= te('Send a message') ?></a>
      </div>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold">All Clear?</span>
    <h2 class="t-white">Then let's get you booked</h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Book an Appointment') ?></a></div>
</section>

<?php public_foot(['page' => 'faq.php',
  'inline' => '<script>document.addEventListener("DOMContentLoaded",function(){PBS.initFaq("#faqList");});</script>']); ?>
