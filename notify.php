<?php
/**
 * Уведомления в Телеграм.
 *
 * Токен бота и адресат хранятся в data/config.php:
 *   TELEGRAM_TOKEN    — токен от @BotFather
 *   TELEGRAM_CHAT_ID  — кому писать (ваш чат с ботом)
 *
 * Важно: бот может написать только тому, кто сам ему написал (/start).
 * По номеру телефона отправить сообщение нельзя — это ограничение Телеграма.
 */

declare(strict_types=1);

/**
 * Вызов метода Bot API.
 *
 * @return array<string,mixed>|null
 */
function ivuTelegramApi(string $token, string $method, array $params = []): ?array
{
    if ($token === '') {
        return null;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !is_string($response)) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * Отправить сообщение владельцу сайта.
 */
function ivuTelegram(string $text): bool
{
    if (!defined('TELEGRAM_TOKEN') || TELEGRAM_TOKEN === '') {
        return false;
    }
    if (!defined('TELEGRAM_CHAT_ID') || TELEGRAM_CHAT_ID === '') {
        return false;
    }

    $result = ivuTelegramApi((string) TELEGRAM_TOKEN, 'sendMessage', [
        'chat_id' => (string) TELEGRAM_CHAT_ID,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ]);

    return is_array($result) && !empty($result['ok']);
}

/**
 * Узнать chat_id по последнему сообщению боту.
 * Человеку достаточно нажать /start в боте, потом «Проверить связь» в панели.
 */
function ivuTelegramDetectChat(string $token): ?string
{
    $result = ivuTelegramApi($token, 'getUpdates', ['limit' => 20]);
    if (!is_array($result) || empty($result['ok']) || empty($result['result'])) {
        return null;
    }

    $chatId = null;
    foreach ($result['result'] as $update) {
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (is_array($message) && isset($message['chat']['id'])) {
            $chatId = (string) $message['chat']['id'];
        }
    }

    return $chatId;
}
