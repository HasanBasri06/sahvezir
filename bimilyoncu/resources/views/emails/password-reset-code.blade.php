<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Şifre sıfırlama kodu</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px">
        <p style="margin:0 0 24px;font-size:24px;font-weight:bold"><span style="color:#ff5743">b'</span>milyoncu</p>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.5">
            Şifreni sıfırlamak için aşağıdaki kodu giriş ekranındaki alana yaz:
        </p>
        <p style="margin:0 0 16px;font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center">{{ $code }}</p>
        <p style="margin:0;font-size:13px;line-height:1.5;color:#71717a">
            Kod {{ $minutes }} dakika geçerlidir. Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin; şifren değişmez.
        </p>
    </div>
</body>
</html>
