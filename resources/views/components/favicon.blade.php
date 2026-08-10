{{-- The tab icon is the same file as the in app logo, hashed by Vite. --}}
@php($logo = Vite::asset('resources/logo.png'))

<link rel="icon" type="image/png" href="{{ $logo }}">
<link rel="shortcut icon" type="image/png" href="{{ $logo }}">
<link rel="apple-touch-icon" href="{{ $logo }}">
