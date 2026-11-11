<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives browser-side diagnostics from client pages.
 *
 * Used to investigate the OT Entry "Manage Transactions" popup going blank on
 * some clients' machines, which cannot be reproduced locally. The browser
 * reports what it saw at the moment the popup emptied (browser, extensions,
 * translation, recent Livewire requests, JS errors) and it is written to
 * storage/logs/ot-modal-blank.log.
 */
class ClientDiagnosticsController extends Controller
{
    public function otModalBlank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report' => ['required', 'array'],
        ]);

        // Guard the log against oversized payloads.
        $report = json_encode($data['report'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($report) > 60000) {
            $report = substr($report, 0, 60000).'…[truncated]';
        }

        $user = $request->user();

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/ot-modal-blank.log'),
        ])->warning('OT Entry: Manage Transactions popup went blank', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'clab_no' => $user?->contractor_clab_no,
            'ip' => $request->ip(),
            'server_user_agent' => $request->userAgent(),
            'report' => $report,
        ]);

        return response()->json(['ok' => true]);
    }
}
