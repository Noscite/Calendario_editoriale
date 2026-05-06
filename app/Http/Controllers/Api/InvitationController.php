<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Invitation;
use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use App\Mail\InvitationMail;
use App\Mail\Subscription\WelcomeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class InvitationController extends Controller
{
    // POST /api/admin/invitations
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'role'  => 'required|in:admin,editor,viewer',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! in_array($user->role, ['owner', 'admin'])) {
            return response()->json(['detail' => 'Permesso negato.'], 403);
        }

        $org = $user->organization;

        // Verifica email non già registrata nell'organizzazione
        if (User::where('email', $data['email'])->where('organization_id', $org->id)->exists()) {
            return response()->json(['detail' => 'Questo utente è già nell\'organizzazione.'], 400);
        }

        // Verifica limite utenti del piano
        $plan = $org->plan;
        if ($plan) {
            $limits = $plan->limits ?? [];
            $maxUsers = $limits['max_users'] ?? 1;
            $currentUsers = User::where('organization_id', $org->id)->count();
            $pendingInvites = Invitation::where('organization_id', $org->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->count();

            if ($maxUsers !== -1 && ($currentUsers + $pendingInvites) >= $maxUsers) {
                return response()->json([
                    'detail' => "Hai raggiunto il limite di {$maxUsers} utenti per il tuo piano.",
                ], 400);
            }
        }

        // Verifica invito pendente per stessa email
        $existing = Invitation::where('organization_id', $org->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json(['detail' => 'Invito già inviato a questo indirizzo.'], 400);
        }

        $invitation = Invitation::create([
            'organization_id' => $org->id,
            'email'           => $data['email'],
            'token'           => Str::random(64),
            'role'            => $data['role'],
            'invited_by'      => $user->id,
            'expires_at'      => now()->addDays(7),
        ]);

        $invitation->load('inviter', 'organization');

        Mail::to($data['email'])->send(new InvitationMail($invitation));

        return response()->json([
            'id'         => $invitation->id,
            'email'      => $invitation->email,
            'role'       => $invitation->role,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 201);
    }

    // GET /api/auth/invitation/{token} — PUBBLICO
    public function validateToken(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with('organization:id,name')
            ->first();

        if (! $invitation) {
            return response()->json(['detail' => 'Invito non trovato.'], 404);
        }

        if ($invitation->isAccepted()) {
            return response()->json(['detail' => 'Invito già accettato.'], 400);
        }

        if ($invitation->isExpired()) {
            return response()->json(['detail' => 'Invito scaduto.'], 400);
        }

        return response()->json([
            'email'             => $invitation->email,
            'role'              => $invitation->role,
            'organization_name' => $invitation->organization?->name,
            'expires_at'        => $invitation->expires_at->toIso8601String(),
        ]);
    }

    // POST /api/auth/invitation/{token}/accept — PUBBLICO
    public function accept(string $token, Request $request): JsonResponse
    {
        $data = $request->validate([
            'password'  => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',      // almeno una minuscola
                'regex:/[A-Z]/',      // almeno una maiuscola
                'regex:/[0-9]/',      // almeno un numero
            ],
            'full_name' => 'nullable|string|max:255',
        ], [
            'password.required'  => 'La password è obbligatoria.',
            'password.min'       => 'La password deve contenere almeno 8 caratteri.',
            'password.regex'     => 'La password deve contenere almeno una minuscola, una maiuscola e un numero.',
            'full_name.max'      => 'Il nome completo non può superare 255 caratteri.',
        ]);

        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            return response()->json(['detail' => 'Invito non valido, scaduto o già accettato.'], 400);
        }

        // Verifica se l'utente esiste già
        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            // Associa all'organizzazione
            $existingUser->update([
                'organization_id' => $invitation->organization_id,
                'role'            => $invitation->role,
            ]);
            $user = $existingUser;
        } else {
            $user = User::create([
                'email'           => $invitation->email,
                'password'        => Hash::make($data['password']),
                'full_name'       => $data['full_name'] ?? null,
                'organization_id' => $invitation->organization_id,
                'role'            => $invitation->role,
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        // Invia email di benvenuto solo ai nuovi utenti (non a quelli già esistenti ri-associati)
        if (! $existingUser) {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        }

        $accessToken = $user->createToken('api')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type'   => 'bearer',
            'user'         => [
                'id'    => $user->id,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    // GET /api/admin/invitations
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invitations = Invitation::where('organization_id', $user->organization_id)
            ->with('inviter:id,full_name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invitation $inv) => [
                'id'          => $inv->id,
                'email'       => $inv->email,
                'role'        => $inv->role,
                'invited_by'  => $inv->inviter?->full_name,
                'expires_at'  => $inv->expires_at->toIso8601String(),
                'accepted_at' => $inv->accepted_at?->toIso8601String(),
                'is_expired'  => $inv->isExpired(),
                'is_accepted' => $inv->isAccepted(),
            ]);

        // Utenti attuali dell'organizzazione
        $users = User::where('organization_id', $user->organization_id)
            ->select('id', 'email', 'full_name', 'role', 'created_at')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'invitations' => $invitations,
            'users'       => $users,
        ]);
    }

    // DELETE /api/admin/invitations/{id}
    public function revoke(int $id): JsonResponse
    {
        $invitation = Invitation::findOrFail($id);

        if ($invitation->isAccepted()) {
            return response()->json(['detail' => 'Invito già accettato, impossibile revocare.'], 400);
        }

        $invitation->delete();

        return response()->json(['message' => 'Invito revocato.']);
    }
}
