<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class BuyerController extends Controller
{
    /**
     * List all buyers from buyers table
     * Returns buyers with their linked user_id for authorization purposes
     */
    public function index(): JsonResponse
    {
        $buyers = Buyer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'text_color', 'is_active', 'user_id']);

        return response()->json([
            'data' => $buyers,
        ]);
    }

    /**
     * Store a new buyer
     * Creates both a user account (for login) and a buyer record
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => 'required|string|min:6',
            'color' => 'nullable|string|max:7',
        ]);

        return DB::transaction(function () use ($validated) {
            // Create user account for login
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role' => 'buyer',
                'is_active' => true,
                'color' => $validated['color'] ?? '#3b82f6',
            ]);

            // Create buyer record linked to user
            $buyer = Buyer::create([
                'name' => $validated['name'],
                'color' => $validated['color'] ?? '#3b82f6',
                'is_active' => true,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Buyer created successfully',
                'data' => $buyer,
            ], 201);
        });
    }

    /**
     * Update a buyer
     */
    public function update(Request $request, Buyer $buyer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($buyer, $validated) {
            // Update buyer record
            $buyer->update($validated);

            // Also update linked user if exists
            if ($buyer->user_id && $buyer->user) {
                $buyer->user->update([
                    'name' => $validated['name'],
                    'color' => $validated['color'] ?? $buyer->user->color,
                    'is_active' => $validated['is_active'] ?? $buyer->user->is_active,
                ]);
            }
        });

        return response()->json([
            'message' => 'Buyer updated successfully',
            'data' => $buyer->fresh(),
        ]);
    }

    /**
     * Delete a buyer (soft-delete/deactivate)
     */
    public function destroy(Buyer $buyer): JsonResponse
    {
        DB::transaction(function () use ($buyer) {
            // Deactivate buyer
            $buyer->update(['is_active' => false]);

            // Also deactivate linked user if exists
            if ($buyer->user_id && $buyer->user) {
                $buyer->user->update(['is_active' => false]);
            }
        });

        return response()->json([
            'message' => 'Buyer deactivated successfully',
        ]);
    }
}
