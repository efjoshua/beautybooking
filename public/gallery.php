<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

$folders = repo_gallery_folders();
$items = repo_gallery();

public_head([
    'title' => 'Gallery &amp; Portfolio — ' . APP_NAME,
    'description' => 'Braids, silk presses, nail art, lash sets and spa work from the Princess Beauty Salon chair.',
    'page' => 'gallery.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Gallery') ?></div>
    <h1><?= te('Our Portfolio') ?></h1>
    <p><?= te("Every photo here is a real client who sat in our chair. Filter by the service you're curious about.") ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="chips chips-scroll mb-8" id="galFilters">
      <button class="chip active" data-filter="all"><?= te('All Work') ?></button>
      <?php foreach ($folders as $f): ?>
        <button class="chip" data-filter="<?= e($f['slug']) ?>"><?= e($f['label']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="gallery-grid" id="galleryGrid">
      <?php foreach ($items as $g):
              /* The lightbox needs a browser URL, and only if the file is really there. */
              $src = is_file(APP_ROOT . "/" . $g["image_path"]) ? url($g["image_path"]) : ""; ?>
        <div class="gal-item reveal" data-cat="<?= e($g['folder_slug']) ?>"
             data-title="<?= e($g['title']) ?>" data-by="<?= e($g['staff_name'] ?: 'the team') ?>"
             data-src="<?= e($src) ?>" data-tone="<?= e($g['tone']) ?>"
             data-book="<?= e($g['category_slug'] ?? '') ?>" data-label="<?= e($g['folder_label']) ?>">
          <?= photo([
                'path'  => $g['image_path'],
                'tone'  => $g['tone'],
                'label' => $g['short_name'],
                'alt'   => $g['title'],
              ]) ?>
          <div class="ov"><b><?= e($g['title']) ?></b>
            <span>by <?= e($g['staff_name'] ?: 'the team') ?></span></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-12">
      <p class="muted small"><?= te('Follow the daily feed for work that never makes it here.') ?></p>
      <div class="flex gap-3 wrapflex" style="justify-content:center">
        <a href="https://instagram.com/" target="_blank" rel="noopener" class="btn btn-outline">
          Instagram <?= e(setting('instagram', '')) ?></a>
        <a href="https://tiktok.com/" target="_blank" rel="noopener" class="btn btn-outline">
          TikTok <?= e(setting('tiktok', '')) ?></a>
      </div>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><span class="eyebrow t-gold"><?= te('Seen Something You Like?') ?></span>
    <h2 class="t-white"><?= te("Bring the screenshot — we'll recreate it") ?></h2>
    <a href="book.php" class="btn btn-primary btn-lg mt-4"><?= te('Book an Appointment') ?></a></div>
</section>

<div class="modal modal-lg" id="lightbox">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3 id="lbTitle">Work</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body" id="lbBody"></div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" data-modal-close><?= te('Close') ?></button>
      <a class="btn btn-primary btn-sm" id="lbBook" href="book.php"><?= te('Book this look') ?></a>
    </div>
  </div>
</div>

<?php public_foot(['page' => 'gallery.php', 'inline' => '<script>
document.addEventListener("DOMContentLoaded", function () {
  PBS.initFilters("#galFilters .chip", "#galleryGrid .gal-item");
  document.querySelectorAll("#galleryGrid .gal-item").forEach(function (el) {
    el.addEventListener("click", function () {
      var d = el.dataset;
      document.getElementById("lbTitle").textContent = d.title;
      /* Same rule as the grid: the photo when there is one, the tile when there is not. */
      var art = d.src
        ? "<img src=\"" + PBS.esc(d.src) + "\" alt=\"" + PBS.esc(d.title) +
          "\" style=\"width:100%;border-radius:12px;display:block\">"
        : "<div class=\"ph " + d.tone + "\" style=\"aspect-ratio:4/3;border-radius:12px\"><i>" +
          PBS.esc(d.label) + "</i></div>";
      document.getElementById("lbBody").innerHTML = art +
        "<div class=\"flex items-center gap-3 mt-5\">" +
          "<span class=\"avatar\">" + PBS.initials(d.by) + "</span>" +
          "<div><b>" + PBS.esc(d.by) + "</b></div>" +
          "<span class=\"badge badge-gold\" style=\"margin-left:auto\">" + PBS.esc(d.label) + "</span></div>";
      /* Folders without a bookable category (makeup) just open the booking page. */
      var book = document.getElementById("lbBook");
      book.href = d.book ? "book.php?cat=" + encodeURIComponent(d.book) : "book.php";
      PBS.openModal ? PBS.openModal("lightbox") :
        document.getElementById("lightbox").classList.add("open");
      document.body.style.overflow = "hidden";
    });
  });
});
</script>']); ?>
