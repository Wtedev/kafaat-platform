<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'كفاءات')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['IBM Plex Sans Arabic', 'Tajawal', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet" />

    <style>
        *, *::before, *::after { font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif; }
        button:focus-visible, a:focus-visible, input:focus-visible {
            outline: 2px solid #253B5B;
            outline-offset: 3px;
            border-radius: 8px;
        }
    </style>
</head>
<body class="min-h-screen antialiased flex items-center justify-center py-12 px-4"
      style="background: linear-gradient(150deg, #EEF5FB 0%, #F3F7FB 55%, #EAF2FA 100%)">

    <div class="w-full max-w-md">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="text-3xl font-bold tracking-tight" style="color:#253B5B">كفاءات</a>
            <p class="text-sm mt-1.5" style="color:#6B7280">منصة التدريب والتطوع</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-xl border border-white/80 p-8">
            @yield('content')
        </div>

        {{-- Back to home --}}
        <div class="text-center mt-6">
            <a href="{{ route('home') }}" class="text-sm transition-colors hover:underline" style="color:#6B7280">
                ← العودة للرئيسية
            </a>
        </div>

    </div>

</body>
</html>
