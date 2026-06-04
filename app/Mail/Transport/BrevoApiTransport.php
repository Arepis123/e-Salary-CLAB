<?php

namespace App\Mail\Transport;

use Brevo\Brevo;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestAttachmentItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestBccItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestCcItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through the Brevo transactional email API (POST /v3/smtp/email)
 * using the official getbrevo/brevo-php SDK.
 *
 * Implemented as a Symfony/Laravel transport so every existing Mailable,
 * attachment, queued send, cc/bcc and reply-to keeps working unchanged — only
 * the delivery channel changes from SMTP to the HTTPS API.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly Brevo $client)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $values = [
            'sender' => $this->sender($email),
            'to' => $this->addresses($email->getTo(), SendTransacEmailRequestToItem::class),
            'subject' => $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $values['htmlContent'] = is_resource($html) ? stream_get_contents($html) : $html;
        }

        if ($text = $email->getTextBody()) {
            $values['textContent'] = is_resource($text) ? stream_get_contents($text) : $text;
        }

        if ($cc = $email->getCc()) {
            $values['cc'] = $this->addresses($cc, SendTransacEmailRequestCcItem::class);
        }

        if ($bcc = $email->getBcc()) {
            $values['bcc'] = $this->addresses($bcc, SendTransacEmailRequestBccItem::class);
        }

        if ($replyTo = $email->getReplyTo()) {
            $values['replyTo'] = new SendTransacEmailRequestReplyTo($this->addressValues($replyTo[0]));
        }

        if ($attachments = $this->attachments($email)) {
            $values['attachment'] = $attachments;
        }

        $response = $this->client->transactionalEmails->sendTransacEmail(
            new SendTransacEmailRequest($values)
        );

        if ($response && $response->messageId) {
            $message->setMessageId($response->messageId);
        }
    }

    /**
     * Build the Brevo sender object from the message's "From" address.
     */
    private function sender(Email $email): SendTransacEmailRequestSender
    {
        $from = $email->getFrom();

        return new SendTransacEmailRequestSender($this->addressValues($from[0] ?? null));
    }

    /**
     * Map a list of Symfony addresses to Brevo recipient objects of the given type.
     *
     * @param  Address[]  $addresses
     * @param  class-string  $class
     * @return array<int, object>
     */
    private function addresses(array $addresses, string $class): array
    {
        return array_map(fn (Address $address) => new $class($this->addressValues($address)), $addresses);
    }

    /**
     * Reduce a Symfony address to the {email, name?} shape Brevo expects.
     *
     * @return array{email?: string, name?: string}
     */
    private function addressValues(?Address $address): array
    {
        if (! $address) {
            return [];
        }

        $values = ['email' => $address->getAddress()];

        if ($name = $address->getName()) {
            $values['name'] = $name;
        }

        return $values;
    }

    /**
     * Convert the message's attachments to base64-encoded Brevo attachment objects.
     *
     * @return array<int, SendTransacEmailRequestAttachmentItem>
     */
    private function attachments(Email $email): array
    {
        $items = [];

        foreach ($email->getAttachments() as $part) {
            $filename = $part->getPreparedHeaders()
                ->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment';

            $items[] = new SendTransacEmailRequestAttachmentItem([
                'name' => $filename,
                'content' => base64_encode($part->getBody()),
            ]);
        }

        return $items;
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
