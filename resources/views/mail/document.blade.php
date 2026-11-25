<!DOCTYPE html>
<html>
<body style="margin:0;background:#f6f7f9;color:#1f2937;font-family:'Segoe UI',system-ui,sans-serif;line-height:1.65;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;">
                    <tr>
                        <td>
                            <img src="{{ $message->embed(public_path('brand/logo.png')) }}" alt="Original Concepts" height="48" style="height:48px;width:auto;display:block;">
                            <h1 style="font-weight:800;font-size:22px;margin:18px 0 8px;color:#1f2937;">{{ ucfirst($label) }} {{ $document->number }}</h1>
                            <p style="margin:0 0 18px;color:#6b7280;">Please find the PDF attached.</p>
                            <a href="{{ config('app.url') }}" style="display:inline-block;background:#c2410c;color:#ffffff;font-weight:700;text-decoration:none;border-radius:8px;padding:12px 18px;">Open Original Concepts Support</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
