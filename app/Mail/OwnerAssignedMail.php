<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $member;
    public $propertyName;
    public $planName;
    public $password;

    public function __construct($member, string $propertyName, ?string $planName, ?string $password = null)
    {
        $this->member = $member;
        $this->propertyName = $propertyName;
        $this->planName = $planName;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Property Ownership Assigned — '.$this->propertyName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.owner_assigned',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
