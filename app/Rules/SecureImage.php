<?php

namespace App\Rules;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SecureImage implements ValidationRule
{
    protected array $allowedMimeTypes = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
    ];

    protected array $imagetypeToMime = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    protected int $maxPixelCount;
    protected ?int $minWidth;
    protected ?int $minHeight;
    protected ?int $maxWidth;
    protected ?int $maxHeight;

    public function __construct(
        ?array $allowedMimeTypes = null,
        int $maxPixelCount = 25_000_000,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $minWidth = null,
        ?int $minHeight = null
    ) {
        if ($allowedMimeTypes !== null) {
            $this->allowedMimeTypes = [];
            foreach ($allowedMimeTypes as $mime) {
                $this->allowedMimeTypes[$mime] = true;
            }
            $this->imagetypeToMime = array_filter(
                $this->imagetypeToMime,
                function (string $mime): bool {
                    return isset($this->allowedMimeTypes[$mime]);
                }
            );
        }

        $this->maxPixelCount = $maxPixelCount;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        $this->minWidth = $minWidth;
        $this->minHeight = $minHeight;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be an uploaded file.');
            return;
        }
        if (!$value->isValid() || $value->getSize() === 0) {
            $fail('The :attribute is not a valid upload.');
            return;
        }

        $path = $value->getPathname();

        $mimeFromExif = null;
        if (function_exists('exif_imagetype')) {
            $imageType = @exif_imagetype($path);
            if ($imageType !== false && isset($this->imagetypeToMime[$imageType])) {
                $mimeFromExif = $this->imagetypeToMime[$imageType];
            }
        }

        $imageSize = @getimagesize($path);
        if ($imageSize === false || !isset($imageSize[0], $imageSize[1])) {
            $fail('The :attribute is not a valid image.');
            return;
        }
        $width = (int) $imageSize[0];
        $height = (int) $imageSize[1];
        $totalPixels = $width * $height;

        if ($mimeFromExif === null && isset($imageSize['mime']) && is_string($imageSize['mime'])) {
            $mimeFromExif = $imageSize['mime'];
        }
        if ($mimeFromExif === null || !isset($this->allowedMimeTypes[$mimeFromExif])) {
            $fail('The :attribute must be a valid image (jpeg, png, webp).');
            return;
        }

        if ($totalPixels <= 0) {
            $fail('The :attribute is not a valid image.');
            return;
        }
        if ($totalPixels > $this->maxPixelCount) {
            $fail('The :attribute image is too large.');
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeFromFinfo = $finfo->file($path) ?: '';
        $mimeFromFramework = (string) $value->getMimeType();

        if (!isset($this->allowedMimeTypes[$mimeFromExif])) {
            $fail('The :attribute image type is not allowed.');
            return;
        }
        if ($mimeFromFinfo !== $mimeFromExif) {
            $fail('The :attribute file type could not be verified.');
            return;
        }
        if ($mimeFromFramework !== '' && $mimeFromFramework !== $mimeFromExif) {
            $fail('The :attribute file type does not match its contents.');
            return;
        }
    }
}


