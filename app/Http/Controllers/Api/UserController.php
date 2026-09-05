<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', new StrongPassword()],
            'role' => 'required|in:admin,staff',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'User berhasil ditambahkan',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user)
    {
        $user->load('borrowings');

        return (new UserResource($user))
            ->additional([
                'success' => true,
            ]);
    }

    public function update(Request $request, User $user)
    {
        /** @var User&\stdClass $userWithId */
        $userWithId = $user;
        /** @var int $userId */
        $userId = (int) $userWithId->id;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => ['nullable', 'string', new StrongPassword()],
            'role' => 'required|in:admin,staff',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'User berhasil diperbarui',
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(User $user)
    {
        /** @var User&\stdClass $userWithId */
        $userWithId = $user;
        /** @var int $userId */
        $userId = (int) $userWithId->id;
        
        // Prevent deleting yourself
        if ($userId === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus user sendiri'
            ], 400);
        }

        return DB::transaction(function () use ($user) {
            // Prevent deleting user with active borrowings
            $hasActiveBorrowings = $user->borrowings()
                ->whereIn('status', ['dipinjam', 'terlambat'])
                ->lockForUpdate()
                ->exists();

            if ($hasActiveBorrowings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus user yang masih memiliki peminjaman aktif'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User berhasil dihapus'
            ]);
        });
    }
}
