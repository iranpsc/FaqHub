<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\SecureImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Allowed image MIME types
     */
    protected array $allowedImageMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Allowed document MIME types
     */
    protected array $allowedDocumentMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    public function __construct()
    {
        // Optional authentication is handled at route level
    }

    /**
     * Upload image for TinyMCE editor
     */
    public function uploadTinyMCEImage(Request $request)
    {
        // Use SecureImage rule for robust validation
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120', // Max 5MB
                new SecureImage(
                    allowedMimeTypes: $this->allowedImageMimes,
                    maxPixelCount: 25_000_000, // Max 25 megapixels
                    maxWidth: 4096,
                    maxHeight: 4096
                ),
            ],
        ], [
            'file.required' => 'لطفا یک تصویر انتخاب کنید',
            'file.file' => 'فایل انتخابی معتبر نیست',
            'file.max' => 'حجم تصویر نباید بیشتر از 5 مگابایت باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'خطا در اعتبارسنجی: ' . $validator->errors()->first()
            ], 422);
        }

        try {
            $image = $request->file('file');

            // Generate secure unique filename (avoid using original name)
            $filename = $this->generateSecureFilename($image->getClientOriginalExtension());

            // Store the image in the 'editor-images' directory
            $path = $image->storeAs('editor-images', $filename, 'public');

            // Generate the full URL
            $fullUrl = asset('storage/' . $path);

            // Return the location as expected by TinyMCE
            return response()->json([
                'location' => $fullUrl
            ], 200, [
                'Content-Type' => 'application/json'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'خطا در آپلود تصویر'
            ], 500);
        }
    }

    /**
     * Upload image for Quill editor
     */
    public function uploadQuillImage(Request $request)
    {
        // Use SecureImage rule for robust validation
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120', // Max 5MB
                new SecureImage(
                    allowedMimeTypes: $this->allowedImageMimes,
                    maxPixelCount: 25_000_000,
                    maxWidth: 4096,
                    maxHeight: 4096
                ),
            ],
        ], [
            'file.required' => 'لطفا یک تصویر انتخاب کنید',
            'file.file' => 'فایل انتخابی معتبر نیست',
            'file.max' => 'حجم تصویر نباید بیشتر از 5 مگابایت باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'خطا در اعتبارسنجی: ' . $validator->errors()->first()
            ], 422);
        }

        try {
            $image = $request->file('file');

            // Generate secure unique filename
            $filename = $this->generateSecureFilename($image->getClientOriginalExtension());

            // Store the image in the 'editor-images' directory
            $path = $image->storeAs('editor-images', $filename, 'public');

            // Generate the full URL
            $fullUrl = asset('storage/' . $path);

            return response()->json([
                'location' => $fullUrl
            ], 200, [
                'Content-Type' => 'application/json'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'خطا در آپلود تصویر'
            ], 500);
        }
    }

    /**
     * Upload general file
     */
    public function uploadFile(Request $request)
    {
        $type = $request->input('type', 'general');

        // Build validation rules based on type
        $rules = $this->getValidationRulesForType($type);

        $validator = Validator::make($request->all(), [
            'file' => $rules,
            'type' => 'sometimes|string|in:document,image',
        ], [
            'file.required' => 'لطفا یک فایل انتخاب کنید',
            'file.file' => 'فایل انتخابی معتبر نیست',
            'file.max' => 'حجم فایل بیش از حد مجاز است',
            'file.mimetypes' => 'نوع فایل مجاز نیست',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Generate secure unique filename
            $filename = $this->generateSecureFilename($file->getClientOriginalExtension());

            // Determine storage directory based on type
            $directory = match($type) {
                'image' => 'images',
                'document' => 'documents',
                default => 'files'
            };

            // Store the file
            $path = $file->storeAs($directory, $filename, 'public');
            $fullUrl = asset('storage/' . $path);

            return response()->json([
                'message' => 'فایل با موفقیت آپلود شد',
                'file_url' => $fullUrl,
                'file_path' => $path,
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در آپلود فایل'
            ], 500);
        }
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => [
                'required',
                'string',
                'max:255',
                // Prevent path traversal attacks
                function ($attribute, $value, $fail) {
                    if (str_contains($value, '..') || str_contains($value, '//')) {
                        $fail('مسیر فایل نامعتبر است');
                    }
                    // Only allow deletion from specific directories
                    $allowedPrefixes = ['editor-images/', 'images/', 'documents/', 'files/'];
                    $isAllowed = false;
                    foreach ($allowedPrefixes as $prefix) {
                        if (str_starts_with($value, $prefix)) {
                            $isAllowed = true;
                            break;
                        }
                    }
                    if (!$isAllowed) {
                        $fail('حذف این فایل مجاز نیست');
                    }
                },
            ],
        ], [
            'file_path.required' => 'مسیر فایل الزامی است',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filePath = $request->input('file_path');

            // Check if file exists and delete it
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);

                return response()->json([
                    'message' => 'فایل با موفقیت حذف شد'
                ]);
            } else {
                return response()->json([
                    'message' => 'فایل یافت نشد'
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در حذف فایل'
            ], 500);
        }
    }

    /**
     * Generate a secure unique filename
     */
    protected function generateSecureFilename(string $extension): string
    {
        // Sanitize extension
        $extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension));

        // Generate unique filename using UUID
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Get validation rules based on file type
     */
    protected function getValidationRulesForType(string $type): array
    {
        return match($type) {
            'image' => [
                'required',
                'file',
                'max:5120', // 5MB
                new SecureImage(
                    allowedMimeTypes: $this->allowedImageMimes,
                    maxPixelCount: 25_000_000,
                    maxWidth: 4096,
                    maxHeight: 4096
                ),
            ],
            'document' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimetypes:' . implode(',', $this->allowedDocumentMimes),
            ],
            default => [
                'required',
                'file',
                'max:5120', // 5MB default
                'mimetypes:' . implode(',', array_merge($this->allowedImageMimes, $this->allowedDocumentMimes)),
            ],
        };
    }
}
