<?php
/**
 * includes/mailer.php
 * Central mail helper — all application emails must use sendMail().
 * Loads SMTP settings from DB. Falls back to php mail() if SMTP disabled.
 * ─────────────────────────────────────────────────────────────────────────
 */

/**
 * Send an email.
 *
 * @param string $to      Recipient email
 * @param string $subject Subject line
 * @param string $html    HTML body
 * @param string $text    Plain-text fallback (auto-generated if empty)
 * @param string $toName  Recipient display name (optional)
 * @return array ['success'=>bool, 'error'=>string]
 */
function sendMail(string $to, string $subject, string $html, string $text = '', string $toName = ''): array {
    $settings = getSmtpSettings();

    if ($text === '') {
        $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
    }

    if ($settings['smtp_enabled']) {
        return sendMailSmtp($to, $toName, $subject, $html, $text, $settings);
    }

    return sendMailNative($to, $toName, $subject, $html, $text, $settings);
}

/**
 * Load SMTP settings from DB (cached per request).
 */
function getSmtpSettings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $defaults = [
        'smtp_host'       => '',
        'smtp_port'       => 587,
        'smtp_username'   => '',
        'smtp_password'   => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => MAIL_FROM,
        'smtp_from_name'  => MAIL_FROM_NAME,
        'smtp_enabled'    => false,
    ];

    try {
        $rows = getDB()->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'smtp_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $k => $v) {
            $defaults[$k] = $v;
        }
        $defaults['smtp_enabled'] = !empty($defaults['smtp_enabled']) && $defaults['smtp_enabled'] !== '0';
        $defaults['smtp_port']    = (int)($defaults['smtp_port'] ?: 587);
    } catch (Throwable $e) {
        error_log('getSmtpSettings: ' . $e->getMessage());
    }

    $cache = $defaults;
    return $cache;
}

/**
 * Send via SMTP using sockets (no PHPMailer dependency).
 * Supports TLS/STARTTLS via stream_socket_client.
 */
function sendMailSmtp(string $to, string $toName, string $subject, string $html, string $text, array $s): array {
    // Try to use PHPMailer if available (composer dependency already installed)
    $autoload = dirname(__DIR__) . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        return sendMailPhpMailer($to, $toName, $subject, $html, $text, $s);
    }

    // Fallback to native if no library
    return sendMailNative($to, $toName, $subject, $html, $text, $s);
}

/**
 * Send via PHPMailer (available via composer).
 */
