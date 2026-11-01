<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing('community');

        return (new MailMessage)
            ->subject("Receipt: {$payment->community->name}")
            ->greeting('Hi '.strtok($notifiable->name, ' ').',')
            ->line("Thanks for your payment to {$payment->community->name}.")
            ->line('Amount: '.Money::format($payment->amount, $payment->currency))
            ->line('Item: '.($payment->description ?: 'Membership'))
            ->line('Date: '.$payment->paid_at->toFormattedDateString())
            ->line('Reference: '.$payment->gateway_payment_id)
            ->action('Manage billing', route('billing.index'));
    }
}
