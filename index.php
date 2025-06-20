<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Config;
use App\Controller\EmailController;
use App\Service\EmailService;
use App\Service\ExcelService;
use App\Middleware\IpWhitelistMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

// Initialize configuration
Config::init();

// Initialize IP whitelist middleware
$allowedIps = Config::get('security.allowed_ips', []);
$ipWhitelistMiddleware = new IpWhitelistMiddleware($allowedIps);

// Initialize CSRF protection
$csrfTokenManager = new CsrfTokenManager(
    new UriSafeTokenGenerator(),
    new NativeSessionTokenStorage()
);

// Initialize services
$emailService = new EmailService();
$excelService = new ExcelService();

// Initialize controller
$controller = new EmailController($emailService, $excelService, $csrfTokenManager);

// Handle request
$request = Request::createFromGlobals();

// Check IP whitelist
$ipResponse = $ipWhitelistMiddleware->handle($request);
if ($ipResponse !== null) {
    $ipResponse->send();
    exit;
}

$path = $request->getPathInfo();

try {
    switch ($path) {
        case '/':
            $response = $controller->index();
            break;
        case '/preview':
            $response = $controller->preview($request);
            break;
        case '/send':
            $response = $controller->send($request);
            break;
        case '/upload-excel':
            $response = $controller->uploadExcel($request);
            break;
        default:
            $response = new JsonResponse(['error' => 'Not found'], 404);
    }
} catch (Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 500);
}

// Ensure we have a valid response
if (!$response instanceof Response) {
    $response = new JsonResponse(['error' => 'Invalid response from server'], 500);
}

$response->send(); 