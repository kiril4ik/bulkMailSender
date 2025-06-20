<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Config\Config;

class EmailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer(): void
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = Config::get('smtp.host');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = Config::get('smtp.username');
        $this->mailer->Password = Config::get('smtp.password');
        $this->mailer->SMTPSecure = Config::get('smtp.encryption');
        $this->mailer->Port = Config::get('smtp.port');
        $this->mailer->isHTML(true);
        
        // Set character encoding
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Encoding = 'base64';
    }

    public function sendEmail(string $to, string $subject, string $body, array $variables = [], string $cc = ''): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            
            // Handle comma-separated email addresses for TO
            $emails = array_map('trim', explode(',', $to));
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException("Invalid email address: {$email}");
                }
                $this->mailer->addAddress($email);
            }
            
            // Handle CC if provided
            if (!empty($cc)) {
                $ccEmails = array_map('trim', explode(',', $cc));
                foreach ($ccEmails as $ccEmail) {
                    if (!filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException("Invalid CC email address: {$ccEmail}");
                    }
                    $this->mailer->addCC($ccEmail);
                }
            }
            
            // Ensure proper encoding of subject and body
            $this->mailer->Subject = mb_encode_mimeheader($this->replaceVariables($subject, $variables), 'UTF-8', 'B');
            $this->mailer->Body = $this->replaceVariables($body, $variables);
            $this->mailer->AltBody = strip_tags($this->mailer->Body);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to send email: {$e->getMessage()}");
        }
    }

    public function sendBulkEmails(array $recipients, string $subject, string $body, string $cc = ''): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            try {
                $this->sendEmail(
                    $recipient['email'],
                    $subject,
                    $body,
                    $recipient,
                    empty($cc) ? ($recipient['cc'] ?? '') : $cc
                );
                $results[$recipient['email']] = ['status' => 'success'];
            } catch (\Exception $e) {
                $results[$recipient['email']] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        return $content;
    }
} 