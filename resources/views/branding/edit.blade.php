@extends('layouts.app')
@section('title', 'Branding')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Branding</h1><p class="text-sm text-slate-500">Make the workspace look like your own company.</p></div>

<form method="post" enctype="multipart/form-data" action="{{ route('branding.update') }}" class="card card-pad max-w-3xl space-y-6">
    @csrf @method('PUT')
    @include('partials.branding-fields')
    <div class="flex justify-end"><button class="btn btn-primary">Save branding</button></div>
</form>
@endsection
