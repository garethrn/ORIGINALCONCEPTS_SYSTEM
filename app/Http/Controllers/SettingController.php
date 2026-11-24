<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Payments\OnlinePaymentGateway;
use App\Contracts\Payments\RecurringBillingGateway;
use App\Services\Billing\CompanyProfile;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(CompanyProfile $company, OnlinePaymentGateway $payfast, RecurringBillingGateway $subscriptions): View
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);

        return view('settings.edit', [
            'company' => $company->all(),
            'payfastConfigured' => $payfast->isConfigured(),
            'subscriptionsConfigured' => $subscriptions->isConfigured(),
        ]);
    }

    public function update(Request $request, CompanyProfile $company): RedirectResponse
    {
        abort_unless($request->user()->role->canManageSettings(), 403);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:150'],
            'trading_name' => ['nullable', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account' => ['nullable', 'string', 'max:40'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'default_labour_rate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $data['default_labour_rate_cents'] = (string) Money::randsToCents($data['default_labour_rate']);
        unset($data['default_labour_rate']);
        $company->update($data);

        return back()->with('status', 'Company settings saved.');
    }
}
