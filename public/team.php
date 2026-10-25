<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$cats  = repo_categories();
$staff = repo_staff(true);

/* slug => category, so the tag loop is a lookup rather than a nested scan */
$catBySlug = [];
foreach ($cats as $c) { $catBySlug[$c['slug']] = $c; }

public_head([
    'title' => 'Our Stylists — ' . APP_NAME,
    'description' => 'Meet the specialists at Princess Beauty Salon: braiders, nail artists, lash techs, estheticians and massage therapists.',
    'page' => 'team.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Stylists') ?></div>
    <h1><?= te('Meet Your Stylists') ?></h1>
    <p><?= te(':n specialists, each the best in the room at one thing. Book the person, not just the service.', ['n' => count($staff)]) ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="chips chips-scroll mb-8" id="teamFilters">
      <button class="chip active" data-filter="all">Everyone</button>
      <?php foreach ($cats as $c): ?>
        <button class="chip" data-filter="<?= e($c['slug']) ?>"><?= e(tr($c, 'name', 'service_categories')) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="grid g-3" id="teamGrid">
      <?php foreach ($staff as $s): ?>
        <article class="team-card reveal" data-cat="<?= e($s['categories'][0] ?? '') ?>">
          <?= photo(['path' => staff_photo($s['photo']), 'class' => 'photo',
                     'tone' => $s['tone'] === 'gold' ? 'ph-gold' : 'ph-pink',
                     'label' => initials($s['name']), 'alt' => $s['name']]) ?>
          <div class="body">
            <h4><?= e($s['name']) ?></h4>
            <div class="role"><?= e($s['job_title']) ?></div>
            <div class="stars mt-2"><?= str_repeat('★', (int) round((float) $s['rating'])) ?>
              <span class="tiny muted"><?= number_format((float) $s['rating'], 1) ?> ·
                <?= (int) $s['reviews_count'] ?> reviews</span></div>
            <p class="small muted mt-3"><?= e(tr($s, 'bio', 'staff')) ?></p>
            <div class="tags">
              <?php foreach ($s['categories'] as $slug): ?>
                <?php if (isset($catBySlug[$slug])): ?>
                  <span class="badge badge-gold badge-plain"><?= e($catBySlug[$slug]['name']) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <div class="tiny muted mt-4"><?= e($s['working_hours'] ?: '') ?>
              <?= $s['since_year'] ? '· with us since ' . e($s['since_year']) : '' ?></div>
            <a href="book.php?staff=<?= e($s['ref']) ?>" class="btn btn-primary btn-sm mt-4 full">
              Book with <?= e(explode(' ', $s['name'])[0]) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-cream">
  <div class="wrap wrap-narrow text-center">
    <span class="eyebrow">Join The Team</span>
    <h2>We're always looking for talent</h2>
    <div class="gold-rule"></div>
    <p class="lead">If you're a braider, nail artist or lash tech who takes the craft
       seriously, we offer chair rental and commission splits up to 42%.</p>
    <a href="contact.php" class="btn btn-dark mt-4">Get in Touch</a>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold">Found Your Person?</span>
    <h2 class="t-white">Book them directly</h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Book an Appointment') ?></a></div>
</section>

<?php public_foot(['page' => 'team.php', 'inline' => '<script>
document.addEventListener("DOMContentLoaded", function () {
  PBS.initFilters("#teamFilters .chip", "#teamGrid .team-card");
});
</script>']); ?>
