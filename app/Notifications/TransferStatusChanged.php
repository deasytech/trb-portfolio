<?php

namespace App\Notifications;

use App\Models\Transfer;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Services\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransferStatusChanged extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(public Transfer $transfer)
    {
        $this->afterCommit = true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Transfer {$this->transfer->reference}: {$this->transfer->status->label()}")
            ->line("Your transfer {$this->transfer->reference} for ".Money::format($this->transfer->amount_minor, $this->transfer->currency)." is now {$this->transfer->status->label()}.")
            ->action('View transfer', route('customer.transfers.show', $this->transfer));
    }

    /**
     * @return array{transfer_id: int, reference: string, status: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'reference' => $this->transfer->reference,
            'status' => $this->transfer->status->value,
            'message' => "Transfer {$this->transfer->reference} is now {$this->transfer->status->label()}.",
        ];
    }
}
