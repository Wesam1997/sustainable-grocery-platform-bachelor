<?php
declare(strict_types=1);

namespace App\Application\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    public function send(
        string $recipient,
        string $subject,
        string $body
    ): void {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USERNAME'];
            $mail->Password = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) $_ENV['SMTP_PORT'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(
                $_ENV['SMTP_USERNAME'],
                $_ENV['SMTP_FROM_NAME'] ?? 'Flash Food'
            );

            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            $mail->send();
        } catch (Exception $e) {
            error_log('Flash Food SMTP error: ' . $e->getMessage());
            throw new \RuntimeException(
                'Email could not be sent.',
                0,
                $e
            );
        }
    }
}
