<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

class FileUploadController extends Controller
{
    protected $s3Client;
    protected $baseUrl;

    public function __construct()
    {
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region'  => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'endpoint' => 'https://s3.storage.selcloud.ru',
            'http' => [
                'verify' => false,
            ],
            'use_path_style_endpoint' => true,
        ]);

        $this->baseUrl = 'https://36244fdd-bfff-4e22-8f16-74197dd951ff.selstorage.ru';
    }

    public function upload(Request $request)
    {
        if (!$request->hasFile('files')) {
            return response()->json(['error' => 'No files provided'], 400);
        }

        $fileUrls = [];

        try {
            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();

                $this->s3Client->putObject([
                    'Bucket' => env('AWS_BUCKET'),
                    'Key'    => $filename,
                    'Body'   => fopen($file->getPathname(), 'rb'),
                    'ACL'    => 'public-read',
                ]);

                $fileUrls[] = $this->baseUrl . '/' . $filename;
            }

            Log::info('Files uploaded to S3:', ['paths' => $fileUrls]);

            return response()->json(['paths' => $fileUrls], 200);

        } catch (\Exception $e) {
            Log::error('File upload error:', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'File upload failed', 'details' => $e->getMessage()], 500);
        }
    }
}
