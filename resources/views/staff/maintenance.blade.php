<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>واجهة الموظفين قيد العمل حالياً</title>
    <link rel="preload" href="{{ asset('fonts/shamel/FFShamelFamily-SansOneBook.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/staff-ui.css'])
</head>
<body class="sui-body sui-maintenance">
    <main class="sui-card sui-maintenance__card">
        <h1>واجهة الموظفين قيد العمل حالياً</h1>
        <p>نعمل على تطوير الواجهة وسنعود قريباً</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-staff-ui.button type="submit" variant="secondary">تسجيل الخروج</x-staff-ui.button>
        </form>
    </main>
</body>
</html>
