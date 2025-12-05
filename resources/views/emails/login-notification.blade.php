<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="x-apple-mobile-web-app-capable" content="yes">
    <meta name="x-apple-mobile-web-app-status-bar-style" content="black">
    <meta name="x-apple-mobile-web-app-title" content="{{ config('app.name') }}">

    <title>{{ config('app.name') }}</title>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->

    <style>
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            direction: rtl !important;
            text-align: right !important;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f8f9fa !important;
            font-family: 'Tahoma', 'Arial', sans-serif !important;
            font-size: 16px !important;
            line-height: 1.6 !important;
            color: #333333 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        /* Gmail-specific RTL fix */
        u + #body .gmail-blend-screen {
            direction: rtl !important;
            text-align: right !important;
        }

        /* Container */
        .email-container {
            max-width: 600px !important;
            margin: 0 auto !important;
            background-color: #ffffff !important;
        }

        /* Header */
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            padding: 30px 20px !important;
            text-align: center !important;
        }

        .email-header h1 {
            color: #ffffff !important;
            font-size: 24px !important;
            font-weight: bold !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Content */
        .email-content {
            padding: 40px 30px !important;
            direction: rtl !important;
            text-align: right !important;
        }

        .greeting {
            font-size: 18px !important;
            color: #2d3748 !important;
            margin-bottom: 20px !important;
            font-weight: 600 !important;
            padding: 0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        .message-text {
            font-size: 16px !important;
            color: #4a5568 !important;
            margin-bottom: 15px !important;
            line-height: 1.8 !important;
            padding: 0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        /* Info box */
        .info-box {
            background-color: #f7fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 20px !important;
            margin: 20px 0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        .info-item {
            margin-bottom: 10px !important;
            padding: 8px 0 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        .info-item:last-child {
            border-bottom: none !important;
            margin-bottom: 0 !important;
        }

        .info-label {
            font-weight: 600 !important;
            color: #2d3748 !important;
            font-size: 14px !important;
            display: inline-block !important;
            width: 120px !important;
            text-align: right !important;
            direction: rtl !important;
            margin-left: 10px !important;
            margin-right: 0 !important;
        }

        .info-value {
            color: #4a5568 !important;
            font-size: 14px !important;
            font-family: 'Courier New', monospace !important;
            direction: rtl !important;
            text-align: right !important;
        }

        /* Button */
        .action-button {
            display: inline-block !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #ffffff !important;
            text-decoration: none !important;
            padding: 12px 30px !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            margin: 20px 0 !important;
            text-align: center !important;
        }

        .button-container {
            text-align: center !important;
            margin: 20px 0 !important;
        }

        /* Warning box */
        .warning-box {
            background-color: #fff5f5 !important;
            border: 1px solid #fed7d7 !important;
            border-radius: 8px !important;
            padding: 20px !important;
            margin: 20px 0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        .warning-text {
            color: #c53030 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            margin: 0 !important;
            padding: 0 !important;
            direction: rtl !important;
            text-align: right !important;
        }

        /* Footer */
        .email-footer {
            background-color: #f7fafc !important;
            padding: 30px !important;
            text-align: center !important;
            border-top: 1px solid #e2e8f0 !important;
        }

        .footer-text {
            color: #718096 !important;
            font-size: 14px !important;
            margin: 5px 0 !important;
            padding: 0 !important;
        }

        /* Responsive design */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-content {
                padding: 20px 15px !important;
            }

            .email-header {
                padding: 20px 15px !important;
            }

            .email-header h1 {
                font-size: 20px !important;
            }

            .info-label {
                display: block !important;
                width: auto !important;
                margin-bottom: 5px !important;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-container {
                background-color: #1a202c !important;
            }

            .email-content {
                background-color: #1a202c !important;
            }

            .message-text {
                color: #e2e8f0 !important;
            }

            .greeting {
                color: #f7fafc !important;
            }
        }
    </style>
</head>
<body dir="rtl" style="margin: 0 !important; padding: 0 !important; background-color: #f8f9fa !important; font-family: 'Tahoma', 'Arial', sans-serif !important; font-size: 16px !important; line-height: 1.6 !important; color: #333333 !important; direction: rtl !important; text-align: right !important;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" dir="rtl" style="direction: rtl; text-align: right;">
        <tr>
            <td align="center" style="background-color: #f8f9fa; padding: 20px 0; direction: rtl; text-align: right;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" dir="rtl" style="direction: rtl; text-align: right; max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                    <!-- Header -->
                    <tr>
                        <td class="email-header" align="center" dir="rtl" style="direction: rtl; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 20px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 24px; font-weight: bold; margin: 0; padding: 0; direction: rtl;">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="email-content" align="right" dir="rtl" style="direction: rtl; text-align: right; padding: 40px 30px;">
                            <div class="greeting" style="font-size: 18px; color: #2d3748; margin-bottom: 20px; font-weight: 600; padding: 0; direction: rtl; text-align: right;">
                                سلام! {{ $notifiable->name }}
                            </div>

                            <div class="message-text" style="font-size: 16px; color: #4a5568; margin-bottom: 15px; line-height: 1.8; padding: 0; direction: rtl; text-align: right;">
                                ورود جدیدی به حساب کاربری شما انجام شده است.
                            </div>

                            <table class="info-box" cellspacing="0" cellpadding="0" border="0" width="100%" dir="rtl" style="direction: rtl; text-align: right; background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;">
                                <tr>
                                    <td align="right" dir="rtl" style="direction: rtl; text-align: right;">
                                        <div class="info-item" style="margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; direction: rtl; text-align: right;">
                                            <span class="info-label" style="font-weight: 600; color: #2d3748; font-size: 14px; display: inline-block; width: 120px; text-align: right; direction: rtl; margin-left: 10px; margin-right: 0;">زمان ورود:</span>
                                            <span class="info-value" style="color: #4a5568; font-size: 14px; font-family: 'Courier New', monospace; direction: rtl; text-align: right;">{{ $loginTime }}</span>
                                        </div>
                                        <div class="info-item" style="margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; direction: rtl; text-align: right;">
                                            <span class="info-label" style="font-weight: 600; color: #2d3748; font-size: 14px; display: inline-block; width: 120px; text-align: right; direction: rtl; margin-left: 10px; margin-right: 0;">آدرس IP:</span>
                                            <span class="info-value" style="color: #4a5568; font-size: 14px; font-family: 'Courier New', monospace; direction: rtl; text-align: right;">{{ $ipAddress }}</span>
                                        </div>
                                        <div class="info-item" style="margin-bottom: 0; padding: 8px 0; direction: rtl; text-align: right;">
                                            <span class="info-label" style="font-weight: 600; color: #2d3748; font-size: 14px; display: inline-block; width: 120px; text-align: right; direction: rtl; margin-left: 10px; margin-right: 0;">مرورگر:</span>
                                            <span class="info-value" style="color: #4a5568; font-size: 14px; font-family: 'Courier New', monospace; direction: rtl; text-align: right;">{{ $userAgent }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <div class="button-container" style="text-align: center; margin: 20px 0;">
                                <a href="{{ config('app.frontend_url') }}/profile" class="action-button" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 16px; margin: 20px 0; text-align: center; direction: rtl;">
                                    مشاهده پروفایل
                                </a>
                            </div>

                            <div class="warning-box" style="background-color: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 20px; margin: 20px 0; direction: rtl; text-align: right;">
                                <p class="warning-text" style="color: #c53030; font-size: 14px; font-weight: 500; margin: 0; padding: 0; direction: rtl; text-align: right;">
                                    ⚠️ اگر این ورود توسط شما انجام نشده، لطفا فوراً رمز عبور خود را تغییر دهید.
                                </p>
                            </div>

                            <div class="message-text" style="font-size: 16px; color: #4a5568; margin-bottom: 15px; line-height: 1.8; padding: 0; direction: rtl; text-align: right;">
                                با تشکر از استفاده شما از پلتفرم ما!
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" align="center" dir="rtl" style="direction: rtl; background-color: #f7fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p class="footer-text" style="color: #718096; font-size: 14px; margin: 5px 0; padding: 0; direction: rtl; text-align: center;">
                                این ایمیل به صورت خودکار ارسال شده است. لطفاً به آن پاسخ ندهید.
                            </p>
                            <p class="footer-text" style="color: #718096; font-size: 14px; margin: 5px 0; padding: 0; direction: rtl; text-align: center;">
                                © {{ date('Y') }} {{ config('app.name') }}. تمامی حقوق محفوظ است.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
