@if (session('status'))
    <div role="status" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
@endif
@if ($errors->has('action'))
    <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first('action') }}</div>
@endif
