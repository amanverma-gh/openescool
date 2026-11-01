<?php

namespace App\Services\Brevo;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTP API, so an admin only needs the API key
 * (no SMTP login). Registered as the "brevo" mailer in AppServiceProvider.
 */
class BrevoTransport extends AbstractTransport
{
    /** Headers Brevo builds itself or that we pass in dedicated fields. */
    protected const SKIP_HEADERS = ['from', 'to', 'cc', 'bcc', 'reply-to', 'subject', 'content-type', 'mime-version', 'sender', 'date', 'message-id'];

    public function __construct(protected string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? $message->getEnvelope()->getSender();
        $list = fn (array $addresses) => array_map(fn (Address $a) => array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]), $addresses) ?: null;

        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            if (! in_array(strtolower($header->getName()), self::SKIP_HEADERS, true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }

        $response = Http::withHeaders(['api-key' => $this->key])
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.brevo.com/v3/smtp/email', array_filter([
                'sender' => array_filter(['email' => $from->getAddress(), 'name' => $from->getName() ?: null]),
                'to' => $list($email->getTo()),
                'cc' => $list($email->getCc()),
                'bcc' => $list($email->getBcc()),
                'replyTo' => $list($email->getReplyTo())[0] ?? null,
                'subject' => (string) $email->getSubject(),
                'htmlContent' => $email->getHtmlBody(),
                'textContent' => $email->getTextBody(),
                'headers' => $headers ?: null,
                'attachment' => $this->attachments($email),
            ], fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new RuntimeException('Brevo rejected the email: '.($response->json('message') ?? $response->status()));
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId($id);
        }
    }

    /**
     * @return list<array{name: string, content: string}>|null
     */
    protected function attachments(Email $email): ?array
    {
        $files = array_map(fn ($part) => [
            'name' => $part->getFilename() ?? 'attachment',
            'content' => base64_encode($part->getBody()),
        ], $email->getAttachments());

        return $files ?: null;
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }
}
