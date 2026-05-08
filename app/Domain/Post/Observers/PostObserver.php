<?php

declare(strict_types=1);

namespace App\Domain\Post\Observers;

use App\Domain\Post\Models\Post;
use App\Domain\Post\Models\PostEditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Observer che traccia le modifiche manuali su post AI-generated.
 * Salva un PostEditLog per ogni cambio dei campi rilevanti, escludendo
 * il momento di creazione iniziale (è la generazione AI, non un edit umano).
 */
final class PostObserver
{
    private const TRACKED_FIELDS = [
        'content',
        'hashtags',
        'visual_suggestion',
        'call_to_action',
    ];

    public function updating(Post $post): void
    {
        // Salta i log se il post non ha generation_metadata
        // (significa che non è stato AI-generated, non ci interessa)
        if (empty($post->generation_metadata)) {
            return;
        }

        // Salta se è un cambio di status (es. draft → published)
        // Tracciamo solo le modifiche al contenuto editoriale
        $userId = Auth::id();

        foreach (self::TRACKED_FIELDS as $field) {
            if (!$post->isDirty($field)) {
                continue;
            }

            $original = $post->getOriginal($field);
            $new = $post->getAttribute($field);

            // Per array (es. hashtags) confronta serialized
            $originalSerialized = is_array($original) ? json_encode($original) : (string) $original;
            $newSerialized = is_array($new) ? json_encode($new) : (string) $new;

            if ($originalSerialized === $newSerialized) {
                continue;
            }

            PostEditLog::create([
                'post_id' => $post->id,
                'organization_id' => $post->organization_id,
                'user_id' => $userId,
                'field_name' => $field,
                'original_value' => $originalSerialized,
                'new_value' => $newSerialized,
                'edit_reason' => null,
            ]);
        }
    }
}
