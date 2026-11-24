<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', ['users' => User::query()->with('client')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.form', [
            'userModel' => new User(['role' => Role::Technician, 'is_active' => true]),
            'clients' => Client::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $this->validated($request);
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'client_id' => $data['role'] === Role::Client->value ? $data['client_id'] : null,
            'is_active' => $data['is_active'],
        ]);
        $user->role = Role::from($data['role']);
        $user->password = $data['password'];
        $user->save();
        $audit->log('user.created', $user, null, ['email' => $user->email, 'role' => $user->role->value]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.form', [
            'userModel' => $user,
            'clients' => Client::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $this->validated($request, $user);
        $before = ['role' => $user->role->value, 'is_active' => $user->is_active, 'client_id' => $user->client_id];
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'client_id' => $data['role'] === Role::Client->value ? $data['client_id'] : null,
            'is_active' => $data['is_active'],
        ]);
        $user->role = Role::from($data['role']);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $audit->log('user.updated', $user, $before, ['role' => $user->role->value, 'is_active' => $user->is_active, 'client_id' => $user->client_id]);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(Role::class)],
            'client_id' => ['nullable', 'integer', 'exists:clients,id', 'required_if:role,client'],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
