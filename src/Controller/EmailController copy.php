<?php

namespace App\Controller;

use App\Service\EmailService;
use App\Service\ExcelService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class EmailController
{
    private $emailService;
    private $excelService;
    private $csrfTokenManager;

    public function __construct(
        EmailService $emailService,
        ExcelService $excelService,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->emailService = $emailService;
        $this->excelService = $excelService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function index(): Response
    {
        $token = $this->csrfTokenManager->getToken('email_form');
        return new Response(require __DIR__ . '/../../templates/index.php');
    }

    public function uploadExcel(Request $request): JsonResponse
    {
        $this->validateCsrfTokenForm($request);

        $file = $request->files->get('excel_file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        try {
            $recipients = $this->excelService->processExcelFile($file->getPathname());
            return new JsonResponse(['recipients' => $recipients]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        $this->validateCsrfToken($request);

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        }

        if (!isset($data['subject']) || !isset($data['body']) || !isset($data['recipients'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $previews = [];
        foreach ($data['recipients'] as $recipient) {
            $previews[] = [
                'email' => $recipient['email'],
                'subject' => $this->emailService->processTemplate($data['subject'], $recipient),
                'body' => $this->emailService->processTemplate($data['body'], $recipient)
            ];
        }

        return new JsonResponse(['previews' => $previews]);
    }

    public function send(Request $request): JsonResponse
    {
        try {
            $this->validateCsrfToken($request);

            $content = $request->getContent();
            if (empty($content)) {
                return new JsonResponse(['error' => 'Empty request body'], 400);
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'error' => 'Invalid JSON data: ' . json_last_error_msg(),
                    'content' => $content
                ], 400);
            }

            if (!isset($data['subject']) || !isset($data['body']) || !isset($data['recipients'])) {
                return new JsonResponse([
                    'error' => 'Missing required fields',
                    'received_data' => $data
                ], 400);
            }

            // Debug SMTP configuration
            error_log('SMTP Configuration: ' . print_r([
                'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'not set',
                'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'not set',
                'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? 'not set',
                'SMTP_ENCRYPTION' => $_ENV['SMTP_ENCRYPTION'] ?? 'not set',
                'SMTP_PASSWORD' => isset($_ENV['SMTP_PASSWORD']) ? '******' : 'not set'
            ], true));

            $results = [];
            foreach ($data['recipients'] as $recipient) {
                try {
                    $this->emailService->sendEmail(
                        $recipient['email'],
                        $this->emailService->processTemplate($data['subject'], $recipient),
                        $this->emailService->processTemplate($data['body'], $recipient)
                    );
                    $results[$recipient['email']] = ['status' => 'success'];
                } catch (\Exception $e) {
                    error_log('Error sending email to ' . $recipient['email'] . ': ' . $e->getMessage());
                    $results[$recipient['email']] = [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            return new JsonResponse(['results' => $results]);
        } catch (\Exception $e) {
            error_log('Error in send endpoint: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Error sending emails: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    private function validateCsrfToken(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$token || !$this->csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('email_form', $token))) {
            throw new \Exception('Invalid CSRF token');
        }
    }

    private function validateCsrfTokenForm(Request $request): void
    {
        $token = $request->request->get('_csrf_token');
        if (!$token || !$this->csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('email_form', $token))) {
            throw new \Exception('Invalid CSRF token');
        }
    }
} 