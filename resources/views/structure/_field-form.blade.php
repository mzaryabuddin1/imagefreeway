<div><label class="label">Field name</label><input name="label" value="{{ $f->label ?? '' }}" class="input bg-white" placeholder="e.g. Invoice Number" required></div>
<div><label class="label">Type</label>
    <select name="type" class="input bg-white">@foreach(\App\Models\ProjectField::TYPES as $k => $t)<option value="{{ $k }}" @selected(($f->type ?? 'text')===$k)>{{ $t }}</option>@endforeach</select></div>
<div class="sm:col-span-2"><label class="label">Permitted values <span class="font-normal text-slate-400">(pick-list only — one per line)</span></label>
    <textarea name="options_text" rows="3" class="input bg-white" placeholder="Active&#10;Terminated">{{ isset($f) && $f?->options ? implode("\n", $f->options) : '' }}</textarea></div>
<div class="sm:col-span-2"><label class="label">Formula <span class="font-normal text-slate-400">(calculated only — use field keys, e.g. quantity * unit_price)</span></label>
    <input name="formula" value="{{ $f->formula ?? '' }}" class="input bg-white" placeholder="quantity * unit_price"></div>
<div class="sm:col-span-2 flex flex-wrap gap-x-6 gap-y-2 text-sm">
    <label class="flex items-center gap-2"><input type="checkbox" name="required" value="1" class="rounded border-slate-300" @checked($f?->required ?? false)> Required</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="searchable" value="1" class="rounded border-slate-300" @checked($f?->searchable ?? true)> Searchable</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="show_in_results" value="1" class="rounded border-slate-300" @checked($f?->show_in_results ?? true)> Show in results</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="read_only" value="1" class="rounded border-slate-300" @checked($f?->read_only ?? false)> Read-only (admins only)</label>
</div>
