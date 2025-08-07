<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplate;
use App\Models\ImageTemplateCategories;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ImageTemplateController extends Controller
{


    public function remove(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $image = $request->file('image');
        $filename = $image->hashName();
        $inputPath = storage_path('app/public/uploads/' . $filename);
        $outputFilename = 'result-' . Str::random(8) . '.png';
        $outputPath = storage_path('app/public/processed/' . $outputFilename);
        $relativePath = 'storage/processed/' . $outputFilename;

        // Ensure directories exist
        if (!file_exists(dirname($inputPath))) {
            mkdir(dirname($inputPath), 0775, true);
        }
        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        // Move the uploaded file to input path
        $image->move(dirname($inputPath), basename($inputPath));

        // Build command
        $pythonPath = base_path('venv/Scripts/python.exe');
        $scriptPath = base_path('resources/scripts/remove_bg.py');
        $command = "\"{$pythonPath}\" \"{$scriptPath}\" \"{$inputPath}\" \"{$outputPath}\"";

        try {
            Log::info("Running command: $command");

            $output = shell_exec($command);

            if (!file_exists($outputPath)) {
                throw new \Exception("Output file not generated.");
            }

            // Delete original input image
            @unlink($inputPath);

            $url = asset($relativePath);

            if ($request->ajax()) {
                return response()->json([
                    'url' => $url,
                    'path' => $relativePath,
                    'html' => '<img src="' . $url . '" alt="Processed Image" class="h-52">',
                ]);
            }

            return view('backend.remove-bg.result', compact('url'));
        } catch (\Exception $e) {
            Log::error("Remove BG failed: " . $e->getMessage());
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'Background removal failed',
                    'details' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors([
                'error' => 'Background removal failed',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function removebg()
    {
        // This method can be used to handle background removal logic if needed
        // For now, it just returns a view or a message
        return view('backend.pages.image_templates.remove_bg');
    }
    public function index()
    {
        $templates = ImageTemplate::latest()->get();
        return view('backend.pages.image_templates.index', compact('templates'));
    }

    // Show editor
    public function create()
    {
        $categories = ImageTemplateCategories::all();

        return view('backend.pages.image_templates.create', compact('categories'));
    }

    // Store layout JSON to DB
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:image_template_categories,id', // Add this
            'layout_json' => 'required|string',
            'background_image' => 'required|file|image',
            'overlay_image' => 'nullable|file|image',
        ]);

        // Save background image
        $backgroundPath = $request->file('background_image')->store('public/image_templates');

        // Save overlay image if exists
        $overlayPath = null;
        if ($request->hasFile('overlay_image')) {
            $overlayPath = $request->file('overlay_image')->store('public/image_templates');
        }

        $template = ImageTemplate::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'layout_json' => $request->layout_json,
            'background_image' => basename($backgroundPath),
            'overlay_image_path' => $overlayPath ? basename($overlayPath) : null,
        ]);

        return response()->json([
            'success' => true,
            'template' => $template
        ]);
    }









    public function show(ImageTemplate $template)
    {
        return view('backend.pages.image_templates.show', compact('template'));
    }

    public function destroy(ImageTemplate $template)
    {
        if ($template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }
        $template->delete();

        return redirect()->route('backend.pages.image_templates.index')->with('success', 'Template deleted successfully.');
    }



    public function update(Request $request, ImageTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'layout_json' => 'required',
            'background_image' => 'nullable|image|max:2048', // optional new background
        ]);

        if ($request->hasFile('background_image')) {
            $path = $request->file('background_image')->store('public/backgrounds');
            $template->background_path = str_replace('public/', 'storage/', $path);
        }

        $template->name = $request->name;
        $template->layout_json = $request->layout_json;
        $template->save();

        return response()->json(['success' => true, 'message' => 'Template updated']);
    }
}
