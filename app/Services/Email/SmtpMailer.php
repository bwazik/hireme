<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\SystemSetting;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SmtpMailer
{
    public function __construct(private readonly EmailAccount $account) {}

    /**
     * Send an email. Throws PHPMailerException on failure.
     *
     * @param  bool  $attachCv  Whether to attach Abdullah_Mohamed_CV.pdf
     */
    public function send(string $to, string $subject, string $body, bool $attachCv = true): void
    {
        $mail = new PHPMailer(true);
        // ── Charset & encoding ────────────────────────────────────────────────
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;

        $mail->isSMTP();
        $mail->Host       = $this->account->smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->account->email;
        $mail->Password   = $this->account->password; // NOT decrypt(), it's casted!
        $mail->SMTPSecure = $this->account->smtp_encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->account->smtp_port;

        $mail->setFrom($this->account->email, $this->account->sender_name);
        $mail->addAddress($to);

        // ── RFC 2047 MIME Header Encoding for Subject ─────────────────────────
        // Explicitly encode the subject in Base64 UTF-8 to prevent mojibake in email client subject lines
        $mail->Subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
        
        // ── HTML Body with strict UTF-8 Meta Tag ──────────────────────────────
        $safeHtml = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $mail->Body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;">' . $safeHtml . '</body></html>';
        
        // ── Plain Text Fallback (Critical for Spam Filters) ───────────────────
        $mail->AltBody = $body;
        $mail->isHTML(true);

        if ($attachCv) {
            $cvPath = SystemSetting::get('email_cv_path', 'cv/Abdullah_Mohamed_CV.pdf');
            $fullPath = storage_path('app/' . $cvPath);

            if (file_exists($fullPath)) {
                $mail->addAttachment($fullPath, 'Abdullah_Mohamed_CV.pdf');
            }
        }

        $mail->send();
    }
}
