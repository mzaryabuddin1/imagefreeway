{{-- The questions of one form; inputs are named form[<id>][<key>] --}}
@foreach($form->fields ?? [] as $f)
    @php($name = "form[{$form->id}][{$f['key']}]")
    <div class="mb-3">
        <label class="label {{ ($f['required'] ?? false) ? 'req' : '' }}">{{ $f['label'] }}</label>
        @switch($f['type'])
            @case('textarea')<textarea name="{{ $name }}" rows="3" class="input" @required($f['required'] ?? false)></textarea>@break
            @case('number')<input type="number" step="any" name="{{ $name }}" class="input" @required($f['required'] ?? false)>@break
            @case('date')<input type="date" name="{{ $name }}" class="input" @required($f['required'] ?? false)>@break
            @case('checkbox')<label class="flex items-center gap-2 text-sm"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" class="rounded border-slate-300"> Yes</label>@break
            @case('select')<select name="{{ $name }}" class="input" @required($f['required'] ?? false)><option value="">—</option>@foreach($f['options'] ?? [] as $o)<option>{{ $o }}</option>@endforeach</select>@break
            @case('signature')
                <div data-sigpad class="sigpad"><canvas></canvas><input type="hidden" name="{{ $name }}"><button type="button" data-sig-clear class="mt-1 text-xs text-slate-500 hover:underline">Clear</button></div>@break
            @default<input name="{{ $name }}" class="input" @required($f['required'] ?? false)>
        @endswitch
    </div>
@endforeach
