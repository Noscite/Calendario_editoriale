<?php

declare(strict_types=1);

namespace App\Domain\Review\Notifications;

use App\Domain\Review\Models\ReviewReply;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AutoReplyPostInvioNotification extends Notification
{
    public function __construct(
        public readonly ReviewReply $reply,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $review = $this->reply->review;
        $brand  = $review->brand;
        $url    = rtrim((string) (config('services.frontend_url') ?? config('app.url')), '/')
            . '/reviews/' . $review->id;

        $reviewerName = $review->reviewer_name ?: 'cliente';
        $brandName    = $brand->name;
        $rating       = (int) $review->rating;

        return (new MailMessage())
            ->subject("[Kalendarium] Risposta automatica inviata a {$reviewerName}")
            ->greeting('Ciao,')
            ->line("Abbiamo risposto automaticamente alla recensione di **{$reviewerName}** ({$rating}★) sul brand **{$brandName}**.")
            ->line('Visualizza il testo della risposta:')
            ->action('Apri recensione', $url);
    }
}
