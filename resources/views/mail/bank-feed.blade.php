<!DOCTYPE html>
<html>
<body style="margin:0;background:#f6f7f9;color:#1f2937;font-family:'Segoe UI',system-ui,sans-serif;line-height:1.65;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;">
                <tr><td>
                    <img src="{{ $message->embed(public_path('brand/logo.png')) }}" alt="Original Concepts" height="48" style="height:48px;width:auto;display:block;">
                    <h1 style="font-size:22px;margin:18px 0 8px;">Bank feed</h1>
                    <p style="color:#6b7280;">{{ $allocated }} transaction{{ $allocated === 1 ? '' : 's' }} allocated automatically. {{ $pending }} still need a look.</p>
                    <a href="{{ url('/banking/review') }}" style="display:inline-block;background:#c2410c;color:#ffffff;font-weight:700;text-decoration:none;border-radius:8px;padding:12px 18px;">Review transactions</a>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
