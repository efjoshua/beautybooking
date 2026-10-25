<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

public_head([
    'title' => 'Privacy Policy — ' . APP_NAME,
    'page' => 'privacy.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="<?= e(url('public/index.php')) ?>">Home</a> <span>/</span> Privacy</div>
    <h1>Privacy Policy</h1>
    <p>Last updated 20 August 2026</p>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow legal">

    <div class="card mb-8">
      <h4 class="mb-3">In short</h4>
      <p class="small mb-0">We collect your name, phone number and what you booked, because
        we cannot run a salon without them. We do not sell your data, we do not share it with
        advertisers, and you can ask us to delete it at any time by messaging the salon.</p>
    </div>

    <div class="card mb-8 toc">
      <h4 class="mb-3">On this page</h4>
      <a href="#collect">1. What we collect</a>
      <a href="#why">2. Why we collect it</a>
      <a href="#share">3. Who sees it</a>
      <a href="#keep">4. How long we keep it</a>
      <a href="#rights">5. Your rights</a>
      <a href="#cookies">6. Cookies</a>
      <a href="#contact">7. Contact</a>
    </div>

    <h2 id="collect">1. What we collect</h2>
    <p>When you book an appointment with Princess Beauty Salon we collect:</p>
    <ul>
      <li>Your name and phone number, so we can confirm and remind you.</li>
      <li>Your email address, only if you choose to give it.</li>
      <li>The services you booked, the stylist you chose, and the date and time.</li>
      <li>Any notes you add — for example a scalp sensitivity or a style reference.</li>
      <li>What you paid and how you paid it, for our own accounting records.</li>
    </ul>
    <p>We do not collect payment card numbers. Card payments are taken on a terminal in the
      salon and we never see or store the full number.</p>

    <h2 id="why">2. Why we collect it</h2>
    <ul>
      <li><b>To deliver the service you booked.</b> Your stylist needs to know who is coming,
        when, and what for.</li>
      <li><b>To contact you about your appointment.</b> Confirmations, reminders and any
        change we need to tell you about.</li>
      <li><b>To keep your history.</b> So the next stylist knows what worked last time and
        we do not have to ask you the same questions again.</li>
      <li><b>To run the business.</b> Revenue, stock and commission records are built from
        completed appointments.</li>
    </ul>
    <p>We will only send you offers if you tick the box asking us to, and you can tell us to
      stop at any time by replying to any message.</p>

    <h2 id="share">3. Who sees it</h2>
    <p>Inside the salon, access depends on role. Stylists see only their own appointments and
      the clients they have personally served. Full client records, revenue and stock data are
      restricted to the owner.</p>
    <p>Outside the salon, we share nothing except where we are legally required to. We do not
      sell client lists and we do not pass your number to advertisers.</p>

    <h2 id="keep">4. How long we keep it</h2>
    <ul>
      <li>Client records: for as long as you remain a client, plus two years.</li>
      <li>Financial records: seven years, as required for tax purposes.</li>
      <li>Marketing consent: until you withdraw it.</li>
    </ul>

    <h2 id="rights">5. Your rights</h2>
    <p>You can ask us at any time to:</p>
    <ul>
      <li>Tell you what we hold about you.</li>
      <li>Correct anything that is wrong.</li>
      <li>Delete your record entirely, except where we must keep financial entries for tax.</li>
      <li>Stop sending you marketing messages.</li>
    </ul>
    <p>Message the salon on WhatsApp or email us and we will action it within seven days.</p>

    <h2 id="cookies">6. Cookies</h2>
    <p>This website uses browser storage only to remember your booking while you are filling
      in the form, and to keep staff signed in to the admin panel. There are no advertising
      trackers and no third-party analytics cookies.</p>

    <h2 id="contact">7. Contact</h2>
    <p>Questions about this policy go to Aïcha Ngo, owner:</p>
    <p>
      <a href="mailto:hello@princessbeautysalon.cm" class="t-pink">hello@princessbeautysalon.cm</a><br>
      <a href="tel:+237678451290" class="t-pink">+237 6 78 45 12 90</a><br>
      Carrefour Obili, Yaoundé, Centre Region, Cameroon
    </p>

    <div class="alert alert-info mt-8">
      <span class="ico" data-icon="alert"></span>
      <span><b>Prototype notice</b>
        <p>This is placeholder policy text for the design prototype. Have it reviewed against
           Cameroonian data protection law before the site goes live.</p></span>
    </div>

  </div>
</section>


<?php public_foot(['page' => 'privacy.php']); ?>
