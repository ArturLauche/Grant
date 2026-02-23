<?php

declare(strict_types=1);

use Grant\Config;
use Grant\Discord\CommandCatalog;

require_once __DIR__ . '/../app/Config.php';
require_once __DIR__ . '/../app/Discord/CommandCatalog.php';

$config = Config::fromEnvironment(dirname(__DIR__));
$token = $config->get('DISCORD_BOT_TOKEN');
$appId = $config->get('DISCORD_APPLICATION_ID');
$guildId = $config->get('DISCORD_GUILD_ID');

$endpoint = $guildId !== ''
    ? "https://discord.com/api/v10/applications/{$appId}/guilds/{$guildId}/commands"
    : "https://discord.com/api/v10/applications/{$appId}/commands";

$payload = json_encode(CommandCatalog::definitions(), JSON_THROW_ON_ERROR);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bot ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    fwrite(STDERR, "Request failed: {$error}\n");
    exit(1);
}

echo "Discord API status: {$status}\n";
echo $response . "\n";

if ($status < 200 || $status >= 300) {
    exit(1);
}
