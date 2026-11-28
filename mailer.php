<?php
/**
 * Отправка писем с сайта.
 *
 * Если в data/config.php заданы SMTP-доступы — письмо уходит через них
 * (Яндекс 360, smtp.yandex.ru:465). Иначе — обычная mail().
 *
 * Почему через SMTP: домен ivu.ru обслуживает Яндекс (mx.yandex.ru),
 * а письма, отправленные функцией mail() напрямую с сервера Бегета,
 * Яндекс не принимает — у домена нет SPF и DKIM для этого сервера.
 */

declare(strict_types=1);

/**
 * @return bool удалось ли отправить
 */
function ivuMail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '') {
        $sent = ivuSmtpSend(
            (string) SMTP_HOST,
            defined('SMTP_PORT') ? (int) SMTP_PORT : 465,
            (string) SMTP_USER,
            defined('SMTP_PASS') ? (string) SMTP_PASS : '',
            $to,
            $encodedSubject,
            $body
        );
        if ($sent) {
            return true;
        }
        // не получилось — пробуем обычной почтой, вдруг сработает
    }

    return @mail(
        $to,
        $encodedSubject,
        $body,
        "From: ivu.ru <no-reply@ivu.ru>\r\nContent-Type: text/plain; charset=UTF-8\r\n"
    );
}

function ivuSmtpSend(string $host, int $port, string $user, string $pass, string $to, string $subject, string $body): bool
{
    $timeout = 15;
    $context = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'timeout' => $timeout],
    ]);

    $socket = @stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($socket === false) {
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $read = static function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $data;
    };

    $send = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $ok = static function (string $reply): bool {
        return isset($reply[0]) && ($reply[0] === '2' || $reply[0] === '3');
    };

    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $send('EHLO ivu.ru');
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $send('AUTH LOGIN');
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }
    $send(base64_encode($user));
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }
    $send(base64_encode($pass));
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $send('MAIL FROM:<' . $user . '>');
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $send('RCPT TO:<' . $to . '>');
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $send('DATA');
    if (!$ok($read())) {
        fclose($socket);
        return false;
    }

    $headers = 'From: Интер-Техника-Плюс <' . $user . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $subject . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . 'Date: ' . date('r') . "\r\n";

    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $normalized = preg_replace('/^\./m', '..', $normalized) ?? $normalized;
    $normalized = str_replace("\n", "\r\n", $normalized);

    fwrite($socket, $headers . "\r\n" . $normalized . "\r\n.\r\n");
    $reply = $read();
    $send('QUIT');
    fclose($socket);

    return $ok($reply);
}
