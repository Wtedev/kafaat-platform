<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'كفاءات')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif']
                    }
                }
            }
        }

    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" />
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 to-gray-100 font-sans antialiased flex items-center justify-center py-12 px-4">

    <div class="w-full max-w-md">

        {{-- Logo / brand --}}
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="text-3xl font-bold text-indigo-700">كفاءات</a>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-8">
            @yield('content')
        </div>

    </div>

</body>
</html>
