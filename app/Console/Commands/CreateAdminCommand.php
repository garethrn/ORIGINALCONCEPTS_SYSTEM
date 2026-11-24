<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the first administrator. The password is read from a prompt or from the environment, never from a default.';

    public function handle(): int
    {
        try {
            $name = $this->value('ADMIN_NAME', 'Full name');
            $email = $this->value('ADMIN_EMAIL', 'Email');
            $password = $this->secretValue();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:150', 'unique:users,email'],
                'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'is_active' => true,
        ]);
        $user->role = Role::Admin;
        $user->password = $password;
        $user->save();

        $this->info('Administrator created for '.$email.'.');
        $this->comment('If you passed the password through the environment, unset ADMIN_PASSWORD now.');

        return self::SUCCESS;
    }

    private function value(string $envKey, string $label): string
    {
        $fromEnv = $this->envValue($envKey);
        if ($fromEnv !== null) {
            return $fromEnv;
        }
        if ($this->option('no-interaction')) {
            throw new RuntimeException($envKey.' is required when running without a prompt.');
        }

        return (string) $this->ask($label);
    }

    private function secretValue(): string
    {
        $fromEnv = $this->envValue('ADMIN_PASSWORD');
        if ($fromEnv !== null) {
            return $fromEnv;
        }
        if ($this->option('no-interaction')) {
            throw new RuntimeException('Set ADMIN_PASSWORD in the environment for this command only, or run it interactively.');
        }

        $password = (string) $this->secret('Password (min 12 characters, mixed case and a number)');
        $confirm = (string) $this->secret('Confirm password');
        if (! hash_equals($password, $confirm)) {
            throw new RuntimeException('The passwords did not match.');
        }

        return $password;
    }

    private function envValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
