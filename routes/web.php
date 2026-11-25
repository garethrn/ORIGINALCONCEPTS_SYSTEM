<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\BankingController;
use App\Http\Controllers\BillingWizardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringScheduleController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

Route::post('/webhooks/payfast', WebhookController::class)->middleware('throttle:webhooks')->withoutMiddleware(ValidateCsrfToken::class);
Route::post('/webhooks/yoco', WebhookController::class)->middleware('throttle:webhooks')->withoutMiddleware(ValidateCsrfToken::class);

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('home');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'update'])->name('profile.password');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'start'])->name('profile.two-factor.start');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::post('/clients/{client}/contacts', [ClientController::class, 'storeContact'])->name('clients.contacts.store');
    Route::delete('/clients/{client}/contacts/{contact}', [ClientController::class, 'destroyContact'])->name('clients.contacts.destroy');
    Route::get('/clients/{client}/bill', [BillingWizardController::class, 'client'])->name('clients.bill');
    Route::post('/clients/{client}/bill', [BillingWizardController::class, 'storeClient'])->name('clients.bill.store');
    Route::post('/clients/{client}/sites', [ClientController::class, 'storeSite'])->name('clients.sites.store');
    Route::delete('/clients/{client}/sites/{site}', [ClientController::class, 'destroySite'])->name('clients.sites.destroy');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comment');
    Route::post('/tickets/{ticket}/attachments', [TicketController::class, 'attachment'])->name('tickets.attachments.store');
    Route::get('/tickets/{ticket}/attachments/{attachment}', [TicketController::class, 'download'])->name('tickets.attachments.download');
    Route::post('/tickets/{ticket}/time', [TicketController::class, 'time'])->name('tickets.time');
    Route::post('/tickets/{ticket}/parts', [TicketController::class, 'part'])->name('tickets.parts');
    Route::get('/tickets/{ticket}/bill', [BillingWizardController::class, 'ticket'])->name('tickets.bill');
    Route::post('/tickets/{ticket}/bill', [BillingWizardController::class, 'storeTicket'])->name('tickets.bill.store');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::post('/quotes/{quote}/issue', [QuoteController::class, 'issue'])->name('quotes.issue');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
    Route::post('/quotes/{quote}/decision', [QuoteController::class, 'accept'])->name('quotes.decision');
    Route::post('/quotes/{quote}/choose', [QuoteController::class, 'choose'])->name('quotes.choose');
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::get('/invoices/{invoice}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('/invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    Route::get('/credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
    Route::post('/credit-notes/{creditNote}/issue', [CreditNoteController::class, 'issue'])->name('credit-notes.issue');
    Route::post('/credit-notes/{creditNote}/send', [CreditNoteController::class, 'send'])->name('credit-notes.send');
    Route::get('/credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');

    Route::get('/recurring', [RecurringScheduleController::class, 'index'])->name('recurring.index');
    Route::get('/recurring/create', [RecurringScheduleController::class, 'create'])->name('recurring.create');
    Route::post('/recurring', [RecurringScheduleController::class, 'store'])->name('recurring.store');
    Route::get('/recurring/{schedule}', [RecurringScheduleController::class, 'show'])->name('recurring.show');
    Route::get('/recurring/{schedule}/edit', [RecurringScheduleController::class, 'edit'])->name('recurring.edit');
    Route::put('/recurring/{schedule}', [RecurringScheduleController::class, 'update'])->name('recurring.update');
    Route::post('/recurring/{schedule}/run', [RecurringScheduleController::class, 'run'])->name('recurring.run');
    Route::delete('/recurring/{schedule}', [RecurringScheduleController::class, 'destroy'])->name('recurring.destroy');

    Route::get('/clients/{client}/statement', [StatementController::class, 'show'])->name('clients.statement');
    Route::get('/clients/{client}/statement.pdf', [StatementController::class, 'pdf'])->name('clients.statement.pdf');
    Route::get('/clients/{client}/statement.csv', [StatementController::class, 'csv'])->name('clients.statement.csv');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/debtors', DebtorController::class)->name('debtors.index');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::get('/journals/create', [JournalController::class, 'create'])->name('journals.create');
    Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');
    Route::get('/journals/{entry}', [JournalController::class, 'show'])->name('journals.show');

    Route::get('/banking', [BankingController::class, 'index'])->name('banking.index');
    Route::get('/banking/{bankAccount}', [BankingController::class, 'show'])->name('banking.show');
    Route::post('/banking/{bankAccount}/import', [BankingController::class, 'import'])->name('banking.import');
    Route::post('/banking/{bankAccount}/rules', [BankingController::class, 'rule'])->name('banking.rules.store');
    Route::post('/banking/{bankAccount}/reconcile', [BankingController::class, 'reconcile'])->name('banking.reconcile');
    Route::post('/bank-transactions/{transaction}/match', [BankingController::class, 'match'])->name('banking.match');

    Route::get('/suppliers', [PurchaseController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [PurchaseController::class, 'storeSupplier'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [PurchaseController::class, 'show'])->name('suppliers.show');
    Route::post('/suppliers/{supplier}/bills', [PurchaseController::class, 'storeBill'])->name('bills.store');
    Route::post('/bills/{bill}/issue', [PurchaseController::class, 'issueBill'])->name('bills.issue');
    Route::post('/expenses', [PurchaseController::class, 'storeExpense'])->name('expenses.store');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::middleware(['staff', 'throttle:ai'])->group(function () {
        Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
        Route::post('/assistant', [AssistantController::class, 'store'])->name('assistant.store');
        Route::post('/assistant/{draft}/confirm', [AssistantController::class, 'confirm'])->name('assistant.confirm');
        Route::post('/assistant/{draft}/cancel', [AssistantController::class, 'cancel'])->name('assistant.cancel');
    });
});
