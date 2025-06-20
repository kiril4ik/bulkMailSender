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
                // Global CC overrides individual CC from Excel
                'cc' => empty($data['cc']) ? ($recipient['cc'] ?? '') : $data['cc'],
                'subject' => $this->replaceVariables($data['subject'], $recipient),
                'body' => $this->replaceVariables($data['body'], $recipient)
            ];
        }

        return new JsonResponse(['previews' => $previews]);
    }

    public function send(Request $request): JsonResponse
    {
        try {
            $this->validateCsrfToken($request);

            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            if (!isset($data['subject']) || !isset($data['body']) || !isset($data['recipients'])) {
                return new JsonResponse(['error' => 'Missing required fields'], 400);
            }

            // Limit to 5 recipients per request
            $recipients = array_slice($data['recipients'], 0, 5);

            $results = [];
            foreach ($recipients as $recipient) {
                try {
                    $this->emailService->sendEmail(
                        $recipient['email'],
                        $this->replaceVariables($data['subject'], $recipient),
                        $this->replaceVariables($data['body'], $recipient),
                        $recipient,
                        // Global CC overrides individual CC from Excel
                        empty($data['cc']) ? ($recipient['cc'] ?? '') : $data['cc']
                    );
                    $results[$recipient['email']] = ['status' => 'success'];
                } catch (\Exception $e) {
                    $results[$recipient['email']] = [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            return new JsonResponse(['results' => $results]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Server error: ' . $e->getMessage()
            ], 403);
        }
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

    private function validateCsrfToken(Request $request): void
    {
        $token = new CsrfToken('email_form', $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new \RuntimeException('Invalid CSRF token');
        }
    }

    private function validateCsrfTokenForm(Request $request): void
    {
        $token = new CsrfToken('email_form', $request->request->get('_csrf_token'));
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