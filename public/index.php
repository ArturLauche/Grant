<?php

declare(strict_types=1);

use Grant\Config;
use Grant\Database;
use Grant\Discord\InteractionHandler;
use Grant\Repository\AuditRepository;
use Grant\Repository\OfficerRepository;
use Grant\Service\RoleGate;

require_once __DIR__ . '/../app/Config.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Repository/OfficerRepository.php';
require_once __DIR__ . '/../app/Repository/AuditRepository.php';
require_once __DIR__ . '/../app/Service/RoleGate.php';
require_once __DIR__ . '/../app/Discord/InteractionHandler.php';

$projectRoot = dirname(__DIR__);

try {
    $config = Config::fromEnvironment($projectRoot);
    $body = file_get_contents('php://input') ?: '';
    $signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
    $timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';

    if (!verifyDiscordSignature($config->get('DISCORD_PUBLIC_KEY'), $timestamp, $body, $signature)) {
        respondJson(['error' => 'invalid request signature'], 401);
    }

    $interaction = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    $pdo = Database::connect($config);
    $officers = new OfficerRepository($pdo);
    $audits = new AuditRepository($pdo);
    $gate = new RoleGate([
        'MR' => array_filter(array_map('trim', explode(',', getenv('ROLE_IDS_MR_AND_HIGHER') ?: ''))),
        'HR' => array_filter(array_map('trim', explode(',', getenv('ROLE_IDS_HR_AND_HIGHER') ?: ''))),
    ]);

    $handler = new InteractionHandler($officers, $audits, $gate);
    $response = $handler->handle($interaction);

    respondJson($response, 200);
} catch (Throwable $e) {
    error_log('Grant interaction error: ' . $e->getMessage());
    respondJson([
        'type' => 4,
        'data' => ['content' => 'Internal error. Check server logs.'],
    ], 500);
}

function verifyDiscordSignature(string $publicKeyHex, string $timestamp, string $body, string $signatureHex): bool
{
    if ($publicKeyHex === '' || $timestamp === '' || $signatureHex === '') {
        return false;
    }

    $message = $timestamp . $body;
    $publicKey = hex2bin($publicKeyHex);
    $signature = hex2bin($signatureHex);

    if ($publicKey === false || $signature === false) {
        return false;
    }

    return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
}

function respondJson(array $payload, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
