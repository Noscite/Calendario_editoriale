<?php

/**
 * Rotte API — URL identiche al backend Python FastAPI.
 *
 * Prefisso automatico /api (configurato in bootstrap/app.php → apiPrefix: 'api').
 * Il frontend React NON viene modificato: ogni URL qui
 * corrisponde 1:1 al router Python originale.
 *
 * Sorgenti di verità:
 *   - Frontend:  /var/www/noscite-calendar/frontend/src/services/api.js
 *   - Backend:   /var/www/noscite-calendar/backend/app/main.py  (prefissi)
 *   - Router:    /var/www/noscite-calendar/backend/app/api/routes/*.py
 */

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\ProspectAuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\AzureAuthController;
use App\Http\Controllers\Api\BrandApiKeyController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\SocialStatsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\HelpController;
use App\Http\Controllers\Api\SupportAgentController;
use App\Http\Controllers\Api\VoiceProfilingController;
use App\Http\Controllers\PublicApi\BrandApiController;
use App\Http\Controllers\PublicApi\ContextController;
use App\Http\Controllers\PublicApi\GenerationApiController;
use App\Http\Controllers\PublicApi\OpenApiController;
use App\Http\Controllers\PublicApi\PostApiController;
use App\Http\Controllers\PublicApi\ProjectApiController;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════
// STRIPE WEBHOOK — /api/webhooks/stripe  (no auth, no CSRF)
// ═══════════════════════════════════════════════════════════════

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth:sanctum', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// ═══════════════════════════════════════════════════════════════
// AUDIT PUBBLICO — /api/audit/share/{token}  (no auth)
// ═══════════════════════════════════════════════════════════════

Route::get('/audit/share/{token}', [ProspectAuditController::class, 'publicShow']);

// ═══════════════════════════════════════════════════════════════
// HEALTH — /api/health
// ═══════════════════════════════════════════════════════════════

Route::get('/health', fn () => response()->json([
    'status'  => 'healthy',
    'service' => 'noscite-calendar',
]));

// ═══════════════════════════════════════════════════════════════
// HELP CENTER — /api/help  (pubblico, no auth)
// ═══════════════════════════════════════════════════════════════

Route::prefix('help')->group(function () {
    Route::get('/categories', [HelpController::class, 'categories']);
    Route::get('/articles', [HelpController::class, 'articles']);
    Route::get('/articles/{slug}', [HelpController::class, 'article']);
    Route::get('/search', [HelpController::class, 'search']);
});

// ═══════════════════════════════════════════════════════════════
// AUTH — /api/auth  (prefix Python: /api/auth)
// ═══════════════════════════════════════════════════════════════
//
// Frontend:
//   api.post('/auth/login', URLSearchParams, 'x-www-form-urlencoded')
//   api.post('/auth/register', data)
//   api.get('/auth/me')
//   api.put('/auth/profile', data)
//   api.post('/auth/change-password')
//   api.post('/auth/refresh')

