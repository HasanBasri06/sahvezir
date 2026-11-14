<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'TaşıBul')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; max-width: 100% !important; }
            .content-cell { padding: 20px 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; line-height: 1.6; color: #374151; background-color: #f3f4f6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" class="wrapper" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; margin: 0 auto;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 0 0 24px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #39838C; border-radius: 12px 12px 0 0;">
                                <tr>
                                    <td align="center" style="padding: 28px 24px;">
                                        <span style="font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">TaşıBul</span>
                                        <p style="margin: 6px 0 0 0; font-size: 13px; color: rgba(255,255,255,0.9);">Lojistik Platformu</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Content card -->
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);">
                            <table role="presentation" class="content" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td class="content-cell" style="padding: 32px 28px;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 28px 24px 16px;">
                            <p style="margin: 0; font-size: 13px; color: #9ca3af;">Bu e-posta TaşıBul tarafından gönderilmiştir.</p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #9ca3af;">© {{ date('Y') }} TaşıBul. Tüm hakları saklıdır.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
