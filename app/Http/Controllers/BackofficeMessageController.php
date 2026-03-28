<?php

namespace App\Http\Controllers;

use App\Models\CallMessage;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackofficeMessageController extends Controller
{
    public function __construct(private readonly TenantResolver $tenantResolver) {}

    public function update(Request $request, int $messageId): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(CallMessage::workflowStatuses())],
        ]);

        $tenant = $this->tenantResolver->forUser($request->user());

        abort_unless($tenant, 404);

        $message = $tenant->callMessages()
            ->whereKey($messageId)
            ->firstOrFail();

        $userId = $request->user()->id;
        $status = $validated['status'];

        $attributes = match ($status) {
            CallMessage::STATUS_NEW => [
                'status' => $status,
                'assigned_to_user_id' => null,
                'handled_by_user_id' => null,
                'handled_at' => null,
            ],
            CallMessage::STATUS_IN_PROGRESS => [
                'status' => $status,
                'assigned_to_user_id' => $userId,
                'handled_by_user_id' => null,
                'handled_at' => null,
            ],
            default => [
                'status' => $status,
                'assigned_to_user_id' => $message->assigned_to_user_id ?? $userId,
                'handled_by_user_id' => $userId,
                'handled_at' => now(),
            ],
        };

        $message->fill($attributes)->save();

        return back()->with('success', 'Le statut du message a été mis à jour.');
    }
}
