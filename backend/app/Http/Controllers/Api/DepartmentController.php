<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min(200, $perPage));

        return Department::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create($data);

        return response()->json($department, 201);
    }

    public function show(Department $department)
    {
        return $department;
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:departments,code,' . $department->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $department->fill($data)->save();

        return $department;
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->noContent();
    }
}
