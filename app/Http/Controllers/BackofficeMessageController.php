<?php

namespace App\Http\Controllers;

use App\Models\CallMessage;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackofficeMessageController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function update(Request $request, int $messageId): RedirectResponse
    {
        $tenant = $this->tenantResolver->forUser($request->user());

        abort_unless($tenant, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(CallMessage::workflowStatuses())],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'callback_due_at' => ['nullable', 'date'],
        ]);

        $message = $tenant->callMessages()
            ->whereKey($messageId)
            ->firstOrFail();

        $previousStatus = $message->status;
        $previousAssigneeId = $message->assigned_to_user_id;
        $previousCallbackDueAt = $message->callback_due_at?->toIso8601String();
        $userId = $request->user()->id;
        $status = $validated['status'];
        $assignedToUserId = $validated['assigned_to_user_id'] ?? null;
        $callbackDueAt = $validated['callback_due_at'] ?? null;

        $attributes = match ($status) {
            CallMessage::STATUS_NEW => [
                'status' => $status,
                'assigned_to_user_id' => null,
                'handled_by_user_id' => null,
                'handled_at' => null,
                'callback_due_at' => null,
            ],
            CallMessage::STATUS_IN_PROGRESS => [
                'status' => $status,
                'assigned_to_user_id' => $assignedToUserId ?? $message->assigned_to_user_id ?? $userId,
                'handled_by_user_id' => null,
                'handled_at' => null,
                'callback_due_at' => $callbackDueAt,
            ],
            default => [
                'status' => $status,
                'assigned_to_user_id' => $assignedToUserId ?? $message->assigned_to_user_id ?? $userId,
                'handled_by_user_id' => $userId,
                'handled_at' => now(),
                'callback_due_at' => null,
            ],
        };

        $message->fill($attributes)->save();
        $message->loadMissing(['call', 'assignedTo', 'handledBy']);

        if ($previousStatus !== $message->status) {
            $this->activityLogger->log(
                tenant: $tenant,
                eventType: 'message_status_updated',
                title: 'Statut message mis a jour',
                description: "Le message est passe de {$previousStatus} a {$message->status}.",
                user: $request->user(),
                call: $message->call,
                callMessage: $message,
                metadata: [
                    'previous_status' => $previousStatus,
                    'new_status' => $message->status,
                ],
            );
        }

        if ($previousAssigneeId !== $message->assigned_to_user_id && $message->assigned_to_user_id) {
            $this->activityLogger->log(
                tenant: $tenant,
                eventType: 'message_assigned',
                title: 'Message assigne',
                description: 'Le message a ete assigne a '.$message->assignedTo?->name.'.',
                user: $request->user(),
                call: $message->call,
                callMessage: $message,
                metadata: [
                    'assigned_to_user_id' => $message->assigned_to_user_id,
                    'assigned_to_name' => $message->assignedTo?->name,
                ],
            );
        }

        if ($message->callback_due_at && $previousCallbackDueAt !== $message->callback_due_at->toIso8601String()) {
            $this->activityLogger->log(
                tenant: $tenant,
                eventType: 'callback_scheduled',
                title: 'Rappel planifie',
                description: 'Le rappel est programme pour le '.$message->callback_due_at->timezone('Europe/Brussels')->format('d/m/Y H:i').'.',
                user: $request->user(),
                call: $message->call,
                callMessage: $message,
                metadata: [
                    'callback_due_at' => $message->callback_due_at->toIso8601String(),
                ],
            );
        }

        return back()->with('success', 'Le suivi du message a ete mis a jour.');
    }
}
