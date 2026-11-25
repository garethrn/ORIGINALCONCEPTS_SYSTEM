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
            'legal_name' => ['nullable', 'string', 'max:150'],
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
            'website' => ['nullable', 'string', 'max:150'],
            'bbbee' => ['nullable', 'string', 'max:80'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account' => ['nullable', 'string', 'max:40'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'default_labour_rate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'after_hours_multiplier' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'call_out_fee' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'minimum_billable_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'rounding_minutes' => ['required', 'integer', 'in:1,5,15,30,60'],
            'travel_rate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'standard_vat_percent' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'show_prices_excl_vat' => ['nullable', 'boolean'],
            'invoice_time_layout' => ['required', 'in:summary,detail'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        if (bccomp($data['after_hours_multiplier'], '0', 2) !== 1) {
            return back()->withErrors(['after_hours_multiplier' => 'The after-hours multiplier must be above zero.'])->withInput();
        }
        if ((float) $data['standard_vat_percent'] > 100) {
            return back()->withErrors(['standard_vat_percent' => 'VAT must be 100% or less.'])->withInput();
        }
        $data['default_labour_rate_cents'] = (string) Money::randsToCents($data['default_labour_rate']);
        $data['call_out_fee_cents'] = (string) Money::randsToCents($data['call_out_fee']);
        $data['travel_rate_cents'] = (string) Money::randsToCents($data['travel_rate']);
        $data['standard_vat_basis_points'] = (string) (int) round(((float) $data['standard_vat_percent']) * 100);
        $data['show_prices_excl_vat'] = $request->boolean('show_prices_excl_vat') ? '1' : '0';
        $data['fiscal_year_start_month'] = (string) (int) ($data['fiscal_year_start_month'] ?? $company->get('fiscal_year_start_month', '3'));
        $data['minimum_billable_minutes'] = (string) (int) $data['minimum_billable_minutes'];
        $data['rounding_minutes'] = (string) (int) $data['rounding_minutes'];
        unset($data['default_labour_rate'], $data['call_out_fee'], $data['travel_rate'], $data['standard_vat_percent']);
        $company->update($data);

        return back()->with('status', 'Company settings saved.');
    }
}
