@extends('antareja::layouts.auth')

@section('title', 'Setup Awal')

@section('content')
    <p class="eyebrow">Setup awal · langkah terakhir</p>
    <h1>Buat akun admin pertama</h1>
    <p class="sub">Database, layanan monitoring, dan konfigurasi sudah disiapkan otomatis. Tinggal buat akun admin untuk masuk.</p>

    @if($errors->any())
        <div class="alert" role="alert">{{ $errors->count() === 1 ? $errors->first() : 'Periksa kembali isian yang ditandai.' }}</div>
    @endif

    <form method="post" action="{{ route('antareja.setup.store') }}" novalidate>
        @csrf
        <div class="note">
            Kode setup hanya bisa dilihat di server, agar orang lain di jaringan tidak bisa membuat admin lebih dulu. Jalankan di VM:
            <pre>cd /opt/antareja &amp;&amp; ./antareja.sh setup-token</pre>
        </div>
        <div class="field">
            <label for="token">Kode setup</label>
            <input type="text" class="token" id="token" name="token" value="{{ old('token') }}" placeholder="XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required autofocus
                   @error('token') aria-invalid="true" aria-describedby="token-err" @enderror>
            @error('token')<div class="err" id="token-err">{{ $message }}</div>@enderror
        </div>

        <div class="divider"></div>

        <div class="field">
            <label for="realname">Nama lengkap</label>
            <input type="text" id="realname" name="realname" value="{{ old('realname') }}" autocomplete="name" required @error('realname') aria-invalid="true" @enderror>
            @error('realname')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="row2">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username', 'admin') }}" autocomplete="username" spellcheck="false" required @error('username') aria-invalid="true" @enderror>
                @error('username')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="email">Email <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="err">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="row2">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
            </div>
            <div class="field">
                <label for="password_confirmation">Ulangi password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>
        </div>
        @error('password')<div class="err" style="margin:-8px 0 16px">{{ $message }}</div>@else<div class="hint" style="margin:-8px 0 20px">Minimal 10 karakter, berisi huruf dan angka.</div>@enderror

        <button type="submit" class="btn">Buat admin &amp; masuk</button>
    </form>
    <p class="message">Akun ini mendapat peran <b>admin</b> (akses penuh). User lain dibuat setelah masuk, lewat menu pengaturan user.</p>
@endsection
