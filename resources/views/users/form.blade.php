@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
<div class="mb-6"><a href="{{ route('users.index') }}" class="text-sm text-brand hover:underline">← Users</a>
    <h1 class="mt-1 text-2xl font-bold">{{ $user->exists ? 'Edit '.$user->name : 'New user' }}</h1></div>

<form method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card card-pad max-w-3xl space-y-5">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label">Full name</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required autofocus></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required></div>
        <div><label class="label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
        <div><label class="label">{{ $user->exists ? 'New password' : 'Password' }}</label>
            <input type="password" name="password" class="input" minlength="8" autocomplete="new-password" {{ $user->exists ? '' : 'required' }} placeholder="{{ $user->exists ? 'Leave blank to keep current' : '' }}"></div>
        <div><label class="label">Role</label>
            <select name="role" class="input">@foreach($roles as $k => $label)<option value="{{ $k }}" @selected(old('role', $user->role)===$k)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="label">Status</label>
            <select name="status" class="input">@foreach(['active','inactive','archived'] as $s)<option value="{{ $s }}" @selected(old('status', $user->status)===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        @if(auth()->user()->isSuperAdmin())
            <div class="sm:col-span-2"><label class="label">Client <span class="font-normal text-slate-400">(leave empty for a platform Super Admin)</span></label>
                <select name="client_id" class="input"><option value="">— Platform —</option>
                    @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $user->client_id)==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
        @endif
    </div>
    <div class="flex justify-end gap-3">
        <a href="{{ route('users.index') }}" class="btn btn-ghost">Cancel</a>
        <button class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
    </div>
</form>
@endsection
