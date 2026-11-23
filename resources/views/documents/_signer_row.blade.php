<div class="grid grid-cols-2 gap-2">
    <div><label class="label">Role</label><input name="signers[{{ $i }}][role]" class="input" list="esign-roles" placeholder="e.g. HR Manager" maxlength="60"></div>
    <div><label class="label">Pick a team member</label>
        <select class="input" name="signers[{{ $i }}][user_id]" data-signer-user>
            <option value="">— or type below —</option>
            @foreach($team as $m)<option value="{{ $m->id }}" data-name="{{ $m->name }}" data-email="{{ $m->email }}">{{ $m->name }}</option>@endforeach
        </select></div>
    <div><label class="label">Name</label><input name="signers[{{ $i }}][name]" class="input" data-signer-name maxlength="120"></div>
    <div><label class="label">Email</label><input type="email" name="signers[{{ $i }}][email]" class="input" data-signer-email maxlength="190"></div>
</div>
<button type="button" class="mt-1.5 text-xs text-red-600 hover:underline" data-remove-signer>Remove this signer</button>
