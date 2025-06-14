<?php

namespace App\Controller;

use App\Service\EmailService;
use App\Service\ExcelService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfToken;

class EmailController
{
    private EmailService $emailService;
    private ExcelService $excelService;
    private CsrfTokenManager $csrfTokenManager;

    public function __construct(
        EmailService $emailService,
        ExcelService $excelService,
        CsrfTokenManager $csrfTokenManager
    ) {
        $this->emailService = $emailService;
        $this->excelService = $excelService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function index(): Response
    {
        $token = $this->csrfTokenManager->getToken('email_form');
        ob_start();
        include __DIR__ . '/../../templates/index.php';
        return new Response(ob_get_clean());
    }

    public function preview(Request $request): JsonResponse
    {
        $this->validateCsrfToken($request, 'email_form');

        $content = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON data');
        }

        $subject = $content['subject'] ?? '';
        $body = $content['body'] ?? '';
        $recipients = $content['recipients'] ?? [];

        if (empty($subject) || empty($body) || empty($recipients)) {
            throw new \RuntimeException('Missing required fields');
        }

        $previews = [];
        foreach ($recipients as $recipient) {
            $previews[] = [
                'email' => $recipient['email'],
                'subject' => $this->replaceVariables($subject, $recipient),
                'body' => $this->replaceVariables($body, $recipient)
            ];
        }

        return new JsonResponse(['previews' => $previews]);
    }

    public function send(Request $request): JsonResponse
    {
        $this->validateCsrfToken($request, 'email_form');

        $content = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON data');
        }

        $subject = $content['subject'] ?? '';
        $body = $content['body'] ?? '';
        $recipients = $content['recipients'] ?? [];

        if (empty($subject) || empty($body) || empty($recipients)) {
            throw new \RuntimeException('Missing required fields');
        }

        $results = $this->emailService->sendBulkEmails($recipients, $subject, $body);

        return new JsonResponse(['results' => $results]);
    }

    public function uploadExcel(Request $request): JsonResponse
    {
        $this->validateCsrfTokenForm($request, 'email_form');

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

    private function validateCsrfToken(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new \RuntimeException('Invalid CSRF token');
        }
    }

    private function validateCsrfTokenForm(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new \RuntimeException('Invalid CSRF token');
        }
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        return $content;
    }
} 