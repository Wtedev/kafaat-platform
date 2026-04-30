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
            font-family: DejaVu Sans, sans-serif;
            background-color: #ffffff;
            color: #1a1a1a;
            width: 297mm;
            height: 210mm;
            overflow: hidden;
            direction: rtl;
        }

        .page {
            width: 297mm;
            height: 210mm;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18mm 22mm;
            text-align: center;
            direction: rtl;
        }

        /* Decorative border */
        .border-outer {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 3px solid #1e3a5f;
        }

        .border-inner {
            position: absolute;
            top: 11mm;
            left: 11mm;
            right: 11mm;
            bottom: 11mm;
            border: 1px solid #c9a84c;
        }

        /* Golden top accent line */
        .accent-line {
            width: 60mm;
            height: 2px;
            background-color: #c9a84c;
            margin: 0 auto 5mm auto;
        }

        /* Header */
        .org-name {
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 2mm;
            letter-spacing: 1px;
        }

        .org-subtitle {
            font-size: 8.5pt;
            color: #888888;
            margin-bottom: 5mm;
        }

        .cert-title {
            font-size: 26pt;
            font-weight: bold;
            color: #c9a84c;
            margin-bottom: 5mm;
        }

        .subtitle {
            font-size: 10pt;
            color: #555555;
            margin-bottom: 6mm;
        }

        /* Recipient */
        .recipient-name {
            font-size: 20pt;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 1.5px solid #c9a84c;
            padding-bottom: 2mm;
            margin-bottom: 6mm;
            min-width: 120mm;
            display: inline-block;
        }

        /* Body text */
        .body-text {
            font-size: 10.5pt;
            color: #333333;
            text-align: center;
            line-height: 1.8;
            max-width: 200mm;
            margin-bottom: 7mm;
            direction: rtl;
        }

        .entity-name {
            font-weight: bold;
            color: #1e3a5f;
        }

        /* Issued date */
        .issued-date {
            font-size: 9pt;
            color: #555555;
            margin-bottom: 7mm;
        }

        /* Footer row */
        .footer {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            width: 100%;
            margin-top: 2mm;
            direction: rtl;
        }

        .footer-block {
            text-align: center;
            min-width: 60mm;
        }

        .footer-label {
            font-size: 8pt;
            color: #888888;
            margin-bottom: 1mm;
        }

        .footer-line {
            border-top: 1px solid #cccccc;
            margin-bottom: 1.5mm;
            width: 60mm;
        }

        .footer-value {
            font-size: 9pt;
            color: #1a1a1a;
            font-weight: bold;
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

        <div class="footer">
            <div class="footer-block">
                <div class="footer-label">رقم الشهادة</div>
                <div class="footer-line"></div>
                <div class="footer-value">{{ $certificate->certificate_number }}</div>
            </div>

            <div class="footer-block">
                <div class="footer-label">التوقيع المعتمد</div>
                <div class="footer-line"></div>
                <div class="footer-value">إدارة جمعية كفاءات</div>
            </div>

            <div class="footer-block">
                <div class="footer-label">رمز التحقق</div>
                <div class="footer-line"></div>
                <div class="footer-value" style="font-size:7pt; letter-spacing:1px;">{{ $certificate->verification_code }}</div>
            </div>
        </div>
    </div>
</body>
</html>
