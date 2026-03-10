<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

/**
 * GET /api/v1/openapi.json — OpenAPI spec filtrata per Public API v1.
 *
 * Corrisponde a public_api.py → /openapi.json.
 * Restituisce la specifica OpenAPI 3.1.0 statica per gli endpoint pubblici.
 */
final class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $spec = [
            'openapi' => '3.1.0',
            'info'    => [
                'title'       => 'Noscite Calendar - Public API',
                'description' => 'API per integrazioni esterne: gestione calendario editoriale, post, brands e progetti. Autenticazione via header X-API-Key.',
                'version'     => '1.0.0',
            ],
            'servers' => [
                ['url' => config('app.url') . '/api/v1', 'description' => 'Public API v1'],
            ],
            'paths'      => $this->paths(),
            'components' => [
                'schemas'         => $this->schemas(),
                'securitySchemes' => [
                    'APIKeyHeader' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
            ],
        ];

        return response()->json($spec);
    }

    // ── Schemas ─────────────────────────────────────────────────

    private function schemas(): array
    {
        return [
            'BrandOut' => [
                'type'       => 'object',
                'properties' => [
                    'id'            => ['type' => 'integer'],
                    'name'          => ['type' => 'string'],
                    'sector'        => ['type' => 'string', 'nullable' => true],
                    'description'   => ['type' => 'string', 'nullable' => true],
                    'tone_of_voice' => ['type' => 'string', 'nullable' => true],
                    'website'       => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'ProjectOut' => [
                'type'       => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'brand_id'       => ['type' => 'integer'],
                    'name'           => ['type' => 'string'],
                    'start_date'     => ['type' => 'string', 'format' => 'date'],
                    'end_date'       => ['type' => 'string', 'format' => 'date'],
                    'platforms'      => ['type' => 'array', 'items' => ['type' => 'string']],
                    'posts_per_week' => ['type' => 'object'],
                    'status'         => ['type' => 'string'],
                ],
            ],
            'PostOut' => [
                'type'       => 'object',
                'properties' => [
                    'id'                 => ['type' => 'integer'],
                    'project_id'         => ['type' => 'integer'],
                    'platform'           => ['type' => 'string'],
                    'scheduled_date'     => ['type' => 'string', 'format' => 'date'],
                    'scheduled_time'     => ['type' => 'string'],
                    'title'              => ['type' => 'string', 'nullable' => true],
                    'content'            => ['type' => 'string'],
                    'hashtags'           => ['type' => 'array', 'items' => ['type' => 'string']],
                    'pillar'             => ['type' => 'string', 'nullable' => true],
                    'post_type'          => ['type' => 'string'],
                    'content_type'       => ['type' => 'string'],
                    'visual_suggestion'  => ['type' => 'string', 'nullable' => true],
                    'cta'                => ['type' => 'string', 'nullable' => true],
                    'call_to_action'     => ['type' => 'string', 'nullable' => true],
                    'image_url'          => ['type' => 'string', 'nullable' => true],
                    'is_carousel'        => ['type' => 'boolean'],
                    'status'             => ['type' => 'string'],
                    'publication_status' => ['type' => 'string', 'nullable' => true],
                    'created_at'         => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'PostCreateInput' => [
                'type'       => 'object',
                'required'   => ['project_id', 'platform', 'scheduled_date', 'content'],
                'properties' => [
                    'project_id'        => ['type' => 'integer', 'description' => 'ID del progetto'],
                    'platform'          => ['type' => 'string', 'description' => 'Piattaforma: instagram, linkedin, facebook, google_business'],
                    'scheduled_date'    => ['type' => 'string', 'format' => 'date', 'description' => 'Data pubblicazione (YYYY-MM-DD)'],
                    'scheduled_time'    => ['type' => 'string', 'description' => 'Ora pubblicazione (HH:MM)', 'default' => '09:00'],
                    'title'             => ['type' => 'string', 'description' => 'Titolo del post'],
                    'content'           => ['type' => 'string', 'description' => 'Testo del post'],
                    'hashtags'          => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Lista hashtag'],
                    'pillar'            => ['type' => 'string', 'description' => 'Pillar del contenuto'],
                    'post_type'         => ['type' => 'string', 'description' => 'Tipo: educational, promotional, engagement, storytelling', 'default' => 'educational'],
                    'content_type'      => ['type' => 'string', 'description' => 'Formato: post, story, reel', 'default' => 'post'],
                    'visual_suggestion' => ['type' => 'string', 'description' => 'Suggerimento visivo per immagine'],
                    'cta'               => ['type' => 'string', 'description' => 'Call to action'],
                    'call_to_action'    => ['type' => 'string', 'description' => 'Call to action estesa'],
                ],
            ],
            'PostUpdateInput' => [
                'type'       => 'object',
                'properties' => [
                    'platform'          => ['type' => 'string'],
                    'scheduled_date'    => ['type' => 'string', 'format' => 'date'],
                    'scheduled_time'    => ['type' => 'string'],
                    'title'             => ['type' => 'string'],
                    'content'           => ['type' => 'string'],
                    'hashtags'          => ['type' => 'array', 'items' => ['type' => 'string']],
                    'pillar'            => ['type' => 'string'],
                    'post_type'         => ['type' => 'string'],
                    'content_type'      => ['type' => 'string'],
                    'visual_suggestion' => ['type' => 'string'],
                    'cta'               => ['type' => 'string'],
                    'call_to_action'    => ['type' => 'string'],
                ],
            ],
            'BulkPostCreateInput' => [
                'type'       => 'object',
                'required'   => ['posts'],
                'properties' => [
                    'posts' => [
                        'type'  => 'array',
                        'items' => ['$ref' => '#/components/schemas/PostCreateInput'],
                    ],
                ],
            ],
        ];
    }

    // ── Paths ───────────────────────────────────────────────────

    private function paths(): array
    {
        return [
            '/me' => [
                'get' => [
                    'summary'   => 'Info utente e organizzazione',
                    'tags'      => ['Context'],
                    'security'  => [['APIKeyHeader' => []]],
                    'responses' => ['200' => ['description' => 'Info utente e API key']],
                ],
            ],
            '/context' => [
                'get' => [
                    'summary'   => 'Contesto utente: brands, progetti attivi',
                    'tags'      => ['Context'],
                    'security'  => [['APIKeyHeader' => []]],
                    'responses' => ['200' => ['description' => 'Brands e progetti con default suggeriti']],
                ],
            ],
            '/brands' => [
                'get' => [
                    'summary'   => 'Lista brand',
                    'tags'      => ['Brands'],
                    'security'  => [['APIKeyHeader' => []]],
                    'responses' => ['200' => ['description' => 'Array di BrandOut', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/BrandOut']]]]]],
                ],
            ],
            '/brands/{id}' => [
                'get' => [
                    'summary'    => 'Dettaglio brand',
                    'tags'       => ['Brands'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'BrandOut', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/BrandOut']]]]],
                ],
            ],
            '/projects' => [
                'get' => [
                    'summary'    => 'Lista progetti',
                    'tags'       => ['Projects'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [
                        ['name' => 'brand_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'Array di ProjectOut']],
                ],
            ],
            '/projects/{id}' => [
                'get' => [
                    'summary'    => 'Dettaglio progetto',
                    'tags'       => ['Projects'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'ProjectOut']],
                ],
            ],
            '/posts' => [
                'get' => [
                    'summary'    => 'Lista post con filtri',
                    'tags'       => ['Posts'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [
                        ['name' => 'project_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ['name' => 'brand_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ['name' => 'platform', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'date_from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 200]],
                        ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => ['200' => ['description' => 'Array di PostOut']],
                ],
                'post' => [
                    'summary'     => 'Crea post nel calendario',
                    'tags'        => ['Posts'],
                    'security'    => [['APIKeyHeader' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostCreateInput']]]],
                    'responses'   => ['201' => ['description' => 'Post creato', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostOut']]]]],
                ],
            ],
            '/posts/create' => [
                'post' => [
                    'summary'     => 'Crea post (parametri espliciti per MCP)',
                    'tags'        => ['Posts'],
                    'security'    => [['APIKeyHeader' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostCreateInput']]]],
                    'responses'   => ['201' => ['description' => 'Post creato']],
                ],
            ],
            '/posts/bulk' => [
                'post' => [
                    'summary'     => 'Crea post multipli',
                    'tags'        => ['Posts'],
                    'security'    => [['APIKeyHeader' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/BulkPostCreateInput']]]],
                    'responses'   => ['201' => ['description' => 'Post creati con conteggio e ID']],
                ],
            ],
            '/posts/{id}' => [
                'get' => [
                    'summary'    => 'Dettaglio post',
                    'tags'       => ['Posts'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'PostOut']],
                ],
                'patch' => [
                    'summary'     => 'Aggiorna post',
                    'tags'        => ['Posts'],
                    'security'    => [['APIKeyHeader' => []]],
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostUpdateInput']]]],
                    'responses'   => ['200' => ['description' => 'PostOut aggiornato']],
                ],
                'delete' => [
                    'summary'    => 'Elimina post',
                    'tags'       => ['Posts'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'Conferma eliminazione']],
                ],
            ],
            '/posts/{id}/schedule' => [
                'post' => [
                    'summary'    => 'Schedula post per pubblicazione',
                    'tags'       => ['Publication'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'platforms', 'in' => 'query', 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
                    ],
                    'responses' => ['200' => ['description' => 'Piattaforme schedulate']],
                ],
            ],
            '/posts/{id}/publish' => [
                'post' => [
                    'summary'    => 'Pubblica post immediatamente',
                    'tags'       => ['Publication'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'Risultato pubblicazione']],
                ],
            ],
            '/generate/calendar/{project_id}' => [
                'post' => [
                    'summary'    => 'Genera calendario con AI',
                    'tags'       => ['Generation'],
                    'security'   => [['APIKeyHeader' => []]],
                    'parameters' => [['name' => 'project_id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'Generazione avviata']],
                ],
            ],
        ];
    }
}
