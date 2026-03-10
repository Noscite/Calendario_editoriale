<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\Brand\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Piani, organizzazioni SaaS e usage tracking — corrisponde a subscriptions.py (Python).
 */
final class SubscriptionController extends Controller
{
    // GET /api/subscriptions/plans
    public function plans(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive', false);

        $query = Plan::query();
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $plans = $query->orderBy('price_monthly')->get();

        return response()->json($plans);
    }

    // GET /api/subscriptions/plans/{id}
    public function showPlan(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        return response()->json($plan);
    }

    // GET /api/subscriptions/current
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->organization_id) {
            return response()->json(['detail' => 'Utente non associato a un\'organizzazione'], 400);
        }

        $org  = Organization::findOrFail($user->organization_id);
        $plan = $org->plan_id ? Plan::find($org->plan_id) : null;

        return response()->json([
            'organization_id'     => $org->id,
            'plan_id'             => $org->plan_id,
            'plan_name'           => $plan?->name,
            'subscription_status' => $org->subscription_status?->value ?? 'trial',
            'trial_ends_at'       => $org->trial_ends_at?->toIso8601String(),
            'subscription_starts_at' => $org->subscription_starts_at?->toIso8601String(),
            'subscription_ends_at'   => $org->subscription_ends_at?->toIso8601String(),
        ]);
    }

    // POST /api/subscriptions/subscribe
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
        ]);

        $user = $request->user();
        if (! $user->organization_id) {
            return response()->json(['detail' => 'Utente non associato a un\'organizzazione'], 400);
        }

        $org = Organization::findOrFail($user->organization_id);
        $org->plan_id = $data['plan_id'];
        $org->subscription_status = 'active';
        $org->subscription_starts_at = now();
        $org->save();

        return response()->json(['message' => 'Sottoscrizione attivata']);
    }

    // POST /api/subscriptions/cancel
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->organization_id) {
            return response()->json(['detail' => 'Utente non associato a un\'organizzazione'], 400);
        }

        $org = Organization::findOrFail($user->organization_id);
        $org->subscription_status = 'cancelled';
        $org->subscription_ends_at = now();
        $org->save();

        return response()->json(['message' => 'Sottoscrizione cancellata']);
    }

    // GET /api/subscriptions/usage/my
    public function myUsage(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->organization_id) {
            return response()->json(['detail' => 'Utente non associato a un\'organizzazione'], 400);
        }

        $org  = Organization::findOrFail($user->organization_id);
        $plan = $org->plan_id ? Plan::find($org->plan_id) : null;

        $limits = $this->getEffectiveLimits($org, $plan);

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd   = now()->endOfMonth()->toDateString();

        $usage = UsageLog::where('organization_id', $user->organization_id)
            ->where('period_start', $periodStart)
            ->first();

        if (! $usage) {
            $usage = UsageLog::create([
                'organization_id' => $user->organization_id,
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
            ]);
        }

        $daysRemaining = max(0, now()->endOfMonth()->diffInDays(now()) + 1);

        return response()->json([
            'calendars_used'       => $usage->calendar_generations_used ?? 0,
            'calendars_limit'      => $limits['monthly_calendars'],
            'calendars_percentage' => $this->calcPercentage($usage->calendar_generations_used ?? 0, $limits['monthly_calendars']),
            'tokens_used'          => $usage->text_tokens_used ?? 0,
            'tokens_limit'         => $limits['monthly_tokens'],
            'tokens_percentage'    => $this->calcPercentage($usage->text_tokens_used ?? 0, $limits['monthly_tokens']),
            'images_used'          => $usage->images_generated ?? 0,
            'images_limit'         => $limits['monthly_images'],
            'images_percentage'    => $this->calcPercentage($usage->images_generated ?? 0, $limits['monthly_images']),
            'days_remaining'       => $daysRemaining,
            'overage_cost'         => $usage->overage_cost ?? '0.00',
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function getEffectiveLimits(Organization $org, ?Plan $plan): array
    {
        if ($org->custom_limits) {
            return [
                'max_brands'         => $org->custom_limits['max_brands'] ?? ($plan?->max_brands ?? 1),
                'max_users'          => $org->custom_limits['max_users'] ?? ($plan?->max_users ?? 1),
                'monthly_calendars'  => $org->custom_limits['monthly_calendar_generations'] ?? ($plan?->monthly_calendar_generations ?? 3),
                'monthly_tokens'     => $org->custom_limits['monthly_text_tokens'] ?? ($plan?->monthly_text_tokens ?? 50000),
                'monthly_images'     => $org->custom_limits['monthly_images'] ?? ($plan?->monthly_images ?? 20),
            ];
        }

        if ($plan) {
            return [
                'max_brands'        => $plan->max_brands,
                'max_users'         => $plan->max_users,
                'monthly_calendars' => $plan->monthly_calendar_generations,
                'monthly_tokens'    => $plan->monthly_text_tokens,
                'monthly_images'    => $plan->monthly_images,
            ];
        }

        return [
            'max_brands'        => 1,
            'max_users'         => 1,
            'monthly_calendars' => 3,
            'monthly_tokens'    => 50000,
            'monthly_images'    => 20,
        ];
    }

    private function calcPercentage(int $used, int $limit): float
    {
        if ($limit === -1) {
            return 0;
        }

        return $limit > 0
            ? min(100, round(($used / $limit) * 100, 1))
            : 0;
    }

    // ── Admin / Superuser endpoints ────────────────────────────

    // PUT /api/subscriptions/plans/{id}
    public function updatePlan(int $id, Request $request): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $data = $request->validate([
            'name'                         => 'sometimes|string|max:255',
            'price_monthly'                => 'sometimes|numeric',
            'price_yearly'                 => 'sometimes|numeric',
            'max_brands'                   => 'sometimes|integer',
            'max_users'                    => 'sometimes|integer',
            'monthly_calendar_generations' => 'sometimes|integer',
            'monthly_text_tokens'          => 'sometimes|integer',
            'monthly_images'               => 'sometimes|integer',
            'is_active'                    => 'sometimes|boolean',
        ]);

        $plan->update($data);

        return response()->json($plan->fresh());
    }

    // GET /api/subscriptions/organizations
    public function listOrganizations(Request $request): JsonResponse
    {
        $orgs = Organization::with('plan')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Organization $o) => [
                'id'                  => $o->id,
                'name'                => $o->name,
                'slug'                => $o->slug,
                'plan_id'             => $o->plan_id,
                'plan_name'           => $o->plan?->name,
                'subscription_status' => $o->subscription_status?->value ?? 'trial',
                'is_active'           => $o->is_active,
                'created_at'          => $o->created_at?->toIso8601String(),
            ]);

        return response()->json($orgs);
    }

    // POST /api/subscriptions/organizations
    public function createOrganization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255|unique:organizations,slug',
            'email'         => 'nullable|email',
            'plan_id'       => 'nullable|integer|exists:plans,id',
            'custom_limits' => 'nullable|array',
        ]);

        $org = Organization::create($data);

        return response()->json($org, 201);
    }

    // PUT /api/subscriptions/organizations/{org_id}
    public function updateOrganization(int $orgId, Request $request): JsonResponse
    {
        $org = Organization::findOrFail($orgId);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'plan_id'             => 'sometimes|integer|exists:plans,id',
            'subscription_status' => 'sometimes|string',
            'is_active'           => 'sometimes|boolean',
            'custom_limits'       => 'sometimes|nullable|array',
        ]);

        $org->update($data);

        return response()->json($org->fresh());
    }

    // GET /api/subscriptions/usage/{org_id}
    public function organizationUsage(int $orgId, Request $request): JsonResponse
    {
        $org  = Organization::findOrFail($orgId);
        $plan = $org->plan_id ? Plan::find($org->plan_id) : null;

        $limits      = $this->getEffectiveLimits($org, $plan);
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd   = now()->endOfMonth()->toDateString();

        $usage = UsageLog::where('organization_id', $orgId)
            ->where('period_start', $periodStart)
            ->first();

        return response()->json([
            'organization_id'  => $orgId,
            'calendars_used'   => $usage->calendar_generations_used ?? 0,
            'calendars_limit'  => $limits['monthly_calendars'],
            'tokens_used'      => $usage->text_tokens_used ?? 0,
            'tokens_limit'     => $limits['monthly_tokens'],
            'images_used'      => $usage->images_generated ?? 0,
            'images_limit'     => $limits['monthly_images'],
        ]);
    }
}
