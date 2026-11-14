<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yol Uzerindeki Ilanlar</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:20px 22px;border-bottom:1px solid #eef2f7;background:#f0fdfa;">
                <h2 style="margin:0;font-size:22px;line-height:1.3;color:#0f766e;">Yol uzerindeki ilanlari gormeye hazir misiniz?</h2>
            </td>
        </tr>
        <tr>
            <td style="padding:22px;">
                <p style="margin:0 0 12px 0;">Merhaba {{ trim((string) ($user->full_name ?? '')) !== '' ? $user->full_name : 'Degerli Kullanicimiz' }},</p>
                <p style="margin:0 0 12px 0;">
                    Hesabinizin 7. gunundesiniz. Artik yol uzerindeki ilanlari takip ederek daha hizli is alabilirsiniz.
                </p>
                <p style="margin:14px 0;padding:12px 14px;border-radius:10px;background:#ecfeff;color:#0f766e;font-weight:700;">
                    {{ $slogan }}
                </p>
                <p style="margin:0;">
                    Hemen panele girip size uygun ilanlari inceleyin.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
