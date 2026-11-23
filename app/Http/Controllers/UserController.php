<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Client;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        $viewer = $request->user();
        $users = User::visibleTo($viewer)->with('client')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($viewer->isSuperAdmin() && $request->client, fn ($q) => $q->where('client_id', $request->client))
            ->orderBy('name')->paginate(15)->withQueryString();

        $clients = $viewer->isSuperAdmin() ? Client::orderBy('name')->get() : collect();

        return view('users.index', compact('users', 'clients'));
    }

    public function create()
    {
        return view('users.form', $this->formData(new User(['status' => 'active', 'role' => 'user'])));
    }

    public function store(Request $request)
    {
        $viewer = $request->user();
        $data = $request->validate($this->rules($viewer) + ['password' => 'required|string|min:8']);
        $data = $this->scopeToViewer($viewer, $data);

        $user = User::create($data);
        Audit::log('user.created', $user, "User {$user->name} created", [], $user->client_id);
        Notice::send($user, 'Welcome!', 'Your account is ready. Take a look at the How to Use guide.', route('help'), 'party-popper', 'success');
        if ($user->client_id) {
            Notice::send(Notice::clientAdmins($user->client_id)->reject(fn ($a) => $a->id === $viewer->id), 'New user added', "{$user->name} was added as {$user->roleLabel()}.", route('users.edit', $user), 'user-plus');
        }

        return $this->ok('User created successfully.', route('users.index'));
    }

    public function edit(User $user)
    {
        $this->authorizeManage($user);

        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManage($user);
        $viewer = $request->user();
        $data = $request->validate($this->rules($viewer, $user) + ['password' => 'nullable|string|min:8']);
        $data = $this->scopeToViewer($viewer, $data);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $before = $user->only(['name', 'email', 'role', 'status', 'client_id']);

        $user->update($data);
        Audit::log('user.updated', $user, "User {$user->name} updated", ['before' => $before, 'after' => $user->only(array_keys($before))], $user->client_id);

        return $this->ok('User updated successfully.', route('users.index'));
    }

    public function status(Request $request, User $user)
    {
        $this->authorizeManage($user);
        abort_if($user->id === $request->user()->id, 403, 'You cannot change your own status.');
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'archived'])]]);

        $old = $user->status;
        $user->update(['status' => $data['status']]);
        Audit::log('user.status', $user, "User {$user->name}: {$old} → {$data['status']}", ['before' => $old, 'after' => $data['status']], $user->client_id);

        return $this->ok("User {$data['status']}.");
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeManage($user);
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        $user->delete();
        Audit::log('user.deleted', $user, "User {$user->name} deleted", [], $user->client_id);

        return $this->ok('User deleted.');
    }

    private function rules(User $viewer, ?User $user = null): array
    {
        $roles = $viewer->isSuperAdmin() ? array_keys(User::ROLES) : ['client_admin', 'user'];

        return [
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:30',
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'client_id' => [Rule::requiredIf($viewer->isSuperAdmin() && request('role') !== 'super_admin'), 'nullable', 'exists:clients,id'],
        ];
    }

    /** Client admins can only ever create users inside their own client. */
    private function scopeToViewer(User $viewer, array $data): array
    {
        if (! $viewer->isSuperAdmin()) {
            $data['client_id'] = $viewer->client_id;
        } elseif (($data['role'] ?? null) === 'super_admin') {
            $data['client_id'] = null;
        }

        return $data;
    }

    private function authorizeManage(User $target): void
    {
        $viewer = auth()->user();
        abort_unless($viewer->isSuperAdmin() || ($target->client_id === $viewer->client_id && ! $target->isSuperAdmin()), 403);
    }

    private function formData(User $user): array
    {
        $viewer = auth()->user();

        return [
            'user' => $user,
            'clients' => $viewer->isSuperAdmin() ? Client::orderBy('name')->get() : collect(),
            'roles' => $viewer->isSuperAdmin() ? User::ROLES : array_diff_key(User::ROLES, ['super_admin' => 1]),
        ];
    }
}
