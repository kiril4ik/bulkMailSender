<?php

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class IpWhitelistMiddleware
{
    private array $allowedIps;

    public function __construct(array $allowedIps = [])
    {
        $this->allowedIps = $allowedIps;
    }

    public function handle(Request $request): ?Response
    {
        $clientIp = $this->getClientIp($request);
        
        if (empty($this->allowedIps)) {
            // If no IPs are configured, allow all
            return null;
        }

        if (!$this->isIpAllowed($clientIp)) {
            return new JsonResponse([
                'error' => 'Access denied',
                'message' => 'Your IP address is not authorized to access this resource'
            ], 403);
        }

        return null; // Continue to next middleware/controller
    }

    private function getClientIp(Request $request): string
    {
        // Check for forwarded IP headers (for proxies/load balancers)
        $forwardedHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];

        foreach ($forwardedHeaders as $header) {
            $ip = $request->server->get($header);
            if ($ip) {
                // Handle multiple IPs in forwarded header (take the first one)
                $ips = explode(',', $ip);
                $clientIp = trim($ips[0]);
                if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $clientIp;
                }
            }
        }

        // Fallback to direct IP
        return $request->getClientIp();
    }

    private function isIpAllowed(string $ip): bool
    {
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipMatches($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        // Handle CIDR notation (e.g., 192.168.1.0/24)
        if (strpos($pattern, '/') !== false) {
            return $this->ipInCidr($ip, $pattern);
        }

        // Handle wildcard notation (e.g., 192.168.*.*)
        if (strpos($pattern, '*') !== false) {
            return $this->ipMatchesWildcard($ip, $pattern);
        }

        // Exact match
        return $ip === $pattern;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        $ipBinary = ip2long($ip);
        $subnetBinary = ip2long($subnet);
        $maskBinary = ~((1 << (32 - $mask)) - 1);
        
        return ($ipBinary & $maskBinary) === ($subnetBinary & $maskBinary);
    }

    private function ipMatchesWildcard(string $ip, string $pattern): bool
    {
        $pattern = str_replace('*', '\d+', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        return preg_match($pattern, $ip) === 1;
    }

    public function addAllowedIp(string $ip): void
    {
        if (!in_array($ip, $this->allowedIps)) {
            $this->allowedIps[] = $ip;
        }
    }

    public function removeAllowedIp(string $ip): void
    {
        $key = array_search($ip, $this->allowedIps);
        if ($key !== false) {
            unset($this->allowedIps[$key]);
            $this->allowedIps = array_values($this->allowedIps);
        }
    }

    public function getAllowedIps(): array
    {
        return $this->allowedIps;
    }
} 