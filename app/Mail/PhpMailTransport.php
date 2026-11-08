<?php

namespace App\Mail;

use Closure;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends mail with PHP's mail() function (MAIL_MAILER=phpmail).
 *
 * Hostinger shared hosting disables proc_open, so Symfony's sendmail transport cannot run there, while mail() works
 * and needs no mailbox password. Symfony renders the full message; To and Subject move into mail()'s own arguments
 * (mail() writes those headers itself), every other header is passed through as is.
 */
class PhpMailTransport extends AbstractTransport
{
    /** @param (Closure(string, string, string, string, string): bool)|null $mailer  for tests; defaults to mail() */
    public function __construct(private ?Closure $mailer = null)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $parts = self::split($message->toString());
        if ($parts['to'] === '') {
            throw new TransportException('The message has no To header.');
        }

        $sender = $message->getEnvelope()->getSender()->getAddress();
        $send = $this->mailer ?? fn (string ...$args): bool => mail(...$args);

        if (! $send($parts['to'], $parts['subject'], $parts['body'], $parts['headers'], '-f'.$sender)) {
            throw new TransportException('mail() did not accept the message.');
        }
    }

    public function __toString(): string
    {
        return 'phpmail://default';
    }

    /**
     * Split a rendered message into mail()'s arguments.
     *
     * @return array{to: string, subject: string, headers: string, body: string}
     */
    public static function split(string $raw): array
    {
        [$head, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');

        $to = '';
        $subject = '';
        $headers = [];
        // One entry per header; folded continuation lines stay attached to their header.
        foreach (preg_split("/\r?\n(?![ \t])/", $head) as $line) {
            $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $line);
            if (preg_match('/^to:\s*(.*)$/is', $unfolded, $m)) {
                $to = trim($m[1]);
            } elseif (preg_match('/^subject:\s*(.*)$/is', $unfolded, $m)) {
                $subject = trim($m[1]);
            } elseif ($line !== '') {
                $headers[] = $line;
            }
        }

        return ['to' => $to, 'subject' => $subject, 'headers' => implode("\r\n", $headers), 'body' => $body];
    }
}
