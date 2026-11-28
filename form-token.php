<?php
/**
 * Выдаёт одноразовую метку времени для форм.
 * Форма присылает её вместе с заявкой, сервер проверяет подпись и возраст.
 *
 * Подпись — HMAC от времени на секрете из config.php (хеш пароля панели),
 * поэтому внешнему боту, чтобы пройти проверку, придётся сначала
 * загрузить страницу, подождать и только потом отправить форму.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configFile = dirname(__DIR__) . '/data/config.php';
if (is_file($configFile)) {
    require_once $configFile;
}

$secret = defined('ADMIN_PASSWORD_HASH') ? (string) ADMIN_PASSWORD_HASH : 'ivu-site-form';
$ts = time();
$sig = hash_hmac('sha256', (string) $ts, $secret);

$captchaKey = defined('SMARTCAPTCHA_CLIENT_KEY') ? (string) SMARTCAPTCHA_CLIENT_KEY : '';

echo json_encode(['ts' => $ts, 'sig' => $sig, 'captchaKey' => $captchaKey]);
