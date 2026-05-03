<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>شهادة — {{ $certificate->certificate_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: ibmplexsansarabic, 'DejaVu Sans', sans-serif;
            background-color: #ffffff;
            color: #1a1a1a;
            direction: rtl;
            unicode-bidi: embed;
        }

        .page {
            position: relative;
            width: 100%;
            min-height: 190mm;
            padding: 16mm 20mm;
            text-align: center;
            direction: rtl;
        }

        .border-outer {
            position: absolute;
            top: 6mm;
            left: 6mm;
            right: 6mm;
            bottom: 6mm;
            border: 3px solid #1e3a5f;
        }

        .border-inner {
            position: absolute;
            top: 9mm;
            left: 9mm;
            right: 9mm;
            bottom: 9mm;
            border: 1px solid #c9a84c;
        }

        .accent-line {
            width: 60mm;
            height: 2px;
            background-color: #c9a84c;
            margin: 0 auto 5mm auto;
        }

        .org-name {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 2mm;
        }

        .org-subtitle {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 8.5pt;
            color: #888888;
            margin-bottom: 5mm;
        }

        .cert-title {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 26pt;
            font-weight: bold;
            color: #c9a84c;
            margin-bottom: 5mm;
        }

        .subtitle {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 10pt;
            color: #555555;
            margin-bottom: 6mm;
        }

        .recipient-name {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 20pt;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 1.5px solid #c9a84c;
            padding-bottom: 2mm;
            margin: 0 auto 6mm auto;
            min-width: 120mm;
            display: inline-block;
        }

        .body-text {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 10.5pt;
            color: #333333;
            text-align: center;
            line-height: 1.9;
            max-width: 200mm;
            margin: 0 auto 7mm auto;
            direction: rtl;
        }

        .entity-name {
            font-weight: bold;
            color: #1e3a5f;
        }

        .issued-date {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 9pt;
            color: #555555;
            margin-bottom: 7mm;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
            direction: rtl;
        }

        .footer-table td {
            width: 33%;
            text-align: center;
            vertical-align: top;
            padding: 0 4mm;
        }

        .footer-label {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 8pt;
            color: #888888;
            margin-bottom: 1mm;
        }

        .footer-line {
            border-top: 1px solid #cccccc;
            margin: 0 auto 1.5mm auto;
            width: 55mm;
        }

        .footer-value {
            font-family: ibmplexsansarabic, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            font-weight: bold;
        }

        .footer-code {
            font-size: 7pt;
            letter-spacing: 0.5px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="border-outer"></div>
        <div class="border-inner"></div>

        <div class="accent-line"></div>

        <div class="org-name">جمعية كفاءات</div>
        <div class="org-subtitle">للتدريب والتطوير المهني</div>

        <div class="cert-title">شهادة إتمام</div>

        <div class="subtitle">تُقدَّم هذه الشهادة إلى</div>

        <div class="recipient-name">{{ $certificate->user->name }}</div>

        <p class="body-text">
            تقديراً لإتمامه/إتمامها بنجاح
            <span class="entity-name">{{ $certificate->certificateable->title }}</span>،
            وذلك إقراراً بما أبداه/أبدته من جدٍّ واجتهاد وسعيٍ دؤوب نحو التميّز.
        </p>

        <div class="issued-date">
            تاريخ الإصدار: {{ $certificate->issued_at->format('Y/m/d') }}
        </div>

        <table class="footer-table" dir="rtl">
            <tr>
                <td>
                    <div class="footer-label">رقم الشهادة</div>
                    <div class="footer-line"></div>
                    <div class="footer-value">{{ $certificate->certificate_number }}</div>
                </td>
                <td>
                    <div class="footer-label">التوقيع المعتمد</div>
                    <div class="footer-line"></div>
                    <div class="footer-value">إدارة جمعية كفاءات</div>
                </td>
                <td>
                    <div class="footer-label">رمز التحقق</div>
                    <div class="footer-line"></div>
                    <div class="footer-value footer-code">{{ $certificate->verification_code }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
