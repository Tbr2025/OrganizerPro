<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        // Mark stale pending entries as failed (pending > 1 minute)
        EmailLog::where('status', EmailLog::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinute())
            ->update([
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => 'Email sending timed out (no sent confirmation received)',
            ]);

        // Stats
        $stats = [
            'total' => EmailLog::count(),
            'sent' => EmailLog::where('status', EmailLog::STATUS_SENT)->count(),
            'failed' => EmailLog::where('status', EmailLog::STATUS_FAILED)->count(),
            'bounced' => EmailLog::where('status', EmailLog::STATUS_BOUNCED)->count(),
        ];

        // Mailable types for filter dropdown
        $mailableTypes = EmailLog::whereNotNull('mailable_class')
            ->distinct()
            ->pluck('mailable_class')
            ->sort()
            ->values();

        // Group by recipient mode
        if ($request->input('group') === 'recipient') {
            $groupQuery = EmailLog::select(
                'to',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                DB::raw("SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced_count"),
                DB::raw('MAX(created_at) as latest_at')
            )->groupBy('to');

            if ($search = $request->input('search')) {
                $groupQuery->where('to', 'like', "%{$search}%");
            }

            $grouped = $groupQuery->orderByDesc('latest_at')->paginate(25)->appends($request->query());

            return view('backend.pages.email-logs.index', [
                'emailLogs' => null,
                'grouped' => $grouped,
                'stats' => $stats,
                'mailableTypes' => $mailableTypes,
                'breadcrumbs' => ['title' => __('Email Logs')],
            ]);
        }

        // Normal list mode
        $query = EmailLog::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($mailableClass = $request->input('mailable_class')) {
            $query->where('mailable_class', $mailableClass);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Sorting
        $sortable = ['to', 'subject', 'mailable_class', 'status', 'created_at'];
        $sort = $request->input('sort', 'created_at');
        $dir = $request->input('dir', 'desc');

        if (in_array($sort, $sortable) && in_array($dir, ['asc', 'desc'])) {
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $emailLogs = $query->paginate(25)->appends($request->query());

        return view('backend.pages.email-logs.index', [
            'emailLogs' => $emailLogs,
            'grouped' => null,
            'stats' => $stats,
            'mailableTypes' => $mailableTypes,
            'breadcrumbs' => ['title' => __('Email Logs')],
        ]);
    }

    public function show(EmailLog $emailLog): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        return response()->json([
            'id' => $emailLog->id,
            'to' => $emailLog->to,
            'subject' => $emailLog->subject,
            'mailable_class' => $emailLog->mailable_class,
            'mailable_short_name' => $emailLog->mailable_short_name,
            'status' => $emailLog->status,
            'error_message' => $emailLog->error_message,
            'body_html' => $emailLog->body_html,
            'retry_count' => $emailLog->retry_count,
            'is_retryable' => $emailLog->isRetryable(),
            'sent_at' => $emailLog->sent_at?->format('d M Y H:i A'),
            'created_at' => $emailLog->created_at->format('d M Y H:i A'),
        ]);
    }

    public function retry(EmailLog $emailLog): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        if (!$emailLog->isRetryable()) {
            return response()->json(['success' => false, 'message' => 'This email cannot be retried.'], 422);
        }

        try {
            Mail::html($emailLog->body_html, function ($message) use ($emailLog) {
                $message->to($emailLog->to)
                    ->subject($emailLog->subject);
            });

            $emailLog->update([
                'status' => EmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'retry_count' => $emailLog->retry_count + 1,
            ]);

            return response()->json(['success' => true, 'message' => 'Email resent successfully.']);
        } catch (\Throwable $e) {
            $emailLog->update([
                'retry_count' => $emailLog->retry_count + 1,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Retry failed: ' . $e->getMessage()], 500);
        }
    }

    public function batchRetry(Request $request): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['emaillog.view']);

        $ids = $request->validate([
            'ids' => 'required|array|max:50',
            'ids.*' => 'integer|exists:email_logs,id',
        ])['ids'];

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach (EmailLog::whereIn('id', $ids)->get() as $log) {
            if (!$log->isRetryable()) {
                $skipped++;
                continue;
            }

            try {
                Mail::html($log->body_html, function ($message) use ($log) {
                    $message->to($log->to)
                        ->subject($log->subject);
                });

                $log->update([
                    'status' => EmailLog::STATUS_SENT,
                    'sent_at' => now(),
                    'error_message' => null,
                    'retry_count' => $log->retry_count + 1,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $log->update([
                    'retry_count' => $log->retry_count + 1,
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$sent} sent, {$failed} failed, {$skipped} skipped",
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
