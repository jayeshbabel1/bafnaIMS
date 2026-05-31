<?php
/**
 * pages/support.php — Task 2: Help & Support Section
 */
$pageTitle = 'Help & Support — ' . APP_NAME;
$showNav   = true;
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="topbar">
    <div class="topbar-brand">
      <div>
        <p class="topbar-eyebrow">Help</p>
        <p class="topbar-title">Support</p>
      </div>
    </div>
  </div>

  <div class="support-wrap">

    <!-- Hero banner -->
    <div class="support-hero">
      <div class="support-hero-icon"><?= icon('info', 28) ?></div>
      <div>
        <p style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;color:#fff;line-height:1.2;">We're here to help</p>
        <p style="font-size:13px;color:rgba(255,255,255,.55);margin-top:4px;">Reach out through any of the channels below.</p>
      </div>
    </div>

    <!-- Contact cards -->
    <div class="support-cards">

      <!-- Phone -->
      <a href="tel:9993939399" class="support-card">
        <div class="support-card-icon" style="background:var(--accent-light);color:var(--accent);">
          <?= icon('phone', 22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Call Us</p>
          <p class="support-card-value">+91 99939 39399</p>
          <p class="support-card-hint">Mon – Sat, 9 AM – 6 PM</p>
        </div>
        <div class="support-card-arrow"><?= icon('forward', 16) ?></div>
      </a>

      <!-- Email -->
      <a href="mailto:asd@sdfa.com" class="support-card">
        <div class="support-card-icon" style="background:var(--gold-bg);color:var(--gold);">
          <?= icon('mail', 22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Email Us</p>
          <p class="support-card-value">asd@sdfa.com</p>
          <p class="support-card-hint">Reply within 24 hours</p>
        </div>
        <div class="support-card-arrow"><?= icon('forward', 16) ?></div>
      </a>

      <!-- WhatsApp -->
      <a href="https://wa.me/919993939399" target="_blank" class="support-card">
        <div class="support-card-icon" style="background:#e8faf0;color:#25D366;">
          <?= icon('whatsapp', 22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">WhatsApp</p>
          <p class="support-card-value">+91 99939 39399</p>
          <p class="support-card-hint">Quick responses on WhatsApp</p>
        </div>
        <div class="support-card-arrow"><?= icon('forward', 16) ?></div>
      </a>

      <!-- Address -->
      <div class="support-card support-card--nolink">
        <div class="support-card-icon" style="background:var(--success-bg);color:var(--success);">
          <?= icon('verified', 22) ?>
        </div>
        <div class="support-card-body">
          <p class="support-card-label">Visit Us</p>
          <p class="support-card-value">33, Mdfdfasdf, Sdfasdf</p>
          <p class="support-card-hint">Surat, Gujarat, India</p>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="support-faq-section">
      <p class="support-section-title">Frequently Asked Questions</p>

      <?php $faqs = [
        ['How do I place an inquiry?',      'Browse the catalog, open any product, and tap "Send Inquiry". Fill in your message and quantity required.'],
        ['How long does a reply take?',      'Our team typically replies within 1–2 business days.'],
        ['Can I shortlist multiple products?','Yes — tap the heart icon on any product to save it to your shortlist for quick access.'],
        ['Are inquiries archived?',          'Inquiries older than 25 days are automatically moved to the Archive tab in your Inquiries page.'],
        ['How do I reset my password?',      'On the login page, tap "Forgot password?" and follow the instructions sent to your email.'],
      ]; ?>

      <?php foreach ($faqs as $i => $faq): ?>
      <div class="faq-item" id="faq<?= $i ?>">
        <button class="faq-q" onclick="toggleFaq(<?= $i ?>)">
          <span><?= h($faq[0]) ?></span>
          <span class="faq-chevron" id="chev<?= $i ?>"><?= icon('forward', 14) ?></span>
        </button>
        <div class="faq-a" id="faqA<?= $i ?>"><?= h($faq[1]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- .support-wrap -->
</div>

<style>
.support-wrap { padding: 16px 16px 80px; max-width: 720px; margin: 0 auto; }

.support-hero {
  display: flex; align-items: center; gap: 16px;
  background: linear-gradient(135deg, var(--nav-bg), var(--accent2));
  border-radius: var(--card-radius); padding: 22px 20px; margin-bottom: 20px;
}
.support-hero-icon {
  width: 56px; height: 56px; border-radius: 14px;
  background: rgba(255,255,255,.12); display: flex; align-items: center;
  justify-content: center; color: #fff; flex-shrink: 0;
}

.support-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }

.support-card {
  display: flex; align-items: center; gap: 12px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--card-radius); padding: 16px 14px;
  text-decoration: none; color: var(--text);
  transition: transform .18s, box-shadow .18s;
}
.support-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.07); }
.support-card--nolink { cursor: default; }
.support-card--nolink:hover { transform: none; box-shadow: none; }

.support-card-icon {
  width: 46px; height: 46px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.support-card-body { flex: 1; min-width: 0; }
.support-card-label { font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; }
.support-card-value { font-size: 13px; font-weight: 600; color: var(--text); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.support-card-hint  { font-size: 11px; color: var(--text3); margin-top: 2px; }
.support-card-arrow { color: var(--text3); flex-shrink: 0; }

.support-faq-section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--card-radius); overflow: hidden; }
.support-section-title { font-size: 12px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .6px; padding: 16px 18px 10px; border-bottom: 1px solid var(--border); }

.faq-item { border-bottom: 1px solid var(--border); }
.faq-item:last-child { border-bottom: none; }
.faq-q {
  width: 100%; display: flex; justify-content: space-between; align-items: center;
  padding: 14px 18px; font-size: 13px; font-weight: 600; color: var(--text);
  text-align: left; gap: 10px; background: none; cursor: pointer;
}
.faq-q:hover { background: var(--surface2); }
.faq-chevron { flex-shrink: 0; color: var(--text3); transition: transform .2s; }
.faq-chevron.open { transform: rotate(90deg); }
.faq-a {
  font-size: 13px; color: var(--text2); line-height: 1.6;
  max-height: 0; overflow: hidden; transition: max-height .3s ease, padding .3s ease;
  padding: 0 18px;
}
.faq-a.open { max-height: 200px; padding: 0 18px 14px; }

@media (max-width: 480px) {
  .support-cards { grid-template-columns: 1fr; }
}
@media (min-width: 768px) {
  .support-wrap { padding: 20px 24px 60px; }
}
</style>

<script>
function toggleFaq(i) {
  const a    = document.getElementById('faqA' + i);
  const chev = document.getElementById('chev' + i);
  const open = a.classList.toggle('open');
  chev.classList.toggle('open', open);
}
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>