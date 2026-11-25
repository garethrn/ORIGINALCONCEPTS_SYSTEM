<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Models\BankMailbox;
use RuntimeException;

final class ImapMailbox
{
    /**
     * @return list<string>
     */
    public function unseen(BankMailbox $mailbox): array
    {
        if ($mailbox->imap_host === null || $mailbox->imap_username === null) {
            return [];
        }
        $remote = ($mailbox->imap_encryption === 'ssl' ? 'ssl://' : '').$mailbox->imap_host.':'.$mailbox->imap_port;
        $socket = @stream_socket_client($remote, $errno, $error, 15, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new RuntimeException('Could not connect to the mailbox: '.$error);
        }
        stream_set_timeout($socket, 20);
        $this->read($socket);
        $this->command($socket, 'A1', 'LOGIN '.$this->quote((string) $mailbox->imap_username).' '.$this->quote((string) $mailbox->imap_password));
        $this->command($socket, 'A2', 'SELECT '.$this->quote($mailbox->imap_folder ?: 'INBOX'));
        $search = $this->command($socket, 'A3', 'UID SEARCH UNSEEN');
        preg_match_all('/\d+/', $search, $ids);
        $messages = [];
        foreach ($ids[0] as $uid) {
            if (! ctype_digit($uid)) {
                continue;
            }
            $fetched = $this->command($socket, 'A4'.$uid, 'UID FETCH '.$uid.' BODY.PEEK[]');
            if (preg_match('/\r\n\r\n(.*)\r\nA4'.$uid.' OK/s', $fetched, $match) === 1) {
                $messages[] = $match[1];
            }
            $this->command($socket, 'A5'.$uid, 'UID STORE '.$uid.' +FLAGS (\Seen)');
        }
        $this->command($socket, 'A6', 'LOGOUT');
        fclose($socket);

        return $messages;
    }

    private function command($socket, string $tag, string $command): string
    {
        fwrite($socket, $tag.' '.$command."\r\n");
        $response = $this->read($socket);
        if (! str_contains($response, $tag.' OK')) {
            throw new RuntimeException('The mailbox rejected '.$tag.'.');
        }

        return $response;
    }

    private function read($socket): string
    {
        $buffer = '';
        while (! feof($socket)) {
            $line = fgets($socket, 8192);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (preg_match('/^A\d+ (OK|NO|BAD)/m', $buffer) === 1 || str_starts_with($buffer, '* OK') || str_starts_with($buffer, '* PREAUTH')) {
                if (preg_match('/^A\d+ /m', $buffer) === 1 || ! str_contains($buffer, 'A')) {
                    if (preg_match('/^A\d+ /m', $buffer) === 1 || preg_match('/^\* (OK|PREAUTH)/m', $buffer) === 1) {
                        if (preg_match('/^A\d+ /m', $buffer) === 1) {
                            break;
                        }
                        if (! str_contains($buffer, "\nA")) {
                            break;
                        }
                    }
                }
            }
        }

        return $buffer;
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
