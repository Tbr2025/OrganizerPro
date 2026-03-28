<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * Display a list of all backup files.
     */
    public function index()
    {
        $backupFiles = Storage::disk('local')->files('backups');

        $backups = collect($backupFiles)
            ->filter(fn($file) => str_ends_with($file, '.sql'))
            ->sortByDesc(fn($file) => Storage::disk('local')->lastModified($file))
            ->map(function ($file) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('local')->size($file),
                    'last_modified' => Storage::disk('local')->lastModified($file),
                ];
            })
            ->values();

        return view('backend.pages.settings.backup-control', [
            'backups' => $backups,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('admin.dashboard')],
                ['name' => 'Settings', 'url' => route('admin.settings.index')],
                ['name' => 'Backups', 'url' => route('admin.backups.index'), 'active' => true],
            ],
            'tab' => 'Backups',
        ]);
    }

    /**
     * Create a new database backup.
     */
    public function create(): RedirectResponse
    {
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $fileName = 'backup-' . date('Ymd-His') . '.sql';
        $dirPath = storage_path('app/backups');
        $filePath = $dirPath . '/' . $fileName;

        // Ensure backups directory exists
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction %s > %s 2>&1',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('Database backup failed', ['output' => $output, 'code' => $returnVar]);
            return redirect()->back()->with('error', __('Backup failed. Check server logs for details.'));
        }

        return redirect()->back()->with('success', __('Backup created successfully: :file', ['file' => $fileName]));
    }

    /**
     * Download a backup file.
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $fileName = $request->get('file');

        if (!$fileName || !preg_match('/^backup-(pre-restore-)?[\d-]+\.sql$/', $fileName)) {
            return redirect()->back()->with('error', __('Invalid backup file.'));
        }

        $path = storage_path('app/backups/' . $fileName);

        if (!file_exists($path)) {
            return redirect()->back()->with('error', __('Backup file not found.'));
        }

        return response()->download($path, $fileName, ['Content-Type' => 'application/sql']);
    }

    /**
     * Restore database from a backup file.
     */
    public function restore(Request $request): RedirectResponse
    {
        $fileName = $request->input('file');

        if (!$fileName || !preg_match('/^backup-(pre-restore-)?[\d-]+\.sql$/', $fileName)) {
            return redirect()->back()->with('error', __('Invalid backup file.'));
        }

        $filePath = storage_path('app/backups/' . $fileName);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('Backup file not found.'));
        }

        // Create a safety backup before restoring
        $this->createSafetyBackup();

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $command = sprintf(
            'mysql --user=%s --password=%s --host=%s --port=%s %s < %s 2>&1',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('Database restore failed', ['file' => $fileName, 'output' => $output, 'code' => $returnVar]);
            return redirect()->back()->with('error', __('Restore failed. A safety backup was created before the attempt. Check server logs.'));
        }

        Log::info('Database restored from backup', ['file' => $fileName]);

        return redirect()->back()->with('success', __('Database restored successfully from :file. A safety backup of the previous state was created.', ['file' => $fileName]));
    }

    /**
     * Delete a backup file.
     */
    public function delete(Request $request): RedirectResponse
    {
        $fileName = $request->input('file');

        if (!$fileName || !preg_match('/^backup-(pre-restore-)?[\d-]+\.sql$/', $fileName)) {
            return redirect()->back()->with('error', __('Invalid backup file.'));
        }

        $path = 'backups/' . $fileName;

        if (!Storage::disk('local')->exists($path)) {
            return redirect()->back()->with('error', __('Backup file not found.'));
        }

        Storage::disk('local')->delete($path);

        return redirect()->back()->with('success', __('Backup deleted: :file', ['file' => $fileName]));
    }

    /**
     * Create a safety backup before restore operations.
     */
    private function createSafetyBackup(): void
    {
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $fileName = 'backup-pre-restore-' . date('Ymd-His') . '.sql';
        $filePath = storage_path('app/backups/' . $fileName);

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction %s > %s 2>&1',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::warning('Safety backup before restore failed', ['output' => $output]);
        }
    }
}
