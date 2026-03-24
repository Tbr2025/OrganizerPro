<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Auth;

class EmailLogController extends Controller
{
    public function index()
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        $emailLogs = EmailLog::recent()->paginate(25);

        return view('backend.pages.email-logs.index', [
            'emailLogs' => $emailLogs,
            'breadcrumbs' => [
                'title' => __('Email Logs'),
            ],
        ]);
    }
}
