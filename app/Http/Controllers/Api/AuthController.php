<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Data\ChangePasswordData;
use App\Domain\User\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Autenticazione — corrisponde a auth.py (Python).
 *
 * NOTA: POST /login accetta form-urlencoded con username+password
 * (il frontend invia URLSearchParams, NON JSON).
 */
final class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'     => 'required|email',
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
            'email.required'    => 'L\'email è obbligatoria.',
            'email.email'       => 'L\'email non è valida.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve contenere almeno 8 caratteri.',
            'password.regex'    => 'La password deve contenere almeno una minuscola, una maiuscola e un numero.',
            'full_name.max'     => 'Il nome completo non può superare 255 caratteri.',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['detail' => 'Email già registrata'], 400);
        }

        $user = User::create([
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'full_name' => $data['full_name'] ?? null,
        ]);

        return response()->json([
            'id'              => $user->id,
            'email'           => $user->email,
            'full_name'       => $user->full_name,
            'is_active'       => $user->is_active,
            'organization_id' => $user->organization_id,
            'created_at'      => $user->created_at?->toIso8601String(),
        ], 201);
    }

    // POST /api/auth/login  — form-urlencoded (username + password)
    public function login(Request $request): JsonResponse
    {
        $email    = $request->input('username'); // frontend invia "username"
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'detail' => 'Email o password non corretti',
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
        ]);
    }

    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $org  = $user->organization_id
            ? Organization::find($user->organization_id)
            : null;

        return response()->json([
            'id'                => $user->id,
            'email'             => $user->email,
            'full_name'         => $user->full_name,
            'role'              => $user->role,
            'is_active'         => $user->is_active,
            'organization_id'   => $user->organization_id,
            'organization_name' => $org?->name,
            'phone'             => $user->phone,
            'company'           => $user->company,
            'address'           => $user->address,
            'city'              => $user->city,
            'country'           => $user->country,
            'vat_number'        => $user->vat_number,
            'notes'             => $user->notes,
            'subscription_status' => $org?->subscription_status?->value,
            'trial_ends_at'       => $org?->trial_ends_at?->toIso8601String(),
        ]);
    }

    // PUT /api/auth/profile
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'company'    => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:500',
            'city'       => 'nullable|string|max:255',
            'country'    => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'notes'      => 'nullable|string',
        ]);

        $user = $request->user();
        $user->fill(array_filter($data, fn ($v) => $v !== null));
        $user->save();

        return response()->json(['message' => 'Profilo aggiornato']);
    }

    // POST /api/auth/change-password
    public function changePassword(Request $request): JsonResponse
    {
        $dto = ChangePasswordData::from($request);

        $user = $request->user();

        if (! Hash::check($dto->current_password, $user->password)) {
            return response()->json(['detail' => 'Password attuale non corretta'], 400);
        }

        $user->password = Hash::make($dto->new_password);
        $user->save();

        return response()->json(['message' => 'Password cambiata con successo']);
    }

    // POST /api/auth/forgot-password
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            Mail::to($user)->send(new PasswordResetMail($token, $user));
        }

        // Rispondi sempre 200 per non rivelare quali email sono registrate
        return response()->json([
            'message' => 'Se l\'email è registrata, riceverai un link a breve.',
        ]);
    }

    // POST /api/auth/reset-password
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // almeno una minuscola
                'regex:/[A-Z]/',      // almeno una maiuscola
                'regex:/[0-9]/',      // almeno un numero
            ],
        ], [
            'password.required'  => 'La password è obbligatoria.',
            'password.min'       => 'La password deve contenere almeno 8 caratteri.',
            'password.confirmed' => 'La conferma password non coincide.',
            'password.regex'     => 'La password deve contenere almeno una minuscola, una maiuscola e un numero.',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return response()->json(['detail' => 'Token non valido o scaduto.'], 400);
        }

        // Verifica scadenza (60 minuti)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return response()->json(['detail' => 'Token scaduto. Richiedi un nuovo link.'], 400);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['detail' => 'Utente non trovato.'], 404);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Elimina il token usato
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Revoca tutti i token Sanctum per sicurezza
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reimpostata con successo.']);
    }

    // POST /api/auth/refresh
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user) {
            return response()->json(['detail' => 'Token non valido'], 401);
        }

        // Revoca il token corrente e ne emette uno nuovo
        $request->user()->currentAccessToken()->delete();
        $newToken = $user->createToken('api')->plainTextToken;

        return response()->json([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
        ]);
    }
}
