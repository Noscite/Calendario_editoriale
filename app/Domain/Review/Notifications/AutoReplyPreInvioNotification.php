<?php

declare(strict_types=1);

namespace App\Domain\Review\Notifications;

use App\Domain\Review\Models\ReviewReply;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AutoReplyPreInvioNotification extends Notification
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
        $delay  = (int) ($brand->auto_reply_delay_minutes ?? 30);
        $url    = rtrim((string) (config('services.frontend_url') ?? config('app.url')), '/')
            . '/reviews/' . $review->id;

        $reviewerName = $review->reviewer_name ?: 'cliente';
        $brandName    = $brand->name;
        $rating       = (int) $review->rating;

        return (new MailMessage())
            ->subject("[Kalendarium] Recensione di {$reviewerName} verrà gestita automaticamente")
            ->greeting('Ciao,')
            ->line("Stiamo per rispondere automaticamente alla recensione di **{$reviewerName}** ({$rating}★) sul brand **{$brandName}**.")
            ->line("La risposta verrà inviata tra **{$delay} minuti**.")
            ->line("Se vuoi modificare il testo o bloccare l'invio, clicca qui sotto:")
            ->action('Apri recensione', $url)
            ->line("Se non fai nulla, la risposta verrà inviata automaticamente.");
    }
}
