<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Ai\ConfirmAiDraft;
use App\Models\AiDraft;
use App\Services\Ai\Assistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role->canUseAssistant(), 403);
        $draft = null;
        if ($request->filled('draft')) {
            $draft = AiDraft::query()->where('user_id', $request->user()->id)->find($request->integer('draft'));
        }

        return view('assistant.index', [
            'enabled' => (bool) config('support.ai.enabled'),
            'transcript' => $request->session()->get('assistant.transcript', []),
            'draft' => $draft,
        ]);
    }

    public function store(Request $request, Assistant $assistant): RedirectResponse
    {
        abort_unless($request->user()->role->canUseAssistant(), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $reply = $assistant->respond($request->user(), $data['message']);
        $transcript = $request->session()->get('assistant.transcript', []);
        $transcript[] = ['role' => 'user', 'text' => $data['message']];
        $transcript[] = ['role' => 'assistant', 'text' => $reply->message];
        $request->session()->put('assistant.transcript', array_slice($transcript, -20));

        return redirect()->route('assistant.index', $reply->draft ? ['draft' => $reply->draft->id] : [])
            ->with('status', $reply->message);
    }

    public function confirm(Request $request, AiDraft $draft, ConfirmAiDraft $action): RedirectResponse
    {
        $this->authorize('confirm', $draft);
        $data = $request->validate([
            'decision' => ['required', 'in:save,issue,issue_and_email,create'],
            'confirm' => ['accepted'],
        ]);
        $result = $action->handle($request->user(), $draft, $data['decision']);

        return redirect()->route($result->route, $result->id)->with('status', $result->message);
    }

    public function cancel(Request $request, AiDraft $draft): RedirectResponse
    {
        $this->authorize('confirm', $draft);
        $draft->status = 'cancelled';
        $draft->save();

        return redirect()->route('assistant.index')->with('status', 'Draft discarded.');
    }
}
