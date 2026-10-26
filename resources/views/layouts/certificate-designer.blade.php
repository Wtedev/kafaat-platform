<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'تصميم الشهادة') — كفاءات</title>
    @vite(['resources/css/certificate-designer.css', 'resources/js/certificate-designer.js'])
</head>
<body class="certificate-designer-body">
    @yield('content')
</body>
</html>
