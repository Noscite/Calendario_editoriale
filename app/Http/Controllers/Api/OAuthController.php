<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * OAuth callbacks LEGACY — /api/oauth/{platform}/callback
 *
 * Delega a SocialController per evitare duplicazione.
 * I callback REALI registrati nei provider OAuth sono sotto /api/social/callback/*.
 * Queste route restano per retrocompatibilità.
 */
final class OAuthController extends Controller
{
    public function callbackFacebook(Request $request): RedirectResponse
    {
        return redirect()->to('/api/social/callback/facebook?' . $request->getQueryString());
    }

    public function callbackInstagram(Request $request): RedirectResponse
    {
        return redirect()->to('/api/social/callback/instagram?' . $request->getQueryString());
    }

    public function callbackLinkedin(Request $request): RedirectResponse
    {
        return redirect()->to('/api/social/callback/linkedin?' . $request->getQueryString());
    }

    public function callbackGoogle(Request $request): RedirectResponse
    {
        return redirect()->to('/api/social/callback/google?' . $request->getQueryString());
    }
}
