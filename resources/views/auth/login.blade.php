@extends('layouts.auth')

@section('title', 'Masuk ke Sistem')

@section('content')
<form action="{{ route('login.post') }}" method="POST" class="space-y-5">
    @csrf

    <div>
        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
        <div class="mt-1">
            <input id="username" name="username" type="text" autocomplete="username" required
                value="{{ old('username') }}"
                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                placeholder="Masukkan username Anda">
        </div>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
        <div class="mt-1">
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                placeholder="Masukkan password Anda">
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <input id="remember" name="remember" type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label for="remember" class="ml-2 block text-sm text-gray-700">Ingat sesi saya</label>
        </div>
    </div>

    <div>
        <button type="submit"
            class="flex w-full justify-center rounded-md border border-transparent bg-blue-600 py-2.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
            Masuk ke Sistem
        </button>
    </div>
</form>
@endsection
