<?php
namespace App\Http\Controllers;

use App\Models\Subdomain;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SubdomainController extends Controller
{

    public function get()
    { 
        return response()->json(['status' => 1, 'message' => 'All websites', 'data' => Subdomain::latest()->get()]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'subdomain_name' => 'required|string|unique:subdomains,subdomain_name',
            'html_code' => 'required|file|mimes:zip',
        ]);

        $subdomainName = strtolower($request->subdomain_name);
        $subdomain = "{$subdomainName}." . env('APP_SHORT_URL');
        $folderPath = "subdomains/{$subdomainName}";

        try {
            $zipFile = $request->file('html_code');
            $zipPath = $zipFile->store('temp');

            if (!$zipPath) {
                throw new \Exception("Failed to store the uploaded zip file.");
            }

            $zipFilePath = storage_path("app/private/{$zipPath}");
            $extractPath = storage_path("app/{$folderPath}");
            if (!File::exists($extractPath)) {
                File::makeDirectory($extractPath, 0777, true, true);
            }
            $zip = new \ZipArchive();
            $result = $zip->open($zipFilePath);

            if ($result === true) {
                if (!$zip->extractTo($extractPath)) {
                    $zip->close();
                    throw new \Exception("Failed to extract zip contents. Check permissions or disk space.");
                }
                $zip->close();
                
                Storage::delete($zipPath);
                Subdomain::create([
                    'project_id' => uniqid(),
                    'subdomain_name' => $request->subdomain_name,
                    'folder_path' => "https://{$subdomain}", // $folderPath,
                ]);
                return response()->json([
                    'message' => 'Subdomain created successfully.',
                    'url' => "https://{$subdomain}",
                ], 201);
            } else {
                // Handle specific ZipArchive errors
                $errorMessages = [
                    \ZipArchive::ER_NOZIP => 'Not a zip archive.',
                    \ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
                    \ZipArchive::ER_CRC => 'CRC error.',
                    \ZipArchive::ER_OPEN => 'Failed to open zip file.',
                    \ZipArchive::ER_READ => 'Read error.',
                    \ZipArchive::ER_SEEK => 'Seek error.',
                    \ZipArchive::ER_MEMORY => 'Memory allocation error.',
                    \ZipArchive::ER_NOENT => 'File not found.',
                    \ZipArchive::ER_EXISTS => 'File already exists.',
                    \ZipArchive::ER_INVAL => 'Invalid argument.',
                    \ZipArchive::ER_INTERNAL => 'Internal error.',
                    \ZipArchive::ER_CHANGED => 'Entry has been changed.',
                    \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported.',
                ];

                $errorMessage = $errorMessages[$result] ?? "Unknown zip error (code: {$result}).";

                return response()->json([
                    'error' => "Failed to open zip: {$errorMessage}",
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'project_id' => 'required|string',
            'subdomain_name' => 'nullable',
            'html_code' => 'sometimes|file|mimes:zip',
        ]);

        try {
            // Find the subdomain or throw an exception if not found
            $subdomain = Subdomain::where('project_id', $request->project_id)->first();
            if(!$subdomain){
                return response()->json([
                    'message' => 'Subdomain not found.'.$request->project_id,
                ], 404);
            } 
            // // Handle subdomain name change
            // if ($request->subdomain_name && isset($request->subdomain_name)) {
            //     $newSubdomainName = strtolower($request->subdomain_name);
            //     $newFolderPath = "subdomains/{$newSubdomainName}";
            //     $oldPath = storage_path("app/{$subdomain->folder_path}");
            //     $newPath = storage_path("app/{$newFolderPath}");

            //     if (File::exists($oldPath)) {
            //         // Rename the folder
            //         File::move($oldPath, $newPath);
            //         $subdomain->folder_path = "https://{$request->subdomain_name}." . env('APP_SHORT_URL');
            //         $subdomain->subdomain_name = $request->subdomain_name; //  "{$newSubdomainName}." . env('APP_SHORT_URL');
            //     } else {
            //         return response()->json([
            //             'error' => 'Original folder does not exist.',
            //         ], 500);
            //     }
            // }

            // Handle HTML zip update
            if ($request->hasFile('html_code')) {
                $zipFile = $request->file('html_code');
                $zipPath = $zipFile->store('temp');

                if (!$zipPath) {
                    throw new \Exception("Failed to store the uploaded zip file.");
                }

                $zipFilePath = storage_path("app/{$zipPath}");
                $extractPath = storage_path("app/{$subdomain->folder_path}");

                // Ensure extraction path exists
                if (!File::exists($extractPath)) {
                    File::makeDirectory($extractPath, 0777, true, true);
                }

                // Clear existing contents before extracting the new zip
                File::cleanDirectory($extractPath);

                $zip = new \ZipArchive();
                $result = $zip->open($zipFilePath);

                if ($result === true) {
                    if (!$zip->extractTo($extractPath)) {
                        $zip->close();
                        throw new \Exception("Failed to extract zip contents. Check permissions or disk space.");
                    }

                    $zip->close();
                    Storage::delete($zipPath); // Delete the temp zip file after extraction
                } else {
                    $errorMessages = [
                        \ZipArchive::ER_NOZIP => 'Not a zip archive.',
                        \ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
                        \ZipArchive::ER_CRC => 'CRC error.',
                        \ZipArchive::ER_OPEN => 'Failed to open zip file.',
                        \ZipArchive::ER_READ => 'Read error.',
                        \ZipArchive::ER_SEEK => 'Seek error.',
                        \ZipArchive::ER_MEMORY => 'Memory allocation error.',
                        \ZipArchive::ER_NOENT => 'File not found.',
                        \ZipArchive::ER_EXISTS => 'File already exists.',
                        \ZipArchive::ER_INVAL => 'Invalid argument.',
                        \ZipArchive::ER_INTERNAL => 'Internal error.',
                        \ZipArchive::ER_CHANGED => 'Entry has been changed.',
                        \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported.',
                    ];

                    $errorMessage = $errorMessages[$result] ?? "Unknown zip error (code: {$result}).";

                    return response()->json([
                        'error' => "Failed to extract zip: {$errorMessage}",
                    ], 500);
                }
            } 
            $subdomain->save();

            return response()->json([
                'status' => 1,
                'message' => 'Subdomain updated successfully.',
                'data' => $subdomain
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Subdomain not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating subdomain: ' . $e->getMessage(),
            ], 500);
        }
    }

}
