<?php
$pageTitle = 'Support — ' . APP_NAME;
$showNav   = true;
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">Help</p>
      <h1 class="page-title">Support</h1>
    </div>
  </div>

  <div style="max-width:720px;margin:0 auto;">

    <!-- Hero -->
    <div class="support-hero">
      <div class="support-hero-icon"><?= icon('info',26) ?></div>
      <div>
        <p style="font-family:var(--font-display);font-size:18px;font-weight:700;color:#fff;line-height:1.2;">
          We're here to help
        </p>
        <p style="font-size:13px;color:rgba(255,255,255,.5);margin-top:4px;">
          Reach out through any of the channels below.
        </p>
      </div>
    </div>

    <!-- Contact cards -->
    <div class="support-cards">
      <a href="tel:9898074441" class="support-card">
        <div class="support-card-icon" style="background:var(--gray-100);color:var(--text3);">
          <?= icon('phone',22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Call Us</p>
          <p class="support-card-value">+91 9898074441</p>
          <p class="support-card-hint">Mon – Sat, 9 AM – 6 PM</p>
        </div>
      </a>

      <a href="mailto:info@bafnamarbles.com" class="support-card">
        <div class="support-card-icon" style="background:var(--gold-light);color:var(--gold-dark);">
          <?= icon('mail',22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Email Us</p>
          <p class="support-card-value">sales@bafnamarbles.com</p>
          <p class="support-card-hint">Reply within 24 hours</p>
        </div>
      </a>

      <a href="https://wa.me/919898074441" target="_blank" class="support-card">
        <div class="support-card-icon" style="background:#e8faf0;color:#25D366;">
          <?= icon('whatsapp',22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">WhatsApp</p>
          <p class="support-card-value">+91 9898074441</p>
          <p class="support-card-hint">Quick responses</p>
        </div>
      </a>

      <div class="support-card support-card--nolink">
        <div class="support-card-icon" style="background:var(--success-bg);color:var(--success);">
          <?= icon('verified',22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Visit Us</p>
          <p class="support-card-value">Bafna Marble & Granites <br> block No.40, Near Puniya Bhumi , Second VIP Road <br>Surat-395007 , Gujarat -INDIA <br>
          <a href="https://maps.app.goo.gl/9WiRU9Zg3Sxw8xxA9" target="_blank" class="location-pin-btn"><img src="https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi3_hdpi.png" alt="Location Pin" ><span> Google Location</span></a>

<style>
.location-pin-btn{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:12px 18px;
    background:#25D366;
    color:#fff;
    text-decoration:none;
    border-radius:30px;
    font-family:Arial,sans-serif;
    font-size:15px;
    font-weight:600;
    transition:.3s ease;
    box-shadow:0 4px 12px rgba(0,0,0,.15);
   margin:20px;
}

.location-pin-btn img{
    width:28px;
    height:28px;
    object-fit:contain;
}

.location-pin-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 18px rgba(0,0,0,.2);
}
</style>
          </p>
        </div>
      </div>
    </div>

    <!-- FAQ -->
    <div class="support-faq">
      <p class="support-faq-title">Frequently Asked Questions</p>

      <?php $faqs = [
        ['How do I save a product?',        'Tap the heart icon on any product card in the catalog to add it to your Shortlist for quick access later.'],
        ['How do I contact about a product?','Use the Share button on any product page to send the details via WhatsApp or email to coordinate directly.'],
        ['Can I save multiple products?',   'Yes — there is no limit. Your entire Shortlist is available under the Shortlist tab in the navigation.'],
        ['How long do notifications last?', 'Notifications are automatically removed after 20 days to keep your feed clean and relevant.'],
        ['How do I reset my password?',     'On the login page, tap "Forgot password?" and follow the instructions sent to your registered email address.'],
        ['How do I update my profile?',     'Go to your Profile page — the edit form is always visible so you can update your details at any time.'],
      ]; ?>

      <?php foreach ($faqs as $i => $faq): ?>
      <div class="faq-item">
        <button class="faq-q" onclick="toggleFaq(<?= $i ?>)">
          <span><?= h($faq[0]) ?></span>
          <span class="faq-chevron" id="chev<?= $i ?>"><?= icon('forward',14) ?></span>
        </button>
        <div class="faq-a" id="faqA<?= $i ?>"><?= h($faq[1]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <p style="text-align:center;font-size:12px;color:var(--text4);margin-top:28px;padding-bottom:16px;">
      <?= APP_NAME ?> &nbsp;·&nbsp; v<?= APP_VERSION ?>
    </p>

  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>