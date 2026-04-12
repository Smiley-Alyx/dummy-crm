<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min(200, $perPage));

        $query = User::query()->orderBy('name')->orderBy('id');

        if ($request->filled('q')) {
            $q = '%' . $request->string('q') . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', $q)->orWhere('email', 'ilike', $q);
            });
        }

        return $query->paginate($perPage);
    }
}
