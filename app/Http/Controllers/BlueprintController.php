<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Client;
use App\Models\SetupBlueprint;
use App\Services\BlueprintService;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Super Admin: setup blueprints (F-OPS-01). Saving under an existing name makes the next version. */
class BlueprintController extends Controller
{
    use Responds;

    public function index(BlueprintService $service)
    {
        $blueprints = SetupBlueprint::orderBy('name')->orderByDesc('version')->get()->each(fn ($b) => $b->summary = $service->summary($b->data));

        return view('blueprints.index', compact('blueprints'));
    }

    public function show(SetupBlueprint $blueprint, BlueprintService $service)
    {
        return view('blueprints.show', ['blueprint' => $blueprint, 's' => $service->summary($blueprint->data), 'clients' => Client::where('blueprint_id', $blueprint->id)->get()]);
    }

    /** Save a client's current setup as a blueprint. */
    public function store(Request $request, Client $client, BlueprintService $service)
    {
        $d = $request->validate(['name' => 'required|string|max:120', 'industry' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:1000']);
        $version = (SetupBlueprint::where('name', $d['name'])->max('version') ?? 0) + 1;
        $b = SetupBlueprint::create($d + ['version' => $version, 'data' => $service->capture($client), 'source_client_id' => $client->id, 'created_by' => $request->user()->id]);
        Audit::log('blueprint.saved', null, "Blueprint {$b->label()} saved from {$client->name}", [], $client->id);

        return $this->ok("Saved as {$b->label()}.", route('blueprints.show', $b));
    }

    public function destroy(SetupBlueprint $blueprint)
    {
        $label = $blueprint->label();
        $blueprint->delete();

        return $this->ok("{$label} was deleted. Clients already made from it are not affected.", route('blueprints'));
    }
}
