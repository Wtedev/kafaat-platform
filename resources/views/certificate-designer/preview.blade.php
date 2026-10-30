<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>معاينة شهادة {{ $program->title }}</title>
    <style>
        html, body { margin: 0; background: #f5f5f7; color: #335483; font-family: 'FF Shamel', ui-sans-serif, system-ui, sans-serif; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #f3f4f6; }
        header a { color: #127a7f; font-weight: 700; }
        main { padding: 16px; display: flex; justify-content: center; }
        canvas { direction: ltr; width: min(100%, 1100px); height: auto; background: #fff; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
        #certificate-preview-status { margin: 0; color: #dc2626; font-weight: 700; }
    </style>
</head>
<body>
    <header>
        <strong>{{ $program->title }}</strong>
        <p id="certificate-preview-status" role="status"></p>
        <a href="{{ $pdfUrl }}" download="certificate-preview.pdf">تنزيل PDF</a>
    </header>
    <main>
        {{-- The page is RTL, but pdf.js paints glyphs with the canvas direction. An inherited rtl direction runs the browser bidi algorithm over text the PDF already shaped, so Arabic shows reversed and disconnected while the downloaded file stays correct. --}}
        <canvas id="certificate-preview-canvas" dir="ltr"></canvas>
    </main>
    <script src="https://unpkg.com/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>
        const status = document.getElementById('certificate-preview-status');
        const canvas = document.getElementById('certificate-preview-canvas');
        const pdfjsLib = window.pdfjsLib;
        if (!pdfjsLib) {
            status.textContent = 'تعذر تحميل عارض المعاينة.';
        } else {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
            pdfjsLib.getDocument(@json($pdfUrl)).promise.then(function (pdf) {
                return pdf.getPage(1);
            }).then(function (page) {
                const viewport = page.getViewport({ scale: 1.6 });
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                const context = canvas.getContext('2d');
                context.direction = 'ltr';
                return page.render({ canvasContext: context, viewport: viewport }).promise;
            }).catch(function () {
                status.textContent = 'تعذر عرض المعاينة.';
            });
        }
    </script>
</body>
</html>
