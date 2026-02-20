<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:#f4f6f8; margin:0; padding:20px; }
        .email-wrapper { max-width:680px; margin:20px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 10px 30px rgba(16,24,40,0.06); }
        .header { background:#0f1724; color:#fff; padding:28px 32px; }
        .header h1 { margin:0; font-size:20px; }
        .content { padding:28px 32px; color:#0f1724; }
        .greeting { font-size:18px; margin-bottom:12px; }
        .muted { color:#6b7280; font-size:14px; }
        .creds { background:#f8fafc; border:1px solid #e6eef7; padding:14px; margin:18px 0; border-radius:6px; }
        .btn { display:inline-block; background:#0f1724; color:#fff; text-decoration:none; padding:12px 20px; border-radius:8px; font-weight:600; }
        .footer { padding:20px 32px; font-size:13px; color:#9ca3af; border-top:1px solid #f1f5f9; }
        .small { font-size:12px; color:#94a3b8; }
        a.inline-link { color:#0f1724; word-break:break-all; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h1>KATCHAP — Invitation</h1>
        </div>
        <div class="content">
            <div class="greeting">Hello {{ $name ?? 'there' }}!</div>
            <div class="muted">You have been invited to join <strong>KATCHAP</strong> as a {{ ucfirst(str_replace('_',' ', $role ?? 'user')) }}.</div>

            <div class="creds">
                <div style="font-weight:600; margin-bottom:6px;">Temporary credentials</div>
                <div style="font-size:14px;">Email: <strong>{{ $email }}</strong></div>
                <div style="font-size:14px;">Password: <strong>{{ $password ?? '(you will set this when activating)' }}</strong></div>
            </div>

            <p style="margin:0 0 8px 0;">You can activate your account by clicking the button below and setting a password, or copy the activation link into your browser.</p>

            <p style="margin:12px 0;">
                <a class="btn" href="{{ $activationUrl }}">Activate / Login</a>
            </p>

            <p class="small">Important: This invitation link will expire in 7 days.</p>

            <p class="small">If the button doesn't work, copy and paste this link into your browser:</p>
            <p><a class="inline-link" href="{{ $activationUrl }}">{{ $activationUrl }}</a></p>

            <p style="margin-top:18px;" class="muted">If you have any questions, please contact your administrator.</p>
            <p style="margin-top:6px; color:#374151; font-weight:600;">The KATCHAP Team</p>
        </div>
        <div class="footer">
            <div class="small">&copy; {{ date('Y') }} Katchap. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
