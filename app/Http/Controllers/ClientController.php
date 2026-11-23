<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Client;
use App\Models\SetupBlueprint;
use App\Models\User;
use App\Services\BlueprintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Super Admin only: create, brand, switch modules on and off, suspend and archive clients. */
class ClientController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        $clients = Client::withCount('users')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('domain', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(12)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'admin_name' => 'required|string|max:120',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'blueprint_id' => 'nullable|exists:blueprints,id',
        ]);
        $blueprint = ! empty($data['blueprint_id']) ? SetupBlueprint::find($data['blueprint_id']) : null;

        // Provisioning is all or nothing (F-OPS-02): a failure part-way leaves no client behind
        $logo = null;
        try {
            [$client, $admin] = DB::transaction(function () use ($data, $request, $blueprint, &$logo) {
                $attrs = $this->clientData($data);
                if ($blueprint) {
                    // modules ticked on this form are added to the ones the blueprint switches on
                    $attrs['modules'] = array_merge($blueprint->data['modules'] ?? [], array_filter($attrs['modules']));
                    $attrs['blueprint_id'] = $blueprint->id;
                }
                $client = Client::create($attrs);
                $this->storeLogo($request, $client);
                $logo = $client->logo_path;
                $admin = User::create([
                    'client_id' => $client->id, 'name' => $data['admin_name'], 'email' => $data['admin_email'],
                    'password' => $data['admin_password'], 'role' => 'client_admin',
                ]);
                $blueprint && app(BlueprintService::class)->apply($client, $blueprint->data);

                return [$client, $admin];
            });
        } catch (\Throwable $e) {
            $logo && Storage::disk(config('filesystems.documents_disk'))->delete($logo);
            report($e);

            return $this->fail('The client could not be created, so nothing was saved. '.($blueprint ? 'The blueprint could not be applied: ' : '').Str::limit($e->getMessage(), 160));
        }

        Audit::log('client.created', $client, "Client {$client->name} created", [], $client->id);
        Notice::send(Notice::superAdmins(), 'New client created', "{$client->name} is ready. Invite its team.", route('clients.edit', $client), 'building-2', 'success');
        Notice::send($admin, 'Welcome to '.($client->app_name ?: $client->name), 'Your workspace is ready. Start by adding your users.', route('dashboard'), 'party-popper', 'success');

        return $this->ok('Client created successfully.', route('clients.index'));
    }

    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate($this->rules($client));
        $before = $client->only(['name', 'domain', 'status', 'modules', 'primary_color', 'secondary_color']);
        $client->update($this->clientData($data));
        $this->storeLogo($request, $client);

        Audit::log('client.updated', $client, "Client {$client->name} updated", ['before' => $before, 'after' => $client->only(array_keys($before))], $client->id);

        return $this->ok('Client updated successfully.', route('clients.index'));
    }

    public function status(Request $request, Client $client)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'archived'])]]);
        $old = $client->status;
        $client->update(['status' => $data['status']]);

        Audit::log('client.status', $client, "Client {$client->name}: {$old} → {$data['status']}", ['before' => $old, 'after' => $data['status']], $client->id);
        Notice::send(Notice::superAdmins(), 'Client status changed', "{$client->name} is now {$data['status']}.", route('clients.edit', $client), 'building-2', $data['status'] === 'active' ? 'success' : 'warning');

        return $this->ok("Client {$data['status']}.");
    }

    public function destroy(Client $client)
    {
        $client->delete();
        Audit::log('client.deleted', $client, "Client {$client->name} deleted", [], $client->id);

        return $this->ok('Client deleted.', route('clients.index'));
    }

    private function rules(?Client $client = null): array
    {
        return [
            'name' => 'required|string|max:150',
            'slug' => ['required', 'alpha_dash', 'max:60', Rule::unique('clients', 'slug')->ignore($client?->id)],
            'domain' => ['nullable', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/i', Rule::unique('clients', 'domain')->ignore($client?->id)],
            'status' => ['required', Rule::in(['active', 'suspended', 'archived'])],
            'app_name' => 'nullable|string|max:80',
            'primary_color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'footer_text' => 'nullable|string|max:200',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'modules' => 'nullable|array',
        ];
    }

    private function clientData(array $data): array
    {
        $modules = [];
        foreach (array_keys(Client::MODULES) as $key) {
            $modules[$key] = isset($data['modules'][$key]);
        }

        $out = collect($data)
            ->only(['name', 'slug', 'domain', 'status', 'app_name', 'primary_color', 'secondary_color', 'footer_text'])
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all();
        $out['domain'] = filled($out['domain'] ?? null) ? strtolower($out['domain']) : null;
        $out['modules'] = $modules;

        return $out;
    }

    private function storeLogo(Request $request, Client $client): void
    {
        $disk = Storage::disk(config('filesystems.documents_disk'));

        if (($request->boolean('remove_logo') || $request->hasFile('logo')) && $client->logo_path) {
            $disk->delete($client->logo_path);
            $client->update(['logo_path' => null]);
        }
        if ($request->hasFile('logo')) {
            $client->update(['logo_path' => $disk->putFile("clients/{$client->id}/branding", $request->file('logo'))]);
        }
    }
}
