<?php

namespace App\Console\Commands;

use App\Models\IclockTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBiometricPunches extends Command
{
    protected $signature = 'dtr:sync-punches
        {--chunk=100 : Number of records to process per batch}
        {--force : Process all records regardless of sync_progress}';

    protected $description = 'Sync new punch records from ZKTeco iclock_transaction into the application';

    public function handle()
    {
        $chunkSize = (int) $this->option('chunk');
        $force = $this->option('force');

        try {
            DB::connection('zkbiotime')->getPdo();
        } catch (\Exception $e) {
            $this->error("Cannot connect to zkbiotime database: " . $e->getMessage());
            return Command::FAILURE;
        }

        $progress = DB::table('sync_progress')
            ->where('sync_type', 'iclock_transaction')
            ->first();

        if (!$progress) {
            $this->error("sync_progress entry not found. Run migrations first.");
            return Command::FAILURE;
        }

        $lastId = $force ? 0 : $progress->last_synced_id;

        $query = DB::connection('zkbiotime')
            ->table('iclock_transaction')
            ->where('id', '>', $lastId)
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            $this->info("No new punches to sync.");
            return Command::SUCCESS;
        }

        $this->info("Found {$total} new punch records to sync (last synced ID: {$lastId}).");

        $synced = 0;
        $maxId = $lastId;

        $query->chunk($chunkSize, function ($punches) use (&$synced, &$maxId) {
            $inserts = [];
            foreach ($punches as $p) {
                $empCode = $p->emp_code ?? null;
                $inserts[] = [
                    'emp_code' => $empCode,
                    'punch_time' => $p->punch_time ?? null,
                    'punch_state' => $p->punch_state ?? null,
                    'terminal_sn' => $p->terminal_sn ?? null,
                    'terminal_alias' => $p->terminal_alias ?? null,
                    'source' => ($p->source ?? ''),
                    'sync_status' => 'synced',
                    'sync_message' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ((int) $p->id > $maxId) {
                    $maxId = (int) $p->id;
                }
            }

            DB::table('punch_sync_logs')->insert($inserts);
            $synced += count($inserts);
        });

        DB::table('sync_progress')
            ->where('sync_type', 'iclock_transaction')
            ->update([
                'last_synced_id' => $maxId,
                'last_sync_at' => now(),
            ]);

        $this->info("Synced {$synced} punch records. Last synced ID: {$maxId}.");

        $event = DB::connection('zkbiotime')
            ->table('iclock_transaction')
            ->where('id', '>', $maxId)
            ->count();

        if ($event > 0) {
            $this->warn("{$event} more punches arrived during sync. Next run will pick them up.");
        }

        return Command::SUCCESS;
    }
}
