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
    }

    public function sendEmail(string $to, string $subject, string $body, array $variables = []): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $this->replaceVariables($subject, $variables);
            $this->mailer->Body = $this->replaceVariables($body, $variables);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to send email: {$e->getMessage()}");
        }
    }

    public function sendBulkEmails(array $recipients, string $subject, string $body): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            try {
                $this->sendEmail(
                    $recipient['email'],
                    $subject,
                    $body,
                    $recipient
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