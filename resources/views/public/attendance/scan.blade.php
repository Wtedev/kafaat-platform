<!doctype html>
<html lang="ar-SA-u-nu-latn" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مسح التحضير — {{ $link->name }}</title>
    @vite(['resources/css/staff-ui.css'])
</head>
<body class="sui-scan-page">
    <main class="sui-scan">
        <header class="sui-scan__head">
            <p class="sui-scan__program">{{ $link->program?->title }}</p>
            <h1>{{ $link->name }}</h1>
            <p class="sui-scan__count">الحاضرون: <strong data-scan-count>{{ en_num($count) }}</strong></p>
        </header>

        @if (! $open)
            <section class="sui-card sui-scan__closed">
                <h2>التحضير غير مفتوح الآن</h2>
                <p>اطلب من فريق البرنامج فتح التحضير لهذا اليوم، ثم حدّث الصفحة.</p>
                <button type="button" class="sui-btn sui-btn--primary sui-btn--md" onclick="location.reload()">تحديث</button>
            </section>
        @else
            <p class="sui-scan__until">مفتوح حتى {{ $link->closes_at?->timezone(config('app.timezone'))->format('H:i') }}</p>
            <section class="sui-card sui-scan__camera">
                <div id="reader" class="sui-scan__reader"></div>
                <p class="sui-scan__hint" data-camera-error hidden></p>
            </section>

            <div class="sui-scan__result" data-result hidden role="status" aria-live="polite">
                <strong data-result-name></strong>
                <span data-result-message></span>
            </div>

            <form class="sui-card sui-scan__manual" data-manual>
                <label class="sui-field__label" for="pass">إدخال الرمز يدوياً</label>
                <div class="sui-scan__manual-row">
                    <input id="pass" class="sui-control" name="pass" placeholder="KAFAAT-P…-R…" dir="ltr" autocomplete="off">
                    <button type="submit" class="sui-btn sui-btn--secondary sui-btn--md">تحضير</button>
                </div>
            </form>
        @endif
    </main>

    @if ($open)
    <script>
    (() => {
        const url = @json(route('gate.link-scan.store', $link->token));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const box = document.querySelector('[data-result]');
        const count = document.querySelector('[data-scan-count]');
        const cameraError = document.querySelector('[data-camera-error]');
        let busy = false;
        let last = '';
        let lastAt = 0;

        const show = (ok, name, message) => {
            box.hidden = false;
            box.classList.toggle('is-ok', ok);
            box.classList.toggle('is-error', !ok);
            box.querySelector('[data-result-name]').textContent = name || '';
            box.querySelector('[data-result-message]').textContent = message || '';
            if (navigator.vibrate) navigator.vibrate(ok ? 80 : [60, 60, 60]);
        };

        const submit = async (pass) => {
            const now = Date.now();
            if (busy || (pass === last && now - lastAt < 4000)) return;
            busy = true; last = pass; lastAt = now;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ pass }),
                });
                const data = await response.json().catch(() => ({}));
                show(Boolean(data.ok), data.name, data.message || 'تعذّر التحضير.');
                if (typeof data.count === 'number') count.textContent = data.count;
            } catch (e) {
                show(false, '', 'تعذّر الاتصال. حاول مرة أخرى.');
            } finally {
                busy = false;
            }
        };

        document.querySelector('[data-manual]').addEventListener('submit', (event) => {
            event.preventDefault();
            const input = event.target.querySelector('input');
            if (input.value.trim()) submit(input.value.trim());
            input.value = '';
        });

        const fail = (message) => { cameraError.hidden = false; cameraError.textContent = message; };
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = () => {
            const scanner = new Html5Qrcode('reader');
            Html5Qrcode.getCameras().then((cameras) => {
                if (!cameras || cameras.length === 0) return fail('لا توجد كاميرا على هذا الجهاز. استخدم الإدخال اليدوي.');
                const rear = cameras.find((c) => /back|rear|خلف/i.test(c.label)) || cameras[cameras.length - 1];
                return scanner.start(rear.id, { fps: 8, qrbox: { width: 240, height: 240 }, aspectRatio: 1.0 }, (decoded) => submit(decoded), () => {});
            }).catch(() => fail('اسمح باستخدام الكاميرا للمتابعة.'));
        };
        script.onerror = () => fail('تعذّر تحميل ماسح الكاميرا. استخدم الإدخال اليدوي.');
        document.head.appendChild(script);
    })();
    </script>
    @endif
</body>
</html>
