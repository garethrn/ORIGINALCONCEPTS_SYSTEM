<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()->role === Role::Admin, 403);
        $logs = AuditLog::query()->with('user')->latest('created_at')->paginate(40);

        return view('audit.index', compact('logs'));
    }
}
