<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::latest()->limit(200)->get();

        if ($request->wantsJson()) {
            return response()->json(['data' => $users]);
        }

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return response()->json(['data' => $user]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        return response()->json(['data' => $user], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            // hashed cast handles password mutator, keep key present only when provided
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['data' => $user]);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();
        return response()->json([], 204);
    }
}
