<?php
namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
// use ZipArchive;

class DomainController extends Controller
{

    public function get()
    {
        return response()->json([
            'status' => 1, 'message' => 'All websites',
            'domains' => Domain::latest()->get(),
            'sub_domains' => Subdomain::latest()->get(),
        ]);
    }

    public function createSubDomain(Request $request)
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

    public function updateSubDomain(Request $request)
    {
        $request->validate([
            'project_id' => 'required|string',
            'subdomain_name' => 'nullable',
            'html_code' => 'sometimes|file|mimes:zip',
        ]);

        try {
            // Find the subdomain or throw an exception if not found
            $subdomain = Subdomain::where('project_id', $request->project_id)->first();
            if (!$subdomain) {
                return response()->json([
                    'message' => 'Subdomain not found.' . $request->project_id,
                ], 404);
            }

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
                'data' => $subdomain,
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

    public function create(Request $request)
    {
        $request->validate([
            'domain_name' => 'required|string|unique:domains,domain_name',
            'html_code' => 'required|file|mimes:zip',
        ]);
 
        $serverIP = trim(shell_exec("curl -s http://checkip.amazonaws.com"));
        if (!$serverIP) { 
            return response()->json([
                'error' => 'Error: occured on server identity',
            ], 400);
        }
 
        $domainIP = gethostbyname($request->domain_name); 
        if($serverIP !== $domainIP){
            return response()->json([
                'error' => "Sorry domain is not connected to IP yet",
            ], 400);
        }
        $domain = strtolower($request->domain_name);
        $domainName = strtolower($request->domain_name);
        $fullDomain = $domainName; // $request->is_subdomain ? "{$domainName}." . env('APP_SHORT_URL') : $domainName;
        $folderPath = "/var/www/sites/{$fullDomain}";

        try { 
            $zipFile = $request->file('html_code');
            $zipPath = $zipFile->store('temp');

            if (!$zipPath) { 
                return response()->json([
                    'error' => "Failed to store the uploaded zip file."
                ], 400);
            }

            $zipFilePath = storage_path("app/private/{$zipPath}");

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0777, true, true);
            }

            $vhostConfig = "
            <VirtualHost *:80>
                ServerName $fullDomain
                DocumentRoot $folderPath

                <Directory $folderPath>
                    AllowOverride All
                    Require all granted
                </Directory>

                ErrorLog /var/log/apache2/$fullDomain-error.log
                CustomLog /var/log/apache2/$fullDomain-access.log combined
            </VirtualHost>";

            file_put_contents("/etc/apache2/sites-available/$fullDomain.conf", $vhostConfig);
            shell_exec("a2ensite $fullDomain.conf");
            shell_exec("systemctl reload apache2");
            shell_exec("certbot --apache -d $domain --non-interactive --agree-tos -m admin@$domain");

            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath) === true) {
                if (!$zip->extractTo($folderPath)) {
                    $zip->close(); 
                    return response()->json([
                        'error' => "Failed to extract zip contents."
                    ], 400);
                }
                $zip->close();
                Storage::delete($zipPath);
                Domain::create([
                    'project_id' => uniqid(),
                    'domain_name' => $request->domain_name,
                    'folder_path' => "https://{$domain}", 
                ]);
            } else {
                return response()->json([
                    'error' => "Invalid zip file."
                ], 400); 
            }
            return response()->json([
                'message' => 'Domain created successfully.',
                'url' => "http://{$fullDomain}",
            ], 201);
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
            'domain_name' => 'required|string|unique:domains,domain_name',
            'html_code' => 'required|file|mimes:zip',
        ]);

        $domainName = strtolower($request->domain_name);
        $fullDomain = $domainName; // $request->is_subdomain ? "{$domainName}." . env('APP_SHORT_URL') : $domainName;
        $folderPath = "/var/www/sites/{$fullDomain}";

        try {
            // Step 1: Upload and Extract Zip
            $zipFile = $request->file('html_code');
            $zipPath = $zipFile->store('temp');

            if (!$zipPath) {
                throw new \Exception("Failed to store the uploaded zip file.");
            }

            $zipFilePath = storage_path("app/private/{$zipPath}");

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0777, true, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) === true) {
                if (!$zip->extractTo($folderPath)) {
                    $zip->close();
                    throw new \Exception("Failed to extract zip contents.");
                }
                $zip->close();
                Storage::delete($zipPath);
            } else {
                throw new \Exception("Invalid zip file.");
            }

            // Step 2: Configure Virtual Host (Apache)
            $vhostConfig = "
            <VirtualHost *:80>
                ServerName $fullDomain
                DocumentRoot $folderPath

                <Directory $folderPath>
                    AllowOverride All
                    Require all granted
                </Directory>

                ErrorLog /var/log/apache2/$fullDomain-error.log
                CustomLog /var/log/apache2/$fullDomain-access.log combined
            </VirtualHost>";

            file_put_contents("/etc/apache2/sites-available/$fullDomain.conf", $vhostConfig);
            shell_exec("a2ensite $fullDomain.conf");
            shell_exec("systemctl reload apache2");
            shell_exec("certbot --apache -d $domain --non-interactive --agree-tos -m admin@$domain");

            return response()->json([
                'message' => 'Domain created successfully.',
                'url' => "http://{$fullDomain}",
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function testSSL($domain)
    {
        try {
            $response = Http::get("https://$domain");
            return response()->json([
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => "SSL Test failed: " . $e->getMessage(),
            ], 500);
        }
    }

    public function regenerateSSL(Request $request)
    {
        $request->validate(['domain' => 'required|string']);

        $domain = $request->domain;

        try {
            shell_exec("certbot --apache -d $domain --non-interactive --agree-tos -m admin@$domain");
            return response()->json(['message' => "SSL regenerated successfully for $domain"]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => "SSL regeneration failed: " . $e->getMessage(),
            ], 500);
        }
    }

    public function checkDomain($domain)
    {
        try {
            $httpResponse = Http::get("https://$domain");
            $sslResponse = shell_exec("openssl s_client -connect {$domain}:443 -servername {$domain} -showcerts");

            return response()->json([
                'http_status' => $httpResponse->status(),
                'http_response' => $httpResponse->body(),
                'ssl_valid' => str_contains($sslResponse, 'Certificate chain'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => "Domain check failed: " . $e->getMessage(),
            ], 500);
        }
    }

}
