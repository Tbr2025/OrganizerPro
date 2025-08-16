<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * Display a list of all backup files.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Define the directory to scan (relative to storage_path('app/'))
        $backupDirectory = 'public';

        // Get all files from the specified directory.
        // Storage::files() returns an array of file paths relative to the disk's root.
        // Since our 'public' disk root is storage_path('app/public'), these paths are correct.
        $backupFiles = Storage::disk('local')->files($backupDirectory); // Using 'local' disk as default, targeting 'public' directory

        // For each file, create a URL to access it (assuming storage:link is run)
        $filesWithUrls = collect($backupFiles)->map(function ($file) {
            // Get the filename from the full path
            $fileName = basename($file);
            // Construct the public URL using Storage::url()
            // Note: Storage::url() expects the path relative to the public disk root (e.g., 'public/your_file.sql')
            $url = Storage::url($file); // This will prepend '/storage/' if public disk is configured correctly

            return [
                'name' => $fileName,
                'path' => $file, // The path as returned by Storage::files()
                'url' => $url,
                'size' => Storage::size($file), // Get file size
                'last_modified' => Storage::lastModified($file), // Get last modified timestamp
            ];
        });

        // You can also get a simple list of filenames if you don't need URLs yet
        // $backupFilenames = Storage::disk('local')->directories($backupDirectory); // if you had subdirectories
        // $backupFilenames = Storage::disk('local')->allFiles($backupDirectory); // if you wanted files in subdirectories too

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('admin.dashboard')], // Assuming you have a dashboard route
            ['name' => 'Settings', 'url' => route('admin.settings.index')], // Or a general settings index route
            ['name' => 'Backups', 'url' => route('admin.backups.index'), 'active' => true],
        ];

        // ... then pass $breadcrumbs to the view ...
        return view('backend.pages.settings.backup-control', [
            'backups' => $filesWithUrls,
            'breadcrumbs' => $breadcrumbs, // Make sure to pass it
            'tab' => 'Backups', // Set the tab variable
        ]);
    }
}
