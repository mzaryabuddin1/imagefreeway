<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user', 'client')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('action', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
            ->latest()->paginate(25)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
