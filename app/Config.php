<?php

declare(strict_types=1);

namespace Grant;

final class Config
{
    public function __construct(private array $values)
    {
    }

    public static function fromEnvironment(string $projectRoot): self
    {
        $envPath = $projectRoot . '/.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ($key !== '' && getenv($key) === false) {
                    putenv(sprintf('%s=%s', $key, $value));
                    $_ENV[$key] = $value;
                }
            }
        }

        $required = [
            'DISCORD_BOT_TOKEN',
            'DISCORD_APPLICATION_ID',
            'DISCORD_PUBLIC_KEY',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
        ];

        $values = [];
        foreach ($required as $key) {
            $value = getenv($key);
            if ($value === false || $value === '') {
                throw new \RuntimeException("Missing required environment variable: {$key}");
            }
            $values[$key] = $value;
        }

        $values['APP_ENV'] = getenv('APP_ENV') ?: 'production';
        $values['DISCORD_GUILD_ID'] = getenv('DISCORD_GUILD_ID') ?: '';

        return new self($values);
    }

    public function get(string $key): string
    {
        return (string) ($this->values[$key] ?? '');
    }
}
