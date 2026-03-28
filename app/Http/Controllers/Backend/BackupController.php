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
        $fileName = 'backup-' . date('Ymd-His') . '.sql';
        $filePath = storage_path('app/backups/' . $fileName);

        $this->ensureBackupDir();

        $command = $this->buildDumpCommand($filePath);

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

        $command = $this->buildImportCommand($filePath);

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
        $filePath = storage_path('app/backups/backup-pre-restore-' . date('Ymd-His') . '.sql');
        $command = $this->buildDumpCommand($filePath);

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::warning('Safety backup before restore failed', ['output' => $output]);
        }
    }

    /**
     * Find the mysqldump binary path.
     */
    private function findMysqlDump(): string
    {
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fall back to PATH lookup
        $which = trim((string) shell_exec('which mysqldump 2>/dev/null'));
        return $which ?: 'mysqldump';
    }

    /**
     * Find the mysql binary path.
     */
    private function findMysql(): string
    {
        foreach (['/usr/bin/mysql', '/usr/local/bin/mysql', '/usr/local/mysql/bin/mysql'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $which = trim((string) shell_exec('which mysql 2>/dev/null'));
        return $which ?: 'mysql';
    }

    /**
     * Build the mysqldump command string.
     */
    private function buildDumpCommand(string $outputPath): string
    {
        return sprintf(
            '%s --user=%s --password=%s --host=%s --port=%s --single-transaction %s > %s 2>&1',
            $this->findMysqlDump(),
            escapeshellarg(config('database.connections.mysql.username')),
            escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.host', '127.0.0.1')),
            escapeshellarg(config('database.connections.mysql.port', '3306')),
            escapeshellarg(config('database.connections.mysql.database')),
            escapeshellarg($outputPath)
        );
    }

    /**
     * Build the mysql import command string.
     */
    private function buildImportCommand(string $inputPath): string
    {
        return sprintf(
            '%s --user=%s --password=%s --host=%s --port=%s %s < %s 2>&1',
            $this->findMysql(),
            escapeshellarg(config('database.connections.mysql.username')),
            escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.host', '127.0.0.1')),
            escapeshellarg(config('database.connections.mysql.port', '3306')),
            escapeshellarg(config('database.connections.mysql.database')),
            escapeshellarg($inputPath)
        );
    }

    /**
     * Ensure the backups directory exists.
     */
    private function ensureBackupDir(): void
    {
        $dirPath = storage_path('app/backups');
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
    }
}
