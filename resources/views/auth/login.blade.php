@extends('layouts.guest')

@section('content')
@php($b = \App\Support\Branding::current())
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between bg-gradient-to-b from-side to-side-dark p-12 text-white lg:flex">
        <div class="inline-flex h-12 w-fit items-center rounded-xl bg-white px-4">
            <img src="{{ $b->logoUrl }}" alt="{{ $b->name }}" class="max-h-8 w-auto max-w-[11rem]">
        </div>
        <div class="max-w-md">
            <h1 class="text-3xl font-bold leading-tight">Documents, workflows and signatures in one secure place.</h1>
            <p class="mt-4 text-white/75">Capture, index, search and approve — with an audit trail on everything.</p>
        </div>
        <p class="text-xs text-white/60">{{ $b->footer }}</p>
    </div>

    <div class="flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex h-12 w-fit items-center rounded-xl bg-white px-4 shadow-sm lg:hidden">
                <img src="{{ $b->logoUrl }}" alt="{{ $b->name }}" class="max-h-8 w-auto max-w-[11rem]">
            </div>
            <h2 class="text-2xl font-bold">Sign in</h2>
            <p class="mt-1 text-sm text-slate-500">Welcome to {{ $b->name }}. Enter your details to continue.</p>

            {{-- Errors appear here and under the field, like a standard form (no popup) --}}
            <form method="post" action="{{ url('/login') }}" data-no-confirm data-inline class="mt-8 space-y-5">
                @csrf
                <div data-form-error role="alert" class="{{ $errors->any() ? '' : 'hidden ' }}rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">{{ $errors->first() }}</div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="username" required autofocus>
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" class="input {{ $errors->any() ? 'border-red-500' : '' }}" autocomplete="current-password" required>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Keep me signed in
                </label>
                <button type="submit" class="btn btn-primary w-full">Sign in</button>
            </form>
        </div>
    </div>
</div>
@endsection
