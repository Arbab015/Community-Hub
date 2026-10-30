<?php

namespace App\Notifications;

use App\Models\PropertyTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transfer lifecycle notices: society owner on create, requester + recipient
 * on approve/reject. The database payload matches the navbar bell contract
 * (title/message, optional avator).
 */
class PropertyTransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected PropertyTransfer $transfer,
        protected string $kind
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->line($this->message())
            ->line('Reference: '.$this->transfer->reference_no)
            ->action('Open Property', $this->propertyUrl());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->subject(),
            'message' => $this->message().' ('.$this->transfer->reference_no.')',
        ];
    }

    private function subject(): string
    {
        return match ($this->kind) {
            'approved' => 'Property Transfer Approved',
            'rejected' => 'Property Transfer Rejected',
            default => 'Property Transfer Awaiting Approval',
        };
    }

    private function message(): string
    {
        $label = $this->propertyLabel();

        return match ($this->kind) {
            'approved' => 'The transfer of '.$label.' to '
                .$this->name($this->transfer->recipient).' has been approved.',
            'rejected' => 'The transfer of '.$label.' to '
                .$this->name($this->transfer->recipient).' was rejected.'
                .($this->transfer->remarks ? ' Remarks: '.$this->transfer->remarks : ''),
            default => $this->name($this->transfer->requestedBy)
                .' requested to transfer '.$label.' to '
                .$this->name($this->transfer->recipient).'.',
        };
    }

    private function propertyLabel(): string
    {
        $ownerable = $this->transfer->propertyOwnerable;

        if ($ownerable instanceof \App\Models\PropertyUnit) {
            return 'unit '.($ownerable->unit_name ?: $ownerable->uuid);
        }

        return 'property '.($ownerable->property_no ?: $ownerable->uuid);
    }

    private function propertyUrl(): string
    {
        $ownerable = $this->transfer->propertyOwnerable;

        if ($ownerable instanceof \App\Models\PropertyUnit) {
            return route('unit.details', $ownerable->uuid);
        }

        return route('property.details', $ownerable->uuid);
    }

    private function name($user): string
    {
        return trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'A member';
    }
}
