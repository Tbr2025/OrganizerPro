<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        $query = EmailLog::recent();

        if ($search = $request->input('search')) {
            $query->where('to', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('sent_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('sent_at', '<=', $dateTo);
        }

        $emailLogs = $query->paginate(25)->appends($request->query());

        return view('backend.pages.email-logs.index', [
            'emailLogs' => $emailLogs,
            'breadcrumbs' => [
                'title' => __('Email Logs'),
            ],
        ]);
    }

    public function clear()
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        EmailLog::truncate();

        return redirect()->route('admin.email-logs.index')
            ->with('success', __('All email logs have been cleared.'));
    }
}
