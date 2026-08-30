<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmission extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Subject lines by topic. Keys must match the validation rule in
     * ContactController::store().
     */
    private const SUBJECTS = [
        'general' => 'Contact form message from :name',
        'registration' => 'League registration question from :name',
        'partnership' => 'Partnership enquiry from :name',
        'support' => 'Technical support request from :name',
        'sponsorship' => 'Sponsorship enquiry from :name',
    ];

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $topic,
        public string $body,
    ) {
    }

    public function envelope(): Envelope
    {
        $template = self::SUBJECTS[$this->topic] ?? self::SUBJECTS['general'];

        return new Envelope(
            subject: str_replace(':name', $this->senderName, $template),
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-submission');
    }
}
