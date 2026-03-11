<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\ActivityLog;
use App\Domain\Organization\Models\Organization;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * Pannello admin — corrisponde a admin.py (Python).
 *
 * Richiede ruolo admin o superuser.
 */
final class AdminController extends Controller
{
    private const VALID_ROLES = ['superuser', 'admin', 'editor', 'viewer'];

    // ─── Organizations (solo superuser) ─────────────────────────

    // GET /api/admin/organizations
    public function organizations(Request $request): JsonResponse
    {
        $this->requireSuperuser($request);

        $orgs = Organization::all()->map(function ($org) {
            $userCount = User::where('organization_id', $org->id)->count();

            return [
                'id'         => $org->id,
                'name'       => $org->name,
                'slug'       => $org->slug,
                'created_at' => $org->created_at?->toIso8601String(),
                'user_count' => $userCount,
            ];
        });

        return response()->json($orgs);
    }

    // ─── User Management (admin+) ──────────────────────────────

    // GET /api/admin/users
    public function users(Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);

        $query = User::query();

        if ($currentUser->role === 'superuser') {
            $orgFilter = $request->query('organization_id');
            if ($orgFilter) {
                $query->where('organization_id', (int) $orgFilter);
            }
        } else {
            $query->where('organization_id', $currentUser->organization_id);
        }

        $users = $query->orderByDesc('created_at')->get();

        $result = $users->map(function ($user) {
            $org = $user->organization_id ? Organization::find($user->organization_id) : null;

            return [
                'id'                => $user->id,
                'email'             => $user->email,
                'full_name'         => $user->full_name,
                'role'              => $user->role,
                'is_active'         => $user->is_active,
                'organization_id'   => $user->organization_id,
                'organization_name' => $org?->name,
                'created_at'        => $user->created_at?->toIso8601String(),
                'phone'             => $user->phone,
                'company'           => $user->company,
                'address'           => $user->address,
                'city'              => $user->city,
                'country'           => $user->country,
                'vat_number'        => $user->vat_number,
                'notes'             => $user->notes,
            ];
        });