Route::prefix('auth')->group(function () {
    // Pubbliche (no auth)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,10');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,10');

    // Inviti — pubbliche
    Route::get('/invitation/{token}', [InvitationController::class, 'validateToken']);
    Route::post('/invitation/{token}/accept', [InvitationController::class, 'accept'])
        ->middleware('throttle:5,10');

    // Azure AD (Microsoft) OAuth — pubbliche
    Route::get('/azure/login', [AzureAuthController::class, 'login']);
    Route::get('/azure/callback', [AzureAuthController::class, 'callback']);

    // Protette
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// ═══════════════════════════════════════════════════════════════
// OAUTH CALLBACKS — /api/oauth  (prefix Python: /api/oauth)
// ═══════════════════════════════════════════════════════════════
//
// Redirect dal provider OAuth — PUBBLICHE (niente auth).
// Separate da /api/social/callback/ (social.py ha i suoi callback).

Route::prefix('oauth')->group(function () {
    Route::get('/facebook/callback', [OAuthController::class, 'callbackFacebook']);
    Route::get('/instagram/callback', [OAuthController::class, 'callbackInstagram']);
    Route::get('/linkedin/callback', [OAuthController::class, 'callbackLinkedin']);
    Route::get('/google/callback', [OAuthController::class, 'callbackGoogle']);
});

// ═══════════════════════════════════════════════════════════════
// SUBSCRIPTION PLANS — /api/subscriptions  (pubbliche)
// ═══════════════════════════════════════════════════════════════
//
// In Python: GET /plans e GET /plans/{id} NON richiedono auth.

Route::prefix('subscriptions')->group(function () {
    Route::get('/plans', [SubscriptionController::class, 'plans']);
    Route::get('/plans/{id}', [SubscriptionController::class, 'showPlan']);
});

// ═══════════════════════════════════════════════════════════════
// VOICE PROFILING — /api/voice-profiling  (prefix Python: /api + /voice-profiling)
// ═══════════════════════════════════════════════════════════════
//
// Tutti gli endpoint sono PUBBLICI nel backend Python.

Route::prefix('voice-profiling')->group(function () {
    Route::get('/check', [VoiceProfilingController::class, 'check']);
    Route::post('/brand/{brand_id}/init', [VoiceProfilingController::class, 'init']);
    Route::get('/brand/{brand_id}/profile', [VoiceProfilingController::class, 'profile']);
    Route::post('/brand/{brand_id}/save', [VoiceProfilingController::class, 'save']);
    // WebSocket: /api/voice-profiling/ws/{brand_id} — gestito da Reverb/canali
});

// ═══════════════════════════════════════════════════════════════
// SOCIAL — route PUBBLICHE (callbacks + token-based)
// ═══════════════════════════════════════════════════════════════
//
// In Python: callbacks e pagine token-based non richiedono auth.

Route::prefix('social')->group(function () {
    // OAuth callbacks (redirect dal provider)
    Route::get('/callback/facebook', [SocialController::class, 'callbackFacebook']);
    Route::get('/callback/instagram', [SocialController::class, 'callbackInstagram']);
    Route::get('/callback/linkedin', [SocialController::class, 'callbackLinkedin']);
    Route::get('/callback/google', [SocialController::class, 'callbackGoogle']);

    // Authorize — pubblica: il token JWT è nel query string, auth gestita nel controller
    Route::get('/authorize/{platform}', [SocialController::class, 'authorize']);

    // Token-based (il token dal flusso OAuth è nel path, non serve auth)
    Route::get('/facebook-pages/{token}', [SocialController::class, 'facebookPages']);
    Route::post('/facebook-pages/{token}/select', [SocialController::class, 'selectFacebookPage']);
    Route::get('/linkedin-orgs/{token}', [SocialController::class, 'linkedinOrgs']);
    Route::post('/linkedin-orgs/{token}/select', [SocialController::class, 'selectLinkedinOrg']);
    Route::get('/google-locations/{token}', [SocialController::class, 'googleLocations']);
    Route::post('/google-locations/{token}/select', [SocialController::class, 'selectGoogleLocation']);
});

// ═══════════════════════════════════════════════════════════════
// TUTTE LE ROTTE PROTETTE (auth:sanctum + subscription attiva)
// ═══════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'subscription.active'])->group(function () {

    // ─── BRANDS — /api/brands  (prefix Python: /api/brands) ────
    //
    // Frontend:
    //   api.get('/brands/')
    //   api.get('/brands/{id}')
    //   api.post('/brands/', data)
    //   api.put('/brands/{id}', data)
    //   api.delete('/brands/{id}')

    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']);
        Route::get('/{id}', [BrandController::class, 'show']);
        Route::post('/', [BrandController::class, 'store']);
        Route::put('/{id}', [BrandController::class, 'update']);
        Route::delete('/{id}', [BrandController::class, 'destroy']);

        // API Keys per brand
        Route::get('/{brand}/api-keys', [BrandApiKeyController::class, 'index']);
        Route::post('/{brand}/api-keys', [BrandApiKeyController::class, 'store']);

        // Audit digitale brand
        Route::post('/{id}/audit', [AuditController::class, 'start']);
        Route::get('/{id}/audit/latest', [AuditController::class, 'latest']);
        Route::get('/{id}/audits', [AuditController::class, 'history']);
        Route::post('/{brandId}/audit-detect-sector', [AuditController::class, 'detectSectorForBrand']);
    });

    // ─── AUDITS — /api/audits  (singolo audit per id) ───────────

    Route::prefix('audits')->group(function () {
        Route::get('/{auditId}',      [AuditController::class, 'show']);
        Route::get('/{auditId}/pdf',  [AuditController::class, 'downloadPdf']);
        Route::post('/{auditId}/share', [AuditController::class, 'generateShareLink']);
    });

    // ─── PROSPECT AUDITS — /api/audit/prospect ───────────────────

    Route::prefix('audit')->group(function () {
        Route::get('/prospects',            [ProspectAuditController::class, 'index']);
        Route::post('/prospect',            [ProspectAuditController::class, 'start']);
        Route::get('/prospect/{id}',        [ProspectAuditController::class, 'show']);
        Route::post('/prospect/{id}/share', [ProspectAuditController::class, 'generateShareLink']);
        Route::post('/prospect/{id}/rerun', [ProspectAuditController::class, 'rerun']);
        Route::delete('/prospect/{id}',     [ProspectAuditController::class, 'destroy']);
    });

    // ─── PROJECTS — /api/projects  (prefix Python: /api/projects)
    //
    // Frontend:
    //   api.get('/projects/', { params: { brand_id } })
    //   api.get('/projects/{id}')
    //   api.post('/projects/', data)
    //   api.put('/projects/{id}', data)
    //   api.delete('/projects/{id}')

    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::put('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);

        // Editions
        Route::post('/{id}/editions', [ProjectController::class, 'addEdition']);
        Route::get('/{id}/history-context', [ProjectController::class, 'historyContext']);
    });

    // ─── POSTS — /api/posts  (prefix Python: /api/posts) ──────
    //
    // Frontend:
    //   api.get('/posts/project/{projectId}')
    //   api.get('/posts/{id}')
    //   api.patch('/posts/{id}', data)
    //   api.delete('/posts/{id}')
    //   api.post('/posts/manual', data)
    //   api.post('/posts/generate-ai', data)
    //   api.post('/posts/batch-delete', data)
    //   api.post('/posts/{id}/generate-image', data)
    //   api.post('/posts/{id}/schedule', data)
    //   api.post('/posts/{id}/publish')

    Route::prefix('posts')->group(function () {
        // CRUD
        Route::get('/project/{project_id}', [PostController::class, 'byProject']);
        Route::get('/{id}', [PostController::class, 'show']);
        Route::put('/{id}', [PostController::class, 'update']);
        Route::patch('/{id}', [PostController::class, 'patch']);
        Route::delete('/{id}', [PostController::class, 'destroy']);

        // Creazione manuale e AI
        Route::post('/manual', [PostController::class, 'storeManual']);
        Route::post('/generate-ai', [PostController::class, 'generateAi']);

        // Batch
        Route::post('/batch-delete', [PostController::class, 'batchDelete']);
        Route::post('/batch-replace', [PostController::class, 'batchReplace']);

        // Azioni singolo post
        Route::post('/{id}/regenerate', [PostController::class, 'regenerate']);
        Route::post('/{id}/generate-image', [PostController::class, 'generateImage']);

        // Scheduling & Pubblicazione
        Route::post('/{id}/schedule', [PostController::class, 'schedule']);
        Route::delete('/{id}/schedule', [PostController::class, 'cancelSchedule']);
        Route::get('/{id}/schedule-status', [PostController::class, 'scheduleStatus']);
        Route::post('/{id}/publish', [PostController::class, 'publish']);

        // Media upload
        Route::post('/{id}/upload-media', [PostController::class, 'uploadMedia']);
        Route::post('/{id}/upload-image', [PostController::class, 'uploadImage']);

        // Carousel
        Route::post('/{id}/generate-carousel', [PostController::class, 'generateCarousel']);

        // Overlap check (duplicato anche in /api/generate — stessa logica Python)
        Route::post('/check-overlap/{project_id}', [PostController::class, 'checkOverlap']);
    });

    // ─── GENERATION — /api/generate  (prefix Python: /api/generate)
    //
    // Frontend:
    //   api.post('/generate/', data)
    //   api.post('/generate/personas/{projectId}')
    //   api.post('/generate/personas/{projectId}/confirm', { personas })
    //   api.delete('/generate/personas/{projectId}/{index}')
    //   api.post('/generate/personas/{projectId}/{index}/regenerate')
    //   api.post('/generate/personas/{projectId}/add')
    //   api.post('/generate/calendar/{projectId}')

    Route::prefix('generate')->group(function () {
        // Root POST (frontend: generation.generate)
        Route::post('/', [GenerationController::class, 'generate']);

        // Buyer Personas
        Route::post('/personas/{project_id}', [GenerationController::class, 'generatePersonas']);
        Route::post('/personas/{project_id}/regenerate', [GenerationController::class, 'regeneratePersonas']);
        Route::match(['put', 'post'], '/personas/{project_id}/confirm', [GenerationController::class, 'confirmPersonas']);
        Route::get('/personas/{project_id}', [GenerationController::class, 'getPersonas']);

        // Gestione singole personas
        Route::post('/personas/{project_id}/add', [GenerationController::class, 'addPersona']);
        Route::delete('/personas/{project_id}/{index}', [GenerationController::class, 'deletePersona']);
        Route::post('/personas/{project_id}/{index}/regenerate', [GenerationController::class, 'regenerateSinglePersona']);

        // Calendario
        Route::get('/preview/{project_id}', [GenerationController::class, 'preview']);
        Route::get('/preflight/{project_id}', [GenerationController::class, 'preflight']);
        Route::post('/calendar/{project_id}', [GenerationController::class, 'generateCalendar']);
        Route::get('/status/{project_id}', [GenerationController::class, 'status']);

        // Singolo post
        Route::post('/regenerate-post/{post_id}', [GenerationController::class, 'regeneratePost']);
        Route::post('/image-prompt/{post_id}', [GenerationController::class, 'imagePrompt']);

        // Overlap check
        Route::post('/check-overlap/{project_id}', [GenerationController::class, 'checkOverlap']);
    });

    // ─── SOCIAL — /api/social  (prefix Python: /api/social) ───
    //  Rotte PROTETTE (connections, authorize, disconnect)
    //
    // Frontend:
    //   api.get('/social/connections/{brandId}')
    //   api.delete('/social/disconnect/{connectionId}')
    //   api.get('/social/authorize/{platform}?brand_id={brandId}')

    Route::prefix('social')->group(function () {
        Route::get('/connections/{brand_id}', [SocialController::class, 'connections']);
        Route::delete('/disconnect/{connection_id}', [SocialController::class, 'disconnect']);

        // ─── SOCIAL STATS — /api/social/stats  (prefix Python: /api/social/stats)
        Route::prefix('stats')->group(function () {
            Route::get('/brand/{brand_id}', [SocialStatsController::class, 'brandStats']);
            Route::post('/fetch/{brand_id}', [SocialStatsController::class, 'fetchStats']);
            Route::get('/post/{publication_id}', [SocialStatsController::class, 'postStats']);
        });
    });

    // ─── DOCUMENTS — /api/documents  (prefix Python: /api/documents)
    //
    // Frontend:
    //   api.get('/documents/list/{brandId}')
    //   api.post('/documents/upload/{brandId}', formData)
    //   api.delete('/documents/{docId}')

    Route::prefix('documents')->group(function () {
        Route::post('/upload/{brand_id}', [DocumentController::class, 'upload']);
        Route::get('/list/{brand_id}', [DocumentController::class, 'index']);
        Route::get('/{id}', [DocumentController::class, 'show']);
        Route::delete('/{id}', [DocumentController::class, 'destroy']);
        Route::post('/search/{brand_id}', [DocumentController::class, 'search']);
        Route::post('/reprocess/{id}', [DocumentController::class, 'reprocess']);
    });

    // ─── EXPORT — /api/export  (prefix Python: /api/export) ───
    //
    // Frontend:
    //   api.get('/export/excel/{projectId}', blob)
    //   api.get('/export/pdf/{projectId}', blob)

    Route::prefix('export')->group(function () {
        Route::get('/excel/{project_id}', [ExportController::class, 'excel']);
        Route::get('/pdf/{project_id}', [ExportController::class, 'pdf']);
    });

    // ─── SUBSCRIPTIONS — /api/subscriptions  (protette) ────────
    //
    // Nota: GET /plans e GET /plans/{id} sono PUBBLICHE (sopra).
    //
    // Frontend:
    //   api.get('/subscriptions/current')
    //   api.post('/subscriptions/subscribe', { plan_id })
    //   api.post('/subscriptions/cancel')
    //   api.get('/subscriptions/usage/my')

    Route::prefix('subscriptions')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/usage/my', [SubscriptionController::class, 'myUsage']);

        // Admin / Superuser
        Route::put('/plans/{id}', [SubscriptionController::class, 'updatePlan']);
        Route::get('/organizations', [SubscriptionController::class, 'listOrganizations']);
        Route::post('/organizations', [SubscriptionController::class, 'createOrganization']);
        Route::put('/organizations/{org_id}', [SubscriptionController::class, 'updateOrganization']);
        Route::get('/usage/{org_id}', [SubscriptionController::class, 'organizationUsage']);
    });

    // ─── NOTIFICATIONS — /api/notifications  (prefix Python: /api + /notifications)

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
    });

    // ─── ADMIN — /api/admin  (prefix Python: /api/admin) ──────

    Route::prefix('admin')->group(function () {
        Route::get('/organizations', [AdminController::class, 'organizations']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::delete('/users/{id}/permanent', [AdminController::class, 'permanentDeleteUser']);
        Route::get('/activity', [AdminController::class, 'activity']);
        Route::get('/stats', [AdminController::class, 'stats']);

        // Inviti
        Route::post('/invitations', [InvitationController::class, 'invite']);
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::delete('/invitations/{id}', [InvitationController::class, 'revoke']);

        // Audit — vista cross-org (superadmin) + audit standalone su URL libero
        Route::get('/audits',                          [AuditController::class, 'adminIndex']);
        Route::post('/audit-url',                      [AuditController::class, 'startStandalone']);
        Route::get('/audits-standalone',               [AuditController::class, 'standaloneIndex']);
        Route::get('/audit-url/{auditId}/pdf',          [AuditController::class, 'standaloneDownloadPdf']);
        Route::post('/audit-url/{auditId}/share',      [AuditController::class, 'generateShareLink']);
        Route::post('/audit-detect-sector',            [AuditController::class, 'detectSector']);
    });

    // ─── API KEYS — /api/api-keys  (prefix Python: /api/api-keys) ─

    Route::prefix('api-keys')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index']);
        Route::post('/', [ApiKeyController::class, 'store']);
        Route::delete('/{key_id}', [ApiKeyController::class, 'destroy']);
    });

    // ─── SUPPORT AI CHAT — /api/support/chat ─────────────────────

    Route::post('/support/chat', [SupportAgentController::class, 'chat'])
        ->middleware('throttle:20,1');

    // ─── REVIEWS — /api/reviews (M3) ─────────────────────────────
    //
    // Lista paginata + dettaglio + bozze risposta AI + invio Google Business.
    // Multi-tenant via BelongsToOrganization (Review e ReviewReply).

    Route::prefix('reviews')->group(function () {
        Route::get('/quota', [ReviewController::class, 'quota']);
        Route::get('/', [ReviewController::class, 'index']);
        Route::get('/{id}', [ReviewController::class, 'show']);
        Route::post('/{id}/draft', [ReviewController::class, 'generateDraft']);
        Route::patch('/{id}/replies/{replyId}', [ReviewController::class, 'updateDraft']);
        Route::post('/{id}/replies/{replyId}/approve', [ReviewController::class, 'approve']);
        Route::post('/{id}/ignore', [ReviewController::class, 'ignore']);
    });
});

