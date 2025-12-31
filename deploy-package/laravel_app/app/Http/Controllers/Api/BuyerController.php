<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BuyerController extends Controller
{
    /**
     * List all buyers (users with role=buyer)
     */
    public function index(): JsonResponse
    {
        $buyers = User::where('role', 'buyer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'is_active']);

        return response()->json([
            'data' => $buyers,
        ]);
    }

    /**
     * Store a new buyer (create user with role=buyer)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => 'required|string|min:6',
            'color' => 'nullable|string|max:7',
        ]);

        $buyer = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => 'buyer',
            'is_active' => true,
            'color' => $validated['color'] ?? '#3b82f6',
        ]);

        return response()->json([
            'message' => 'Buyer created successfully',
            'data' => $buyer,
        ], 201);
    }

    /**
     * Update a buyer
     */
    public function update(Request $request, User $buyer): JsonResponse
    {
        // Ensure we're updating a buyer
        if ($buyer->role !== 'buyer') {
            return response()->json([
                'message' => 'User is not a buyer',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $buyer->update($validated);

        return response()->json([
            'message' => 'Buyer updated successfully',
            'data' => $buyer,
        ]);
    }

    /**
     * Delete a buyer (soft-delete user)
     */
    public function destroy(User $buyer): JsonResponse
    {
        if ($buyer->role !== 'buyer') {
            return response()->json([
                'message' => 'User is not a buyer',
            ], 422);
        }

        // Just deactivate, don't delete
        $buyer->update(['is_active' => false]);

        return response()->json([
            'message' => 'Buyer deactivated successfully',
        ]);
    }
}
