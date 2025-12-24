<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add color column to users table if not exists
        if (!Schema::hasColumn('users', 'color')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('color', 7)->nullable()->after('is_active');
            });
        }

        // Step 2: Drop the old FK constraint first (check if it exists)
        try {
            Schema::table('procurement_items', function (Blueprint $table) {
                $table->dropForeign(['buyer_id']);
            });
        } catch (\Exception $e) {
            // FK may already be dropped
        }

        // Step 3: Migrate buyer data to users and build mapping
        $buyers = DB::table('buyers')->get();
        $buyerToUserMap = [];

        foreach ($buyers as $buyer) {
            // Check if user with same name exists
            $user = DB::table('users')->where('name', $buyer->name)->first();
            
            if ($user) {
                // Update existing user with color
                DB::table('users')->where('id', $user->id)->update(['color' => $buyer->color]);
                $buyerToUserMap[$buyer->id] = $user->id;
            } else {
                // Create new user for buyers without account
                $username = Str::lower(str_replace(' ', '', $buyer->name));
                
                // Check if username already exists
                if (DB::table('users')->where('username', $username)->exists()) {
                    $username = $username . $buyer->id;
                }
                
                $newUserId = DB::table('users')->insertGetId([
                    'name' => $buyer->name,
                    'username' => $username,
                    'password' => Hash::make($username),
                    'role' => 'buyer',
                    'is_active' => true,
                    'color' => $buyer->color,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $buyerToUserMap[$buyer->id] = $newUserId;
            }
        }

        // Step 4: Update procurement_items.buyer_id from old buyer IDs to new user IDs
        foreach ($buyerToUserMap as $oldBuyerId => $newUserId) {
            DB::table('procurement_items')
                ->where('buyer_id', $oldBuyerId)
                ->update(['buyer_id' => $newUserId]);
        }

        // Step 5: Add new FK constraint to users table
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        try {
            Schema::table('procurement_items', function (Blueprint $table) {
                $table->dropForeign(['buyer_id']);
            });
        } catch (\Exception $e) {}

        if (Schema::hasColumn('users', 'color')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
