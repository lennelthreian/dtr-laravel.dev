<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiometricPunchController extends Controller
{
    public function webhook(Request $request)
    {
        $punchTime = $request->input('PunchTime', $request->input('punch_time', now()));
        $punchState = $request->input('PunchState', $request->input('punch_state', '0'));
        $verifyType = $request->input('VerifyType', $request->input('verify_type', null));
        $terminalSn = $request->input('SN', $request->input('terminal_sn', ''));
        $terminalAlias = $request->input('terminal_alias', '');

        $punchTimestamp = $punchTime instanceof Carbon
            ? $punchTime
            : Carbon::parse($punchTime);

        $empCode = $request->input('emp_code', $request->input('PIN', $request->input('bio_id', $request->input('employee_code', ''))));

        DB::table('punch_sync_logs')->insert([
            'emp_code' => $empCode ?: null,
            'punch_time' => $punchTimestamp,
            'punch_state' => $punchState,
            'terminal_sn' => $terminalSn,
            'terminal_alias' => $terminalAlias,
            'source' => 'webhook',
            'sync_status' => 'synced',
            'sync_message' => 'Real-time push from biometric device',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response('OK', 200);
    }

    public function status(Request $request)
    {
        $lastSync = DB::table('sync_progress')
            ->where('sync_type', 'iclock_transaction')
            ->first();

        $pendingWebhooks = DB::table('punch_sync_logs')
            ->where('source', 'webhook')
            ->count();

        $totalSynced = DB::table('punch_sync_logs')->count();

        return response()->json([
            'last_synced_id' => $lastSync ? $lastSync->last_synced_id : 0,
            'last_sync_at' => $lastSync ? $lastSync->last_sync_at : null,
            'total_synced' => $totalSynced,
            'webhook_punches' => $pendingWebhooks,
        ]);
    }
}
