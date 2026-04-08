<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? \App\Models\Setting::get('app_name', 'PKS Tracking System') }}</title>

    <link rel="icon" href="{{ asset('images/logo.jpeg') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.jpeg') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
