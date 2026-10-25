<?php
/**
 * Princess Beauty Salon — public site chrome (server-rendered)
 *
 * Replaces the JS injection in layout.js. Same markup, so every CSS rule and
 * the whole responsive layer carry over untouched.
 */

declare(strict_types=1);

require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

function public_nav(): array
{
    return [
        ['index.php',    t('Home')],
        ['services.php', t('Services')],
        ['offers.php',   t('Offers')],
        ['gallery.php',  t('Gallery')],
        ['team.php',     t('Stylists')],
        ['about.php',    t('About')],
        ['contact.php',  t('Contact')],
    ];
}
function public_nav_more(): array
{
    return [['manage-booking.php', t('Manage Booking')], ['faq.php', t('FAQ')]];
}
function public_tabs(): array
{
    return [
        ['index.php',    t('Home'),     'home',  false],
        ['services.php', t('Services'), 'grid',  false],
        ['book.php',     t('Book'),     'plus',  true],
        ['gallery.php',  t('Gallery'),  'image', false],
        ['contact.php',  t('Contact'),  'phone', false],
    ];
}

function wordmark(string $cls = ''): string
{
    return '<span class="wordmark ' . e($cls) . '">'
        . '<span class="crown" data-icon="crown"></span>'
        . '<span class="princess">Princess</span>'
        . '<span class="beauty">Beauty</span>'
        . '<span class="salon">Salon</span></span>';
}

/**
 * $opts: title, description, page, body_class, noindex
 */
function public_head(array $opts): void
{
    $page  = $opts['page'] ?? basename($_SERVER['SCRIPT_NAME']);
    $title = $opts['title'] ?? APP_NAME;
    $desc  = $opts['description'] ?? setting('tagline', '');
    $phone = setting('phone', '');
    ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($opts['noindex'])): ?><meta name="robots" content="noindex"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/public.css') ?>">
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script>window.PBS_CSRF = <?= json_encode(csrf_token()) ?>;
        window.PBS_BASE = <?= json_encode(BASE_URL) ?>;
        window.PBS_CURRENCY = <?= json_encode(CURRENCY) ?>;</script>
</head>
<body class="public" data-page="<?= e($page) ?>">

