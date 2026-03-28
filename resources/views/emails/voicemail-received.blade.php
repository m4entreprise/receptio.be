<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Nouveau message vocal</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
        <div style="padding:24px 24px 12px;">
            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#64748b;">Receptio</p>
            <h1 style="margin:0;font-size:24px;line-height:1.2;">Nouveau message vocal</h1>
            <p style="margin:16px 0 0;font-size:15px;line-height:1.6;color:#475569;">
                Un nouveau message vocal a ete enregistre pour <strong>{{ $call->tenant->name }}</strong>.
            </p>
        </div>

        <div style="padding:12px 24px 24px;">
            <div style="margin-bottom:16px;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">
                <p style="margin:0 0 8px;font-size:14px;"><strong>Appelant :</strong> {{ $callMessage->caller_name ?: ($callMessage->caller_number ?: ($call->from_number ?: 'Inconnu')) }}</p>
                <p style="margin:0 0 8px;font-size:14px;"><strong>Numero :</strong> {{ $callMessage->caller_number ?: ($call->from_number ?: 'Inconnu') }}</p>
                <p style="margin:0 0 8px;font-size:14px;"><strong>Recu le :</strong> {{ optional($callMessage->created_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') }}</p>
                <p style="margin:0;font-size:14px;"><strong>CallSid :</strong> {{ $call->external_sid ?: 'n/a' }}</p>
            </div>

            <div style="margin-bottom:16px;padding:16px;border:1px solid #e2e8f0;border-radius:14px;">
                <p style="margin:0 0 8px;font-size:14px;"><strong>Resume :</strong></p>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;">
                    {{ $call->summary ?: ($callMessage->message_text ?: 'Message vocal recu.') }}
                </p>
            </div>

            <div style="margin-bottom:24px;">
                <a href="{{ route('dashboard.messages') }}" style="display:inline-block;margin-right:12px;padding:12px 18px;border-radius:999px;background:#0f172a;color:#ffffff;text-decoration:none;font-size:14px;">Ouvrir l'inbox</a>
                <a href="{{ route('dashboard.calls.show', $call->id) }}" style="display:inline-block;padding:12px 18px;border-radius:999px;border:1px solid #cbd5e1;color:#0f172a;text-decoration:none;font-size:14px;">Voir la fiche appel</a>
            </div>

            @if ($callMessage->recording_url)
                <p style="margin:0;font-size:13px;color:#475569;">
                    Enregistrement :
                    <a href="{{ $callMessage->recording_url }}" style="color:#0f172a;">{{ $callMessage->recording_url }}</a>
                </p>
            @endif
        </div>
    </div>
</body>
</html>
