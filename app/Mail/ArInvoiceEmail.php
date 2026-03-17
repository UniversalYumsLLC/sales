<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArInvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The email subject.
     */
    public string $emailSubject;

    /**
     * The email body (HTML content).
     */
    public string $emailBody;

    /**
     * Path to PDF attachment (optional).
     */
    public ?string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $body, ?string $pdfPath = null)
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: [config('mail.ar_from_address', 'accountsreceivable@universalyums.com')],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ar-invoice',
            with: [
                'body' => $this->emailBody,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->pdfPath || ! file_exists($this->pdfPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->pdfPath)
                ->as(basename($this->pdfPath))
                ->withMime('application/pdf'),
        ];
    }
}
