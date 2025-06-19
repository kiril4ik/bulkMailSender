<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];

    public static function init(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        self::$config = [
            'smtp' => [
                'host' => $_ENV['SMTP_HOST'],
                'port' => $_ENV['SMTP_PORT'],
                'username' => $_ENV['SMTP_USERNAME'],
                'password' => $_ENV['SMTP_PASSWORD'],
                'encryption' => $_ENV['SMTP_ENCRYPTION'],
            ],
            'app' => [
                'env' => $_ENV['APP_ENV'],
                'secret' => $_ENV['APP_SECRET'],
            ],
            'security' => [
                'allowed_ips' => isset($_ENV['ALLOWED_IPS']) ? explode(',', $_ENV['ALLOWED_IPS']) : [],
            ],
        ];
    }

    public static function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
} 