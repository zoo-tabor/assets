<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Odesilani e-mailu pres mail() Wedosu, odesilatel dle .env (MAIL_FROM).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = Env::get('MAIL_FROM', 'ekospol@ekospol.cz');
        $fromName = Env::get('MAIL_FROM_NAME', 'Evidence majetku');

        $headers = [
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: EvidenceMajetku',
        ];

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }
}