        return response()->json($result);
    }

    // POST /api/admin/users
    public function createUser(Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);

        $data = $request->validate([
            'email'           => 'required|email',
            'full_name'       => 'required|string|max:255',
            'password'        => 'required|string|min:6',
            'role'            => 'nullable|string',
            'organization_id' => 'nullable|integer',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['detail' => 'Email già registrata'], 400);
        }

        $role = $data['role'] ?? 'editor';
        if (! in_array($role, self::VALID_ROLES)) {
            return response()->json(['detail' => 'Ruolo non valido'], 400);
        }

        if ($role === 'superuser' && $currentUser->role !== 'superuser') {
            return response()->json(['detail' => 'Solo superuser può creare superuser'], 403);
        }

        $orgId = ($currentUser->role === 'superuser' && isset($data['organization_id']))
            ? $data['organization_id']
            : $currentUser->organization_id;

        $user = User::create([
            'email'           => $data['email'],
            'full_name'       => $data['full_name'],
            'password'        => $data['password'],
            'role'            => $role,
            'organization_id' => $orgId,
            'is_active'       => true,
        ]);

        // TODO: log_activity

        $org = $orgId ? Organization::find($orgId) : null;

        return response()->json([
            'id'                => $user->id,
            'email'             => $user->email,
            'full_name'         => $user->full_name,
            'role'              => $user->role,
            'is_active'         => $user->is_active,
            'organization_id'   => $user->organization_id,
            'organization_name' => $org?->name,
            'created_at'        => $user->created_at?->toIso8601String(),
        ], 201);
    }

    // PUT /api/admin/users/{id}
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);

        $data = $request->validate([
            'full_name'       => 'nullable|string|max:255',
            'role'            => 'nullable|string',
            'is_active'       => 'nullable|boolean',
            'organization_id' => 'nullable|integer',
        ]);

        $user = $currentUser->role === 'superuser'
            ? User::findOrFail($id)
            : User::where('id', $id)
                ->where('organization_id', $currentUser->organization_id)
                ->firstOrFail();

        // Non può modificare il proprio ruolo
        if ($id === $currentUser->id && isset($data['role']) && $data['role'] !== $currentUser->role) {
            return response()->json(['detail' => 'Non puoi modificare il tuo ruolo'], 400);
        }

        if (isset($data['role']) && $data['role'] === 'superuser' && $currentUser->role !== 'superuser') {
            return response()->json(['detail' => 'Solo superuser può assegnare ruolo superuser'], 403);
        }

        if (isset($data['full_name'])) {
            $user->full_name = $data['full_name'];
        }
        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }
        if (isset($data['organization_id']) && $currentUser->role === 'superuser') {
            $user->organization_id = $data['organization_id'];
        }

        $user->save();

        // TODO: log_activity

        $org = $user->organization_id ? Organization::find($user->organization_id) : null;

        return response()->json([
            'id'                => $user->id,
            'email'             => $user->email,
            'full_name'         => $user->full_name,
            'role'              => $user->role,
            'is_active'         => $user->is_active,
            'organization_id'   => $user->organization_id,
            'organization_name' => $org?->name,
            'created_at'        => $user->created_at?->toIso8601String(),
            'phone'             => $user->phone,
            'company'           => $user->company,
            'address'           => $user->address,
            'city'              => $user->city,
            'country'           => $user->country,
            'vat_number'        => $user->vat_number,
            'notes'             => $user->notes,
        ]);
    }

    // DELETE /api/admin/users/{id}
    public function deleteUser(int $id, Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);

        if ($id === $currentUser->id) {
            return response()->json(['detail' => 'Non puoi eliminare te stesso'], 400);
        }

        $user = $currentUser->role === 'superuser'
            ? User::findOrFail($id)
            : User::where('id', $id)
                ->where('organization_id', $currentUser->organization_id)
                ->firstOrFail();

        $user->is_active = false;
        $user->save();

        // TODO: log_activity

        return response()->json(['message' => 'Utente disattivato']);
    }

    // DELETE /api/admin/users/{id}/permanent
    public function permanentDeleteUser(int $id, Request $request): JsonResponse
    {
        $this->requireSuperuser($request);

        $currentUser = $request->user();
        if ($id === $currentUser->id) {
            return response()->json(['detail' => 'Non puoi eliminare te stesso'], 400);
        }

        $user = User::findOrFail($id);
        $email = $user->email;

        $user->forceDelete();

        // TODO: log_activity

        return response()->json(['message' => "Utente {$email} eliminato definitivamente"]);
    }

    // ─── Activity Log ──────────────────────────────────────────

    // GET /api/admin/activity
    public function activity(Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);

        $query = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->limit(100);

        if ($currentUser->role !== 'superuser') {
            $query->where('organization_id', $currentUser->organization_id);
        } elseif ($request->query('organization_id')) {
            $query->where('organization_id', $request->query('organization_id'));
        }

        $logs = $query->get()->map(fn ($log) => [
            'id'           => $log->id,
            'user_id'      => $log->user_id,
            'username'     => $log->user?->name ?? $log->user?->email ?? 'system',
            'action'       => $log->action,
            'entity_type'  => $log->entity_type,
            'entity_id'    => $log->entity_id,
            'entity_name'  => $log->entity_name,
            'details'      => $log->details,
            'ip_address'   => $log->ip_address,
            'created_at'   => $log->created_at?->toIso8601String(),
        ]);

        return response()->json($logs);
    }

    // ─── Stats ─────────────────────────────────────────────────

    // GET /api/admin/stats
    public function stats(Request $request): JsonResponse
    {
        $currentUser = $this->requireAdmin($request);
        $orgFilter   = $request->query('organization_id');

        if ($currentUser->role === 'superuser' && ! $orgFilter) {
            // Totali globali — bypass tenant scope
            return response()->json([
                'users'         => User::where('is_active', true)->count(),
                'brands'        => Brand::withoutGlobalScope('organization')->count(),
                'projects'      => Project::withoutGlobalScope('organization')->count(),
                'posts'         => Post::withoutGlobalScope('organization')->count(),
                'organizations' => Organization::count(),
            ]);
        }

        $orgId = $currentUser->role === 'superuser'
            ? (int) $orgFilter
            : $currentUser->organization_id;

        $usersCount    = User::where('organization_id', $orgId)->where('is_active', true)->count();
        $brandsCount   = Brand::withoutGlobalScope('organization')->where('organization_id', $orgId)->count();
        $projectsCount = Project::withoutGlobalScope('organization')->where('organization_id', $orgId)->count();
        $postsCount    = Post::withoutGlobalScope('organization')->where('organization_id', $orgId)->count();

        return response()->json([
            'users'    => $usersCount,
            'brands'   => $brandsCount,
            'projects' => $projectsCount,
            'posts'    => $postsCount,
        ]);
    }

    // ─── Auth helpers ──────────────────────────────────────────

    private function requireAdmin(Request $request): User
    {
        $user = $request->user();
        if (! in_array($user->role, ['superuser', 'admin'])) {
            abort(403, 'Admin access required');
        }

        return $user;
    }

    private function requireSuperuser(Request $request): User
    {
        $user = $request->user();
        if ($user->role !== 'superuser') {
            abort(403, 'Superuser access required');
        }

        return $user;
    }
}
