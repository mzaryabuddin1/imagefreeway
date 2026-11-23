<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Departments and projects of the signed-in client (Client Admin). */
class StructureController extends Controller
{
    use Responds;

    public function index()
    {
        $departments = Department::with(['projects' => fn ($q) => $q->withCount('fields')])->orderBy('sort')->orderBy('name')->get();
        $counts = Document::selectRaw('project_id, count(*) total, sum(status = "unindexed") pending')->groupBy('project_id')->get()->keyBy('project_id');

        return view('structure.index', compact('departments', 'counts'));
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $dept = Department::create($data + ['client_id' => $request->user()->client_id, 'sort' => (Department::max('sort') ?? 0) + 1]);
        Audit::log('department.created', $dept, "Department {$dept->name} created");

        return $this->ok('Department added.');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $old = $department->name;
        $department->update($data);
        Audit::log('department.updated', $department, "Department renamed: {$old} → {$department->name}", ['before' => $old, 'after' => $department->name]);

        return $this->ok('Department saved.');
    }

    public function destroyDepartment(Department $department)
    {
        if ($department->projects()->exists()) {
            return $this->fail('This department still has projects. Remove or move them first.');
        }
        $department->delete();
        Audit::log('department.deleted', $department, "Department {$department->name} deleted");

        return $this->ok('Department deleted.');
    }

    public function storeProject(Request $request, Department $department)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:200']);
        $project = Project::create($data + [
            'client_id' => $department->client_id,
            'department_id' => $department->id,
            'sort' => ($department->projects()->max('sort') ?? 0) + 1,
        ]);
        Audit::log('project.created', $project, "Project {$project->name} created in {$department->name}");

        return $this->ok('Project added. Open it to define its index fields.', route('projects.settings', $project));
    }
}
