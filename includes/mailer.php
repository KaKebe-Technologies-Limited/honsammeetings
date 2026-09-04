<?php
/**
 * Minimal self-contained SMTP client (STARTTLS + AUTH LOGIN).
 * No external libraries required — talks raw SMTP over a TLS socket,
 * which is all Gmail (and most providers) need for an App Password.
 */
require_once __DIR__ . '/../config.php';

class SmtpMailError extends Exception {}

/**
 * Send one email via authenticated SMTP.
 *
 * @param string[] $to  Primary recipient email addresses.
 * @param string[] $cc  CC'd email addresses.
 * @param ?string  $htmlBody  When given, sent as multipart/alternative alongside $textBody
 *                             (the plain-text version email clients fall back to).
 * @param array<int,array{filename:string,content:string,mime:string}> $attachments
 *                             Binary file attachments, e.g. a generated PDF.
 * @throws SmtpMailError on any failure (caller decides how to log/report it).
 */
function send_mail_smtp(array $to, string $subject, string $textBody, array $cc = [], ?string $htmlBody = null, array $attachments = []): void
{
    if (!SMTP_HOST || !SMTP_USERNAME || !SMTP_PASSWORD) {
        throw new SmtpMailError('SMTP is not configured (mail_secret.php missing or incomplete).');
    }

    $to = array_values(array_unique(array_filter($to)));
    $cc = array_values(array_unique(array_filter($cc)));
    $allRecipients = array_merge($to, $cc);
    if (!$allRecipients) {
        throw new SmtpMailError('No recipients supplied.');
    }

    $timeout = 15;
    $sock = @stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, $timeout
    );
    if (!$sock) {
        throw new SmtpMailError("Could not connect to " . SMTP_HOST . ':' . SMTP_PORT . " — $errstr ($errno)");
    }
    stream_set_timeout($sock, $timeout);

    $read = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            // Multi-line SMTP replies use "250-" ... final line is "250 ".
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $write = function (string $cmd) use ($sock): void {
        fwrite($sock, $cmd . "\r\n");
    };
    $expect = function (string $sent, array $okCodes) use ($read, $sock): string {
        $resp = $read();
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            fclose($sock);
            throw new SmtpMailError("SMTP error after '$sent': $resp");
        }
        return $resp;
    };

    $read(); // server greeting (220)

    $write('EHLO localhost');
    $expect('EHLO', [250]);

    $write('STARTTLS');
    $expect('STARTTLS', [220]);

    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($sock);
        throw new SmtpMailError('TLS negotiation failed.');
    }

    // Re-issue EHLO after TLS upgrade, as required by the protocol.
    $write('EHLO localhost');
    $expect('EHLO', [250]);

    $write('AUTH LOGIN');
    $expect('AUTH LOGIN', [334]);
    $write(base64_encode(SMTP_USERNAME));
    $expect('username', [334]);
    $write(base64_encode(SMTP_PASSWORD));
    $expect('password', [235]);

    $write('MAIL FROM:<' . SMTP_FROM . '>');
    $expect('MAIL FROM', [250]);

    foreach ($allRecipients as $addr) {
        $write('RCPT TO:<' . $addr . '>');
        $expect('RCPT TO', [250, 251]);
    }

    $write('DATA');
    $expect('DATA', [354]);

    $headers = [];
    $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>';
    $headers[] = 'To: ' . implode(', ', $to);
    if ($cc) {
        $headers[] = 'Cc: ' . implode(', ', $cc);
    }
    $headers[] = 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8');
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'MIME-Version: 1.0';

    // The text/plain (+ optional text/html) part, standalone or nested inside
    // a multipart/mixed envelope when there are attachments.
    if ($htmlBody !== null) {
        $altBoundary = 'alt_' . bin2hex(random_bytes(16));
        $altContentType = 'multipart/alternative; boundary="' . $altBoundary . '"';
        $altBody =
            "--{$altBoundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $textBody . "\r\n\r\n"
            . "--{$altBoundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$altBoundary}--";
    } else {
        $altContentType = 'text/plain; charset=UTF-8';
        $altBody = $textBody;
    }

    if ($attachments) {
        $mixBoundary = 'mix_' . bin2hex(random_bytes(16));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixBoundary . '"';

        $bodyText = "--{$mixBoundary}\r\nContent-Type: {$altContentType}\r\n"
            . ($htmlBody === null ? "Content-Transfer-Encoding: 8bit\r\n" : '')
            . "\r\n" . $altBody . "\r\n\r\n";
        foreach ($attachments as $att) {
            $bodyText .= "--{$mixBoundary}\r\n"
                . "Content-Type: {$att['mime']}; name=\"{$att['filename']}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$att['filename']}\"\r\n\r\n"
                . chunk_split(base64_encode($att['content']))
                . "\r\n";
        }
        $bodyText .= "--{$mixBoundary}--";
    } else {
        $headers[] = 'Content-Type: ' . $altContentType;
        if ($htmlBody === null) {
            $headers[] = 'Content-Transfer-Encoding: 8bit';
        }
        $bodyText = $altBody;
    }

    // Dot-stuff any line starting with a lone "." per RFC 5321.
    $escapedBody = preg_replace('/^\./m', '..', $bodyText);

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.";
    $write($message);
    $expect('DATA payload', [250]);

    $write('QUIT');
    fclose($sock);
}
