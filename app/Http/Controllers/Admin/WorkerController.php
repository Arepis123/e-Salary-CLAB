<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;

class WorkerController extends Controller
{
    public function show($workerId)
    {
        // Get worker from the worker_db database with all relationships
        $worker = Worker::with(['country', 'workTrade', 'contracts.contractor', 'activeContract'])
            ->where('wkr_id', $workerId)
            ->firstOrFail();

        // Get the active contract
        $contract = $worker->activeContract;

        // Get all contracts ordered by start date (most recent first)
        $contractHistory = $worker->contracts()
            ->with('contractor')
            ->orderBy('con_start', 'desc')
            ->get();

        return view('admin.workers.show', compact('worker', 'contract', 'contractHistory'));
    }
}
