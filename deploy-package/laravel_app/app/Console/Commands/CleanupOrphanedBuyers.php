<?php

namespace App\Console\Commands;

use App\Models\Buyer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedBuyers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:orphaned-buyers {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete buyer records that have no associated user (orphaned records)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning for orphaned buyer records...');
        
        // Find buyers with user_id that doesn't exist in users table or is null
        $orphanedBuyers = Buyer::whereNull('user_id')
            ->orWhereNotIn('user_id', function ($query) {
                $query->select('id')->from('users');
            })
            ->get();
        
        $count = $orphanedBuyers->count();
        
        if ($count === 0) {
            $this->info('✅ No orphaned buyer records found. Database is clean!');
            return 0;
        }
        
        $this->warn("Found {$count} orphaned buyer record(s):");
        
        // Display the orphaned records
        $tableData = $orphanedBuyers->map(function ($buyer) {
            return [
                'ID' => $buyer->id,
                'Name' => $buyer->name,
                'User ID' => $buyer->user_id ?? 'NULL',
                'Created At' => $buyer->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        })->toArray();
        
        $this->table(['ID', 'Name', 'User ID', 'Created At'], $tableData);
        
        if ($this->option('dry-run')) {
            $this->info('');
            $this->info('🔍 DRY RUN MODE: No records were deleted.');
            $this->info('Run without --dry-run flag to actually delete these records.');
            return 0;
        }
        
        if (!$this->confirm("Do you want to delete these {$count} orphaned buyer record(s)?")) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        // Delete the orphaned records
        DB::beginTransaction();
        
        try {
            $deletedCount = Buyer::whereNull('user_id')
                ->orWhereNotIn('user_id', function ($query) {
                    $query->select('id')->from('users');
                })
                ->delete();
            
            DB::commit();
            
            $this->info('');
            $this->info("✅ Successfully deleted {$deletedCount} orphaned buyer record(s).");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Failed to delete records: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