<header class="site-header" id="siteHeader"><div class="wrap header-inner">
  <a href="index.php" aria-label="<?= e(APP_NAME) ?> home"><?= wordmark() ?></a>
  <nav class="nav" aria-label="Main">
    <?php foreach (public_nav() as [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $page === $href ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="header-actions">
    <a class="header-phone" href="tel:<?= e(preg_replace('/\s/', '', $phone)) ?>">
      <span data-icon="phone"></span><?= e($phone) ?></a>
    <?= locale_switch() ?>
    <a href="book.php" class="btn btn-primary btn-sm"><?= te('Book Now') ?></a>
    <button class="hamburger" id="hamburger" aria-label="Open menu" aria-expanded="false">
      <i></i><i></i><i></i></button>
  </div>
</div></header>

<div class="drawer" id="drawer">
  <div class="drawer-backdrop" data-close-drawer></div>
  <div class="drawer-panel">
    <div class="flex items-center justify-between">
      <?= wordmark() ?>
      <button class="modal-close" data-close-drawer aria-label="Close menu">&times;</button>
    </div>
    <nav class="drawer-nav" aria-label="Mobile">
      <?php foreach (public_nav() as [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $page === $href ? ' class="active"' : '' ?>>
          <?= e($label) ?><span data-icon="chevR"></span></a>
      <?php endforeach; ?>
      <a href="book.php"<?= $page === 'book.php' ? ' class="active"' : '' ?>>
        <?= te('Book Appointment') ?><span data-icon="chevR"></span></a>
      <?php foreach (public_nav_more() as [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $page === $href ? ' class="active"' : '' ?>>
          <?= e($label) ?><span data-icon="chevR"></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="drawer-foot">
      <a href="book.php" class="btn btn-primary btn-block mb-4"><?= te('Book Now') ?></a>
      <a href="<?= url('admin/login.php') ?>" class="btn btn-ghost btn-block btn-sm"><?= te('Staff Login') ?></a>
      <?= locale_switch('lang-switch-block mt-4') ?>
      <p class="small muted mt-4 mb-0"><?= e($phone) ?><br><?= e(setting('address', '')) ?></p>
    </div>
  </div>
</div>
<?php
}

function public_foot(array $opts = []): void
{
    $page = $opts['page'] ?? basename($_SERVER['SCRIPT_NAME']);
    $wa   = setting('whatsapp', '');
    $cats = repo_categories();
    ?>
<footer class="site-footer"><div class="wrap">
  <div class="footer-grid">
    <div>
      <a href="index.php" class="footer-brand" aria-label="<?= e(APP_NAME) ?> home">
        <?= wordmark('wordmark-light') ?></a>
      <p class="small mt-4" style="max-width:32ch"><?= e(setting('tagline', '')) ?> —
        <?= te("Yaoundé's home of elegant hair, nails, lashes and spa care since 2019.") ?></p>
      <div class="socials mt-4">
        <a href="https://instagram.com/" target="_blank" rel="noopener" aria-label="Instagram"><span data-icon="instagram"></span></a>
        <a href="https://tiktok.com/" target="_blank" rel="noopener" aria-label="TikTok"><span data-icon="tiktok"></span></a>
        <a href="https://facebook.com/" target="_blank" rel="noopener" aria-label="Facebook"><span data-icon="facebook"></span></a>
        <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><span data-icon="whatsapp"></span></a>
      </div>
    </div>
    <div><h5><?= te('Explore') ?></h5><ul>
      <?php foreach (public_nav() as [$h, $l]): ?><li><a href="<?= e($h) ?>"><?= e($l) ?></a></li><?php endforeach; ?>
      <li><a href="book.php"><?= te('Book Appointment') ?></a></li>
      <?php foreach (public_nav_more() as [$h, $l]): ?><li><a href="<?= e($h) ?>"><?= e($l) ?></a></li><?php endforeach; ?>
    </ul></div>
    <div><h5><?= te('Services') ?></h5><ul>
      <?php foreach ($cats as $c): ?>
        <li><a href="services.php#<?= e($c['slug']) ?>"><?= e(tr($c, 'name', 'service_categories')) ?></a></li>
      <?php endforeach; ?>
    </ul></div>
    <div><h5><?= te('Visit Us') ?></h5>
      <p class="small"><?= e(setting('address', '')) ?></p>
      <p class="small"><a href="tel:<?= e(preg_replace('/\s/', '', setting('phone', ''))) ?>"><?= e(setting('phone', '')) ?></a><br>
        <a href="mailto:<?= e(setting('email', '')) ?>"><?= e(setting('email', '')) ?></a></p>
      <?php $hrs = repo_business_hours(); ?>
      <p class="small mb-0">
        <?php foreach ($hrs as $h): ?>
          <?= e(is_fr() ? fr_days()[(int) $h['weekday'] + 1] : substr($h['day_name'], 0, 3)) ?>
          <?= $h['is_closed'] ? te('Closed') : e(fmt_time($h['opens_at']) . '–' . fmt_time($h['closes_at'])) ?><br>
        <?php endforeach; ?>
      </p>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. <?= te('All rights reserved.') ?></span>
    <span><a href="privacy.php"><?= te('Privacy') ?></a> &nbsp;·&nbsp;
          <a href="terms.php"><?= te('Terms & Booking Policy') ?></a> &nbsp;·&nbsp;
          <a href="<?= url('admin/login.php') ?>"><?= te('Staff Login') ?></a></span>
  </div>
</div></footer>

<a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <span data-icon="whatsapp"></span></a>

<nav class="bottom-nav" aria-label="Mobile tabs"><ul>
  <?php foreach (public_tabs() as [$href, $label, $icon, $cta]): ?>
    <li><a href="<?= e($href) ?>" class="<?= $cta ? 'cta' : ($page === $href ? 'active' : '') ?>">
      <?php if ($cta): ?><span class="fab" data-icon="<?= e($icon) ?>"></span><span><?= e($label) ?></span>
      <?php else: ?><span data-icon="<?= e($icon) ?>"></span><span><?= e($label) ?></span><?php endif; ?>
    </a></li>
  <?php endforeach; ?>
</ul></nav>

<script src="<?= url('assets/js/ui.js') ?>"></script>
<script src="<?= url('assets/js/public-ui.js') ?>"></script>
<?php foreach ($opts['scripts'] ?? [] as $s): ?><script src="<?= e($s) ?>"></script><?php endforeach; ?>
<?= $opts['inline'] ?? '' ?>
</body>
</html>
<?php
}
