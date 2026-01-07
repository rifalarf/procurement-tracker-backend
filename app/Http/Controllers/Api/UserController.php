<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List all users with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['departments', 'buyer']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single user
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($user->load(['departments', 'buyer'])),
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => ['required', 'string', Password::min(8)],
            'role' => 'required|in:admin,buyer,avp,staff',
            'department_ids' => 'array',
            'department_ids.*' => 'exists:departments,id',
            'buyer_color' => 'nullable|string|max:7',
            'buyer_text_color' => 'nullable|string|max:7',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        // Attach departments
        if (!empty($validated['department_ids'])) {
            $user->departments()->attach($validated['department_ids']);
        }

        // Create or update buyer record for buyer role
        if ($validated['role'] === 'buyer') {
            Buyer::create([
                'name' => $validated['name'],
                'user_id' => $user->id,
                'color' => $validated['buyer_color'] ?? '#e8eaed',
                'text_color' => $validated['buyer_text_color'] ?? '#ffffff',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user->load(['departments', 'buyer'])),
        ], 201);
    }

    /**
     * Update a user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'password' => ['nullable', 'string', Password::min(8)],
            'role' => 'required|in:admin,buyer,avp,staff',
            'is_active' => 'boolean',
            'department_ids' => 'array',
            'department_ids.*' => 'exists:departments,id',
            'buyer_color' => 'nullable|string|max:7',
            'buyer_text_color' => 'nullable|string|max:7',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'role' => $validated['role'],
        ];

        if (isset($validated['is_active'])) {
            $updateData['is_active'] = $validated['is_active'];
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync departments
        if (isset($validated['department_ids'])) {
            $user->departments()->sync($validated['department_ids']);
        }

        // Update buyer colors if role is buyer
        if ($validated['role'] === 'buyer') {
            $buyer = $user->buyer;
            if ($buyer) {
                $buyerUpdate = ['name' => $validated['name']];
                if (isset($validated['buyer_color'])) {
                    $buyerUpdate['color'] = $validated['buyer_color'];
                }
                if (isset($validated['buyer_text_color'])) {
                    $buyerUpdate['text_color'] = $validated['buyer_text_color'];
                }
                $buyer->update($buyerUpdate);
            } else {
                // Create buyer if doesn't exist
                Buyer::create([
                    'name' => $validated['name'],
                    'user_id' => $user->id,
                    'color' => $validated['buyer_color'] ?? '#e8eaed',
                    'text_color' => $validated['buyer_text_color'] ?? '#ffffff',
                    'is_active' => true,
                ]);
            }
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user->load(['departments', 'buyer'])),
        ]);
    }

    /**
     * Delete a user
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $user->departments()->detach();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
