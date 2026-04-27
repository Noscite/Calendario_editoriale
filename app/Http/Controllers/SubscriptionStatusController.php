<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionStatusController extends Controller
{
    public function inactive(Request $request): View
    {
        $billing = [
            'iban'          => config('billing.iban'),
            'company_name'  => config('billing.company_name'),
            'company_email' => config('billing.company_email'),
            'company_phone' => config('billing.company_phone'),
        ];

        $subscription = $request->user()?->organization?->subscription;

        return view('subscription.inactive', compact('billing', 'subscription'));
    }
}