function sendMailPhpMailer(string $to, string $toName, string $subject, string $html, string $text, array $s): array {
    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once dirname(__DIR__) . '/../vendor/autoload.php';
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $s['smtp_host'];
        $mail->Port       = (int)$s['smtp_port'];
        $mail->SMTPAuth   = !empty($s['smtp_username']);
        $mail->Username   = $s['smtp_username'];
        $mail->Password   = $s['smtp_password'];
        $mail->SMTPSecure = strtolower($s['smtp_encryption']) === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($s['smtp_from_email'], $s['smtp_from_name']);
        $mail->addAddress($to, $toName ?: $to);
        $mail->Subject  = $subject;
        $mail->isHTML(true);
        $mail->Body     = $html;
        $mail->AltBody  = $text;
        $mail->send();
        return ['success' => true, 'error' => ''];
    } catch (Throwable $e) {
        error_log('sendMailPhpMailer: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fallback: PHP mail() with HTML headers.
 */
function sendMailNative(string $to, string $toName, string $subject, string $html, string $text, array $s): array {
    $from    = $s['smtp_from_email'];
    $fromName = $s['smtp_from_name'];
    $boundary = md5(uniqid(rand(), true));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: BafnaMailer/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $text . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--{$boundary}--";

    $ok = @mail($to, $subject, $body, $headers);
    return ['success' => (bool)$ok, 'error' => $ok ? '' : 'mail() returned false'];
}

/**
 * Reusable branded HTML email template wrapper.
 */
function emailTemplate(string $title, string $bodyHtml, string $preheader = ''): string {
    $appName = APP_NAME;
    $year    = date('Y');
    $pre     = $preheader ? '<span style="display:none;font-size:1px;color:#fff;max-height:0;overflow:hidden;">'
                           . htmlspecialchars($preheader) . '</span>' : '';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:'Segoe UI',Arial,sans-serif;">
{$pre}
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 32px rgba(0,0,0,.08);">
      <!-- Header -->
      <tr>
        <td style="background:#0a0a0a;padding:28px 36px;text-align:center;">
          <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               alt="{$appName}" width="100" style="filter:brightness(0) invert(1);display:block;margin:0 auto;"/>
          <p style="color:rgba(255,255,255,.5);font-size:11px;margin:8px 0 0;letter-spacing:2px;text-transform:uppercase;">{$appName}</p>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:36px 36px 28px;">
          {$bodyHtml}
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f9f7f4;padding:20px 36px;text-align:center;border-top:1px solid #e8e0d4;">
          <p style="color:#aaa;font-size:12px;margin:0;">© {$year} {$appName}. All rights reserved.</p>
          <p style="color:#ccc;font-size:11px;margin:6px 0 0;">Block No.40, Near Puniya Bhumi, Second VIP Road, Surat-395007, Gujarat</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Send approval email to user after admin verification.
 */
function sendApprovalEmail(string $to, string $name): array {
    $loginUrl = BASE_URL . '/index.php?page=login';
    $subject  = 'Your Bafna Marble Catalog Access is Approved!';
    $body     = <<<HTML
<h2 style="font-size:22px;font-weight:700;color:#0a0a0a;margin:0 0 8px;">Welcome, {$name}! 🎉</h2>
<p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 20px;">
  Your request to access the <strong>Bafna Marble Catalog Platform</strong> has been <strong style="color:#1a6b3a;">approved</strong> by our team.
</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 28px;">
  You can now log in to browse our exclusive stone and marble inventory, save shortlists, and send inquiries directly to our team.
</p>
<div style="text-align:center;margin:0 0 28px;">
  <a href="{$loginUrl}" style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;letter-spacing:.3px;">
    Access the Catalog →
  </a>
</div>
<p style="color:#888;font-size:13px;line-height:1.6;border-top:1px solid #eee;padding-top:18px;margin:0;">
  If you have any questions, reply to this email or contact us at <a href="mailto:sales@bafnamarbles.com" style="color:#c9a84c;">sales@bafnamarbles.com</a>.
</p>
HTML;
    $html = emailTemplate($subject, $body, 'Your catalog access has been approved!');
    return sendMail($to, $subject, $html, '', $name);
}

/**
 * Send password reset email (replaces the native mail() version in auth.php).
 */
function sendPasswordResetEmail(string $to, string $name, string $token): array {
    $link    = BASE_URL . '/index.php?page=reset_password&token=' . $token;
    $subject = 'Reset Your Password — ' . APP_NAME;
    $body    = <<<HTML
<h2 style="font-size:22px;font-weight:700;color:#0a0a0a;margin:0 0 8px;">Reset your password</h2>
<p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 8px;">Hi {$name},</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
  We received a request to reset your password for your Bafna Marble Catalog account. Click the button below to set a new password.
</p>
<div style="text-align:center;margin:0 0 24px;">
  <a href="{$link}" style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;letter-spacing:.3px;">
    Reset Password
  </a>
</div>
<p style="color:#888;font-size:13px;line-height:1.6;margin:0 0 14px;">
  This link will expire in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.
</p>
<p style="color:#bbb;font-size:12px;line-height:1.6;border-top:1px solid #eee;padding-top:16px;margin:0;">
  Or copy this URL into your browser:<br/>
  <span style="color:#666;">{$link}</span>
</p>
HTML;
    $html = emailTemplate($subject, $body, 'Reset your Bafna Marble Catalog password');
    return sendMail($to, $subject, $html, '', $name);
}

/**
 * Send a welcome email to a user created from the Admin Panel.
 * Contains the username (email), assigned password, and login URL.
 *
 * The plain password is included here ONLY — it must never be logged,
 * displayed in the admin UI, or stored anywhere outside this one-time email.
 */
function sendNewUserEmail(string $to, string $name, string $plainPassword): array {
    $loginUrl = BASE_URL . '/index.php?page=login';
    $subject  = 'Your Account Has Been Created — ' . APP_NAME;
    $body     = <<<HTML
<h2 style="font-size:22px;font-weight:700;color:#0a0a0a;margin:0 0 8px;">Welcome, {$name}!</h2>
<p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 20px;">
  An account has been created for you on the <strong>Bafna Marble Catalog Platform</strong>. You can use the credentials below to sign in.
</p>
<table cellpadding="0" cellspacing="0" width="100%" style="background:#f9f7f4;border:1px solid #e8e0d4;border-radius:10px;margin:0 0 24px;">
  <tr>
    <td style="padding:18px 22px;">
      <p style="margin:0 0 10px;font-size:13px;color:#888;">
        Username (Email)<br/>
        <strong style="font-size:15px;color:#0a0a0a;">{$to}</strong>
      </p>
      <p style="margin:0;font-size:13px;color:#888;">
        Temporary Password<br/>
        <strong style="font-size:15px;color:#0a0a0a;letter-spacing:.5px;">{$plainPassword}</strong>
      </p>
    </td>
  </tr>
</table>
<div style="text-align:center;margin:0 0 24px;">
  <a href="{$loginUrl}" style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;letter-spacing:.3px;">
    Sign In Now →
  </a>
</div>
<p style="color:#888;font-size:13px;line-height:1.6;border-top:1px solid #eee;padding-top:18px;margin:0;">
  For your security, we recommend changing this password after your first login from the Profile page.
  If you weren't expecting this email, please contact us at <a href="mailto:sales@bafnamarbles.com" style="color:#c9a84c;">sales@bafnamarbles.com</a>.
</p>
HTML;
    $html = emailTemplate($subject, $body, 'Your Bafna Marble Catalog account is ready');
    return sendMail($to, $subject, $html, '', $name);
}