// ═══════════════════════════════════════════════════════════════
// PUBLIC API v1 — /api/v1  (X-API-Key authentication)
// ═══════════════════════════════════════════════════════════════
//
// Corrisponde a public_api.py (Python).
// Prefix Python: /api/v1
// Auth via header X-API-Key + scopes (read / write / publish).

Route::prefix('v1')->group(function () {

    // ── OpenAPI spec (no auth) ─────────────────────────────────
    Route::get('/openapi.json', OpenApiController::class);

    // ── /me — solo auth, nessuno scope specifico ───────────────
    Route::middleware('api.key')->group(function () {
        Route::get('/me', [ContextController::class, 'me']);
    });

    // ── READ endpoints — scope: read ───────────────────────────
    Route::middleware('api.key:read')->group(function () {
        Route::get('/context', [ContextController::class, 'context']);
        Route::get('/brands', [BrandApiController::class, 'index']);
        Route::get('/brands/{id}', [BrandApiController::class, 'show']);
        Route::get('/projects', [ProjectApiController::class, 'index']);
        Route::get('/projects/{id}', [ProjectApiController::class, 'show']);
        Route::get('/posts', [PostApiController::class, 'index']);
        Route::get('/posts/{id}', [PostApiController::class, 'show']);
    });

    // ── WRITE endpoints — scope: write ─────────────────────────
    Route::middleware('api.key:write')->group(function () {
        Route::post('/posts', [PostApiController::class, 'store']);
        Route::post('/posts/create', [PostApiController::class, 'createExplicit']);
        Route::post('/posts/bulk', [PostApiController::class, 'bulkCreate']);
        Route::patch('/posts/{id}', [PostApiController::class, 'update']);
        Route::delete('/posts/{id}', [PostApiController::class, 'destroy']);
        Route::post('/generate/calendar/{project_id}', [GenerationApiController::class, 'generateCalendar']);
    });

    // ── PUBLISH endpoints — scope: publish ─────────────────────
    Route::middleware('api.key:publish')->group(function () {
        Route::post('/posts/{id}/schedule', [PostApiController::class, 'schedule']);
        Route::post('/posts/{id}/publish', [PostApiController::class, 'publish']);
    });
});
