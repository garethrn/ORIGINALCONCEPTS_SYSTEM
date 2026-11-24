<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiDraft;
use App\Models\User;

class AiDraftPolicy
{
    public function view(User $user, AiDraft $draft): bool
    {
        return $user->id === $draft->user_id;
    }

    public function confirm(User $user, AiDraft $draft): bool
    {
        return $user->id === $draft->user_id
            && $draft->status === 'pending'
            && $draft->expires_at->isFuture();
    }
}
