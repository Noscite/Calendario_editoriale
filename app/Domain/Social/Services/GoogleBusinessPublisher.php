<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Post\Models\Post;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publisher per Google Business Profile (GBP).
 *
 * Implementa la pubblicazione di Local Posts tramite
 * Google Business Profile API v4.
 *
 * Segue lo stesso pattern di LinkedInPublisher e FacebookPublisher.
 *
 * Endpoint: POST https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/localPosts
 *
 * Tipi di post supportati:
 *   - STANDARD  → post generico (testo + immagine opzionale)
 *   - EVENT     → post evento (richiede titolo + date/ora inizio e fine)
 */
final class GoogleBusinessPublisher
{
    private const API_BASE = 'https://mybusiness.googleapis.com/v4';

    /**
     * Pubblica un post su Google Business Profile.
     *
     * @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string}
     *
     * @throws TokenExpiredException se l'API risponde 401
     */
    private const ACCOUNT_TYPE_LITERALS = ['profile', 'page', 'organization', 'business', 'location'];

    public function publish(Post $post, SocialConnection $connection): array
    {
        try {
            $accountId  = (string) $connection->external_account_id;
            $locationId = (string) ($connection->account_type ?? '');

            // L'external_account_id può avere il formato "accounts/{id}/locations/{locationId}"
            // oppure solo "{accountId}" con location in account_type.
            if (str_starts_with($accountId, 'accounts/') && str_contains($accountId, '/locations/')) {
                $endpoint = self::API_BASE . "/{$accountId}/localPosts";
            } elseif (str_starts_with($accountId, 'locations/')) {
                $msg = 'Connessione GBP malconfigurata: external_account_id manca il prefisso "accounts/{accountId}/". Riconnettere il brand.';
                Log::error('[GBP] ' . $msg, [
                    'post_id'             => $post->id,
                    'connection_id'       => $connection->id,
                    'external_account_id' => $accountId,
                ]);
                return ['success' => false, 'error' => $msg];
            } elseif (in_array($locationId, self::ACCOUNT_TYPE_LITERALS, true)) {
                $msg = 'Connessione GBP malconfigurata: account_type contiene un literal ("' . $locationId . '") invece del location ID numerico. Riconnettere il brand.';
                Log::error('[GBP] ' . $msg, [
                    'post_id'       => $post->id,
                    'connection_id' => $connection->id,
                    'account_type'  => $locationId,
                ]);
                return ['success' => false, 'error' => $msg];
            } else {
                $endpoint = self::API_BASE . "/accounts/{$accountId}/locations/{$locationId}/localPosts";
            }

            $payload = $this->buildPayload($post);

            Log::info('[GBP] Pubblicazione post', [
                'post_id'    => $post->id,
                'endpoint'   => $endpoint,
                'topic_type' => $payload['topicType'],
            ]);

            $response = Http::withToken($connection->access_token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, $payload);

            if ($response->status() === 401) {
                Log::warning('[GBP] Token scaduto', ['post_id' => $post->id]);
                throw new TokenExpiredException('google_business');
            }

            if ($response->successful()) {
                $data      = $response->json();
                $postName  = $data['name'] ?? '';
                $searchUrl = $data['searchUrl'] ?? '';

                Log::info('[GBP] Post pubblicato con successo', [
                    'post_id'   => $post->id,
                    'post_name' => $postName,
                ]);

                return [
                    'success'           => true,
                    'external_post_id'  => $postName,
                    'external_post_url' => $searchUrl,
                ];
            }

            $error = $response->json('error.message', $response->body());

            Log::error('[GBP] Pubblicazione fallita', [
                'post_id' => $post->id,
                'status'  => $response->status(),
                'error'   => $error,
            ]);

            return ['success' => false, 'error' => "GBP API error: {$error}"];

        } catch (TokenExpiredException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[GBP] Eccezione durante la pubblicazione', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Costruisce il payload per l'API GBP in base al tipo di post.
     * Mappa post_type → topicType GBP (STANDARD o EVENT).
     */
    private function buildPayload(Post $post): array
    {
        // I post_type dell'applicazione sono tutti post di contenuto, non eventi datati.
        // Mapparli a EVENT GBP richiederebbe schedule.start/endDate future valide.
        $eventTypes = [];
        $topicType  = in_array($post->post_type, $eventTypes, true) ? 'EVENT' : 'STANDARD';

        $content = $this->buildContent($post);

        $payload = [
            'topicType' => $topicType,
            'summary'   => $content,
        ];

        // Aggiungi callToAction se disponibile
        if ($post->call_to_action || $post->cta) {
            $payload['callToAction'] = [
                'actionType' => 'LEARN_MORE',
                'url'        => config('services.frontend_url'),
            ];
        }

        // Aggiungi immagine se disponibile
        if ($post->image_url) {
            $payload['media'] = [
                [
                    'mediaFormat' => 'PHOTO',
                    'sourceUrl'   => $this->makeAbsoluteUrl($post->image_url),
                ],
            ];
        }

        // Per eventi aggiungi titolo e finestra temporale (evento di 1 giorno)
        if ($topicType === 'EVENT') {
            $eventDate = $post->scheduled_date ?? now();
            $payload['event'] = [
                'title'    => $post->title ?? ($post->content ? mb_substr($post->content, 0, 58) : 'Evento'),
                'schedule' => [
                    'startDate' => [
                        'year'  => (int) $eventDate->format('Y'),
                        'month' => (int) $eventDate->format('m'),
                        'day'   => (int) $eventDate->format('d'),
                    ],
                    'endDate' => [
                        'year'  => (int) $eventDate->format('Y'),
                        'month' => (int) $eventDate->format('m'),
                        'day'   => (int) $eventDate->format('d'),
                    ],
                ],
            ];
        }

        return $payload;
    }

    /**
     * Costruisce il testo del post con hashtags (come LinkedIn/Facebook).
     */
    private function buildContent(Post $post): string
    {
        $content  = $post->content ?? '';
        $hashtags = $post->hashtags ?? [];

        if (!empty($hashtags)) {
            $hashtagString = collect($hashtags)
                ->map(fn (string $h) => str_starts_with($h, '#') ? $h : "#{$h}")
                ->implode(' ');
            $content .= "\n\n{$hashtagString}";
        }

        // GBP ha un limite di 1500 caratteri per il summary
        return mb_substr($content, 0, 1500);
    }

    /**
     * Converti URL relativi in assoluti (replica di make_absolute_url Python).
     */
    private function makeAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/' . ltrim($url, '/');
    }
}
