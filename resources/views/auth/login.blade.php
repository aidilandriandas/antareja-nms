{{-- ANTAREJA NMS: halaman login bergaya ANTAREJA (form & field tetap sama dengan LibreNMS: username, password, remember, socialite) --}}
@extends('antareja::layouts.auth')

@section('title', 'Masuk')

@section('content')
    <p class="eyebrow">ANTAREJA NMS</p>
    <h1>Masuk</h1>
    <p class="sub">Gunakan akun ANTAREJA Anda.</p>

    @if($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="alert ok" role="status">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ url('login') }}" name="logonform">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username" spellcheck="false" required autofocus @if($errors->any()) aria-invalid="true" @endif>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required @if($errors->any()) aria-invalid="true" @endif>
        </div>
        <label class="check"><input type="checkbox" name="remember" id="remember"> Ingat saya di perangkat ini</label>
        <button type="submit" id="login" class="btn" name="submit">Masuk</button>
    </form>

    @foreach (\App\Facades\LibrenmsConfig::get('auth.socialite.configs', []) as $provider => $config)
        <form action="{{ route('socialite.redirect', $provider) }}" method="post">
            @csrf
            <button type="submit" class="btn secondary">Masuk dengan {{ ucfirst($provider) }}</button>
        </form>
    @endforeach

    @config('login_message')
        <p class="message">{{ \App\Facades\LibrenmsConfig::get('login_message') }}</p>
    @endconfig
@endsection
