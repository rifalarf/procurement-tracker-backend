<?php

namespace App\Console\Commands;

use App\Models\ProcurementItem;
use App\Models\Status;
use App\Models\StatusHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStatuses extends Command
{
    protected $signature = 'procurement:normalize-statuses 
                            {--dry-run : Preview changes without applying them}
                            {--detailed : Show detailed output}';

    protected $description = 'Migrate procurement items from legacy statuses to core statuses';

    private array $statusMapping = [];
    private array $statusIdCache = [];

    public function handle(): int
    {
        $this->info('🔄 Starting status normalization...');
        $this->newLine();

        // Load mapping from config
        $this->statusMapping = config('procurement_flow.legacy_status_mapping', []);

        if (empty($this->statusMapping)) {
            $this->error('No status mapping found in config/procurement_flow.php');
            return self::FAILURE;
        }

        // Build status ID cache
        $this->buildStatusCache();

        // Get items with legacy statuses
        $legacyStatusNames = array_keys($this->statusMapping);
        $legacyStatusIds = Status::whereIn('name', $legacyStatusNames)->pluck('id')->toArray();

        if (empty($legacyStatusIds)) {
            $this->info('✅ No legacy statuses found in database. Nothing to migrate.');
            return self::SUCCESS;
        }

        $items = ProcurementItem::whereIn('status_id', $legacyStatusIds)->get();

        if ($items->isEmpty()) {
            $this->info('✅ No procurement items with legacy statuses found. Nothing to migrate.');
            return self::SUCCESS;
        }

        $this->info("Found {$items->count()} items with legacy statuses.");
        $this->newLine();

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be applied.');
            $this->newLine();
        }

        // Preview changes
        $this->table(
            ['ID', 'No PR', 'Old Status', 'New Status'],
            $items->map(function ($item) {
                $oldStatusName = $item->status?->name ?? 'Unknown';
                $newStatusName = $this->statusMapping[$oldStatusName] ?? 'UNKNOWN';
                return [
                    $item->id,
                    $item->no_pr,
                    $oldStatusName,
                    $newStatusName,
                ];
            })->take(20)->toArray()
        );

        if ($items->count() > 20) {
            $this->info("... and " . ($items->count() - 20) . " more items");
        }
        $this->newLine();

        if ($isDryRun) {
            $this->info('Run without --dry-run to apply these changes.');
            return self::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with the migration?', true)) {
            $this->info('Migration cancelled.');
            return self::SUCCESS;
        }

        // Apply changes
        $successCount = 0;
        $errorCount = 0;

        DB::beginTransaction();

        try {
            foreach ($items as $item) {
                $oldStatusName = $item->status?->name;
                $newStatusName = $this->statusMapping[$oldStatusName] ?? null;

                if (!$newStatusName) {
                    $this->warn("⚠️  Skipping item {$item->id}: No mapping for status '{$oldStatusName}'");
                    continue;
                }

                $newStatusId = $this->statusIdCache[$newStatusName] ?? null;

                if (!$newStatusId) {
                    $this->error("❌ Error: Target status '{$newStatusName}' not found in database");
                    $errorCount++;
                    continue;
                }

                $oldStatusId = $item->status_id;

                // Update item status
                $item->status_id = $newStatusId;
                $item->save();

                // Create migration history entry
                StatusHistory::create([
                    'procurement_item_id' => $item->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $newStatusId,
                    'changed_by' => null, // System migration
                    'changed_at' => now(),
                    'notes' => "Automated migration: {$oldStatusName} → {$newStatusName}",
                    'event_type' => 'MIGRATION',
                ]);

                $successCount++;

                if ($this->option('detailed')) {
                    $this->line("  ✓ Item {$item->id} ({$item->no_pr}): {$oldStatusName} → {$newStatusName}");
                }
            }

            DB::commit();

            $this->newLine();
            $this->info("✅ Migration completed!");
            $this->info("   • Migrated: {$successCount} items");
            if ($errorCount > 0) {
                $this->warn("   • Errors: {$errorCount} items");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Migration failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function buildStatusCache(): void
    {
        $statuses = Status::all();
        foreach ($statuses as $status) {
            $this->statusIdCache[$status->name] = $status->id;
        }
    }
}
