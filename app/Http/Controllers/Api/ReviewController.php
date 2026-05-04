<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Services\ReviewReplyGenerator;
use App\Domain\Subscription\Services\ReplyQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * API per recensioni Google + bozze risposta + invio.
 *
 * Tutte le route sotto auth:sanctum + subscription.active.
 * Multi-tenancy: BelongsToOrganization scopa già Review e ReviewReply per
 * organization_id dell'utente loggato.
 */
final class ReviewController extends Controller
{
    public function __construct(
        private readonly ReplyQuotaService $quotaService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'                => ['nullable', Rule::in(array_column(ReviewStatus::cases(), 'value'))],
            'sentiment'             => ['nullable', 'string'],
            'urgency'               => ['nullable', 'string'],
            'marketing_opportunity' => ['nullable', 'string'],
            'brand_id'              => ['nullable', 'integer'],
            'search'                => ['nullable', 'string', 'max:200'],
            'per_page'              => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Review::query()
            ->with(['brand:id,name', 'latestReply', 'sentReply', 'activeDraft']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['sentiment'])) {
            $query->where('sentiment', $validated['sentiment']);
        }
        if (! empty($validated['urgency'])) {
            $query->where('urgency', $validated['urgency']);
        }
        if (! empty($validated['marketing_opportunity'])) {
            $query->where('marketing_opportunity', $validated['marketing_opportunity']);
        }
        if (! empty($validated['brand_id'])) {
            $query->where('brand_id', (int) $validated['brand_id']);
        }
        if (! empty($validated['search'])) {
            $like = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('comment', 'ILIKE', $like)
                    ->orWhere('reviewer_name', 'ILIKE', $like);
            });
        }

        $page = $query->orderByDesc('review_created_at')->paginate($perPage);

        $page->getCollection()->transform(fn (Review $r) => $this->serializeListItem($r));

        return response()->json($page);
    }

    public function show(int $id): JsonResponse
    {
        $review = Review::query()
            ->with(['brand', 'replies' => fn ($q) => $q->orderByDesc('id')])
            ->findOrFail($id);

        return response()->json($this->serializeDetail($review));
    }

    public function generateDraft(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tone'              => ['nullable', Rule::in(array_column(ReplyTone::cases(), 'value'))],
            'strategy_override' => ['nullable', 'string', 'max:30'],
        ]);

        $review = Review::query()->findOrFail($id);

        if (! $this->quotaService->isFeatureEnabled($review->organization)) {
            return response()->json([
                'error'   => 'feature_disabled',
                'message' => 'La generazione di risposte AI non è inclusa nel piano attuale.',
            ], 403);
        }

        $tone = isset($validated['tone'])
            ? ReplyTone::from($validated['tone'])
            : ReplyTone::BrandDefault;

        // Marca bozze precedenti come superseded (versionamento)
        ReviewReply::query()
            ->where('review_id', $review->id)
            ->where('status', ReplyStatus::Draft->value)
            ->update(['status' => ReplyStatus::Superseded->value]);

        try {
            /** @var ReviewReplyGenerator $generator */
            $generator = app(ReviewReplyGenerator::class);
            $result    = $generator->generate(
                review: $review,
                tone: $tone,
                marketingStrategyOverride: $validated['strategy_override'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('[REVIEW_API] Generazione bozza fallita', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
            return response()->json([
                'error'   => 'generation_failed',
                'message' => 'Generazione lenta o fallita. Riprova fra poco.',
            ], 504);
        }

        $reply = ReviewReply::create([
            'organization_id'    => $review->organization_id,
            'review_id'          => $review->id,
            'status'             => ReplyStatus::Draft->value,
            'body'               => $result['body'],
            'tone_used'          => $result['tone_used'],
            'marketing_strategy' => $result['marketing_strategy'],
            'kb_chunks_used'     => $result['kb_chunks_used'],
            'generated_by_model' => $result['generated_by_model'],
            'input_tokens'       => $result['input_tokens'],
            'output_tokens'      => $result['output_tokens'],
            'generated_at'       => now(),
        ]);

        if ($review->status === ReviewStatus::Scored || $review->status === ReviewStatus::New) {
            $review->update(['status' => ReviewStatus::Drafted->value]);
        }

        return response()->json($this->serializeReply($reply), 201);
    }

    public function updateDraft(int $id, int $replyId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $reply = ReviewReply::query()
            ->where('review_id', $id)
            ->findOrFail($replyId);

        if ($reply->status !== ReplyStatus::Draft) {
            return response()->json([
                'error'   => 'invalid_status',
                'message' => 'La bozza è già stata approvata o inviata: non è più editabile.',
            ], 422);
        }

        $update = ['body' => $validated['body'], 'was_edited' => true];
        if ($reply->original_body === null) {
            $update['original_body'] = $reply->body;
        }
        $reply->update($update);

        return response()->json($this->serializeReply($reply->fresh()));
    }

    public function approve(int $id, int $replyId): JsonResponse
    {
        $review = Review::query()->findOrFail($id);
        $reply  = ReviewReply::query()
            ->where('review_id', $id)
            ->findOrFail($replyId);

        if ($reply->status !== ReplyStatus::Draft) {
            return response()->json([
                'error'   => 'invalid_status',
                'message' => 'Solo le bozze possono essere approvate.',
            ], 422);
        }

        if (! $this->quotaService->canSendReply($review->organization)) {
            $featureEnabled = $this->quotaService->isFeatureEnabled($review->organization);
            return response()->json([
                'error'   => $featureEnabled ? 'quota_exceeded' : 'feature_disabled',
                'message' => $featureEnabled
                    ? 'Limite mensile risposte raggiunto. Aggiorna il piano per continuare.'
                    : 'Funzione non disponibile sul tuo piano.',
            ], 403);
        }

        $reply->update([
            'status'              => ReplyStatus::Approved->value,
            'approved_by_user_id' => Auth::id(),
            'approved_at'         => now(),
        ]);

        SendReplyJob::dispatch($reply->id);

        return response()->json($this->serializeReply($reply->fresh()), 202);
    }

    public function ignore(int $id): JsonResponse
    {
        $review = Review::query()->findOrFail($id);
        $review->update(['status' => ReviewStatus::Ignored->value]);

        return response()->json(['message' => 'Recensione ignorata.', 'id' => $review->id]);
    }

    public function quota(): JsonResponse
    {
        $org = Auth::user()?->organization;
        if (! $org) {
            return response()->json(['error' => 'no_organization'], 422);
        }

        return response()->json($this->quotaService->summary($org));
    }

    // ─── Serialization helpers ────────────────────────────────────

    private function serializeListItem(Review $r): array
    {
        $activeDraft = $r->activeDraft;
        $sentReply   = $r->sentReply;

        return [
            'id'                    => $r->id,
            'rating'                => $r->rating,
            'reviewer_name'         => $r->reviewer_name,
            'reviewer_photo_url'    => $r->reviewer_photo_url,
            'comment'               => $r->comment,
            'review_created_at'     => $r->review_created_at?->toIso8601String(),
            'status'                => $r->status?->value,
            'sentiment'             => $r->sentiment?->value,
            'urgency'               => $r->urgency?->value,
            'topics'                => $r->topics ?? [],
            'marketing_opportunity' => $r->marketing_opportunity?->value,
            'is_fake_suspect'       => (bool) $r->is_fake_suspect,
            'brand'                 => $r->brand ? ['id' => $r->brand->id, 'name' => $r->brand->name] : null,
            'has_active_draft'      => $activeDraft !== null,
            'active_draft_id'       => $activeDraft?->id,
            'has_sent_reply'        => $sentReply !== null,
            'sent_reply_id'         => $sentReply?->id,
        ];
    }

    private function serializeDetail(Review $r): array
    {
        $base = $this->serializeListItem($r);
        $base['scoring_rationale'] = $r->scoring_rationale;
        $base['scored_at']         = $r->scored_at?->toIso8601String();
        $base['scored_by_model']   = $r->scored_by_model;
        $base['external_review_id'] = $r->external_review_id;
        $base['language']          = $r->language;
        $base['brand']             = $r->brand ? [
            'id'              => $r->brand->id,
            'name'            => $r->brand->name,
            'sector'          => $r->brand->sector,
            'review_ontology' => is_array($r->brand->review_ontology) ? $r->brand->review_ontology : [],
        ] : null;
        $base['replies'] = $r->replies->map(fn (ReviewReply $rep) => $this->serializeReply($rep))->values();

        return $base;
    }

    private function serializeReply(ReviewReply $rep): array
    {
        return [
            'id'                  => $rep->id,
            'review_id'           => $rep->review_id,
            'status'              => $rep->status?->value,
            'body'                => $rep->body,
            'original_body'       => $rep->original_body,
            'tone_used'           => $rep->tone_used?->value,
            'marketing_strategy'  => $rep->marketing_strategy,
            'kb_chunks_used'      => $rep->kb_chunks_used ?? [],
            'generated_by_model'  => $rep->generated_by_model,
            'generated_at'        => $rep->generated_at?->toIso8601String(),
            'approved_at'         => $rep->approved_at?->toIso8601String(),
            'sent_at'             => $rep->sent_at?->toIso8601String(),
            'was_edited'          => (bool) $rep->was_edited,
            'external_reply_id'   => $rep->external_reply_id,
            'error_message'       => $rep->error_message,
        ];
    }
}
