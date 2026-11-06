<?php

namespace App\Notifications;

use App\Models\FundsHold;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundsHoldChanged extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(public FundsHold $hold, public string $action)
    {
        $this->afterCommit = true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Funds hold {$this->hold->reference}: {$this->action}")
            ->line($this->message())
            ->action('View funds on hold', route('customer.holds'));
    }

    /**
     * @return array{hold_id: int, reference: string, action: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'hold_id' => $this->hold->id,
            'reference' => $this->hold->reference,
            'action' => $this->action,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        return "Funds hold {$this->hold->reference} was {$this->action}.";
    }
}
