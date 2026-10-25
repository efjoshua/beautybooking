<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$staff = repo_staff(true);
$svcs  = repo_services();
$clients = (int) db_val('SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL');
$reviews = (int) db_val('SELECT COALESCE(SUM(reviews_count), 0) FROM staff WHERE deleted_at IS NULL');
$years   = (int) date('Y') - 2019;

/* The salon interior shot beside the story. `backgrounds/` is scanned like any
   other folder but never shows as a gallery tile. */
$storyPhoto = repo_folder_images('backgrounds', 1)[0] ?? null;

public_head([
    'title' => 'About Us — ' . APP_NAME,
    'description' => 'The story behind Princess Beauty Salon, Yaoundé — and what Look Good, Feel Good, Be You actually means to us.',
    'page' => 'about.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('About') ?></div>
    <h1><?= te('Our Story') ?></h1>
    <p><?= te("Princess Beauty Salon started with one chair, one dryer and a rule we've never broken: the client's time is not ours to waste.") ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow text-center">
    <span class="script t-pink" style="font-size:clamp(2rem,5vw,3rem)"><?= e(setting('tagline', '')) ?></span>
    <div class="gold-rule"></div>
    <p class="lead">Three sentences, in that order, deliberately. The look is what you
       walk in for. The feeling is what makes you come back. And being yourself —
       not a copy of someone on a screen — is the whole point of sitting in our chair.</p>
  </div>
</section>

<section class="section bg-cream">
  <div class="wrap">
    <div class="grid g-2 gap-lg" style="align-items:center">
      <?= photo(['path' => $storyPhoto, 'tone' => 'ph-gold', 'class' => 'reveal',
                 'label' => 'Est. 2019', 'alt' => 'Inside Princess Beauty Salon',
                 'ratio' => '4/5', 'radius' => '20px']) ?>
      <div class="reveal">
        <span class="eyebrow">2019 — Today</span>
        <h2>From one chair in Obili to <?= count($staff) ?> specialists</h2>
        <div class="gold-rule" style="margin-left:0"></div>
        <p>Aïcha Ngo opened Princess Beauty Salon after twelve years of working in
           other people's salons and watching the same thing happen every Saturday:
           women booked for 9am, seen at 1pm, home at 7pm.</p>
        <p>So the first thing she bought wasn't a second dryer. It was a booking
           system. Every appointment blocks the stylist's full service length, and
           nobody gets double-booked to squeeze in a walk-in.</p>
        <p class="mb-0"><?= $years ?> years later that's still the difference.
           <?= count($staff) ?> specialists, <?= count($svcs) ?> services, and a diary
           you can actually trust.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow">What We Stand For</span>
      <h2>Four things we don't compromise on</h2><div class="gold-rule"></div></div>
    <div class="grid g-4">
      <?php foreach ([
        ['clock', 'Your time', 'A booked slot is a promise. If we are running late, you hear it from us before you leave the house.'],
        ['crown', 'The craft', 'Specialists only. Everyone here trains, certifies and keeps learning — including the owner.'],
        ['lashes', 'Your hair &amp; skin', 'Protective styling, no tension braids, sulfate-free products and honest advice when a look will damage you.'],
        ['cash', 'Honest pricing', 'The price on the site is the price at the till. Add-ons are quoted before we touch your hair.'],
      ] as [$icon, $h, $p]): ?>
        <div class="card card-hover reveal text-center">
          <span class="icon-badge" data-icon="<?= e($icon) ?>"></span>
          <h3 class="mt-5"><?= $h ?></h3>
          <p class="small muted mb-0"><?= $p ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-black">
  <div class="wrap">
    <div class="grid g-4 text-center">
      <?php foreach ([
        [$clients, '+', 'Clients Served'], [$reviews, '', 'Five-Star Reviews'],
        [$years, '', 'Years Open'], [count($svcs), '', 'Services Offered'],
      ] as [$n, $suffix, $label]): ?>
        <div><b class="script t-gold" style="font-size:3rem" data-count="<?= $n ?>"
             data-suffix="<?= $suffix ?>">0</b>
          <div class="tiny" style="letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.5)">
            <?= e($label) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head reveal"><span class="eyebrow">The People</span>
      <h2>Who you'll meet</h2><div class="gold-rule"></div></div>
    <div class="grid g-4">
      <?php foreach (array_slice($staff, 0, 4) as $s): ?>
        <article class="team-card reveal">
          <?= photo(['path' => staff_photo($s['photo']), 'class' => 'photo',
                     'tone' => $s['tone'] === 'gold' ? 'ph-gold' : 'ph-pink',
                     'label' => initials($s['name']), 'alt' => $s['name']]) ?>
          <div class="body"><h4><?= e($s['name']) ?></h4>
            <div class="role"><?= e($s['job_title']) ?></div>
            <p class="small muted mt-3 mb-0"><?= e(tr($s, 'bio', 'staff')) ?></p>
            <a href="book.php?staff=<?= e($s['ref']) ?>" class="btn btn-outline btn-sm mt-4 full">
              Book with <?= e(explode(' ', $s['name'])[0]) ?></a></div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-8"><a href="team.php" class="link-arrow">See all <?= count($staff) ?> stylists →</a></div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold">Come See For Yourself</span>
    <h2 class="t-white">Your first visit is on the diary in two minutes</h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Book an Appointment') ?></a></div>
</section>

<?php public_foot(['page' => 'about.php']); ?>
