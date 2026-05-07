<?php
/* MEDIA FILE PROCESSOR

Processes different types of media files (images, videos, audio, documents).
Does not handle final storage, only processes files and returns results. */

require_once 'ProcessingResult.php';

class MediaFileProcessor
{
    private array $config;

    public function __construct(array $config = [])
    {
        //Ensure config.php is loaded
        if (!defined('PROFILE_IMAGE_RESIZE_WIDTH')) {
            throw new RuntimeException('Configuration constants not loaded. Please include config.php');
        }

        //Use configuration from config.php
        $this->config = array_merge([
            'profile_image_max_width'  => PROFILE_IMAGE_RESIZE_WIDTH,
            'profile_image_max_height' => PROFILE_IMAGE_RESIZE_HEIGHT,
            'profile_image_quality'    => PROFILE_IMAGE_JPEG_QUALITY,
            'group_image_max_width'    => GROUP_IMAGE_RESIZE_WIDTH,
            'group_image_max_height'   => GROUP_IMAGE_RESIZE_HEIGHT,
            'group_image_quality'      => GROUP_IMAGE_JPEG_QUALITY,
        ], $config);
    }

    //Process profile image (resize and optimize)
    public function processProfileImage(string $sourcePath, string $destinationPath, array $options = []): ProcessingResult
    {
        if (!file_exists($sourcePath)) {
            return ProcessingResult::failure(['Source file does not exist']);
        }

        $originalSize = filesize($sourcePath);

        //Validate image
        if (@getimagesize($sourcePath) === false) {
            return ProcessingResult::failure(['File is not a valid image']);
        }

        //Determine target dimensions
        $maxWidth  = $options['max_width'] ?? $this->config['profile_image_max_width'];
        $maxHeight = $options['max_height'] ?? $this->config['profile_image_max_height'];
        $quality   = $options['quality'] ?? $this->config['profile_image_quality'];

        //Process the image
        if (!$this->resizeImage($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality)) {
            return ProcessingResult::failure(['Failed to resize image']);
        }

        $processedSize = file_exists($destinationPath) ? filesize($destinationPath) : $originalSize;

        return ProcessingResult::success($originalSize, $processedSize);
    }

    //Process group image (similar to profile image but with different dimensions)
    public function processGroupImage(string $sourcePath, string $destinationPath, array $options = []): ProcessingResult
    {
        if (!file_exists($sourcePath)) {
            return ProcessingResult::failure(['Source file does not exist']);
        }

        $originalSize = filesize($sourcePath);

        //Validate image
        if (@getimagesize($sourcePath) === false) {
            return ProcessingResult::failure(['File is not a valid image']);
        }

        //Determine target dimensions for group images
        $maxWidth  = $options['max_width'] ?? $this->config['group_image_max_width'];
        $maxHeight = $options['max_height'] ?? $this->config['group_image_max_height'];
        $quality   = $options['quality'] ?? $this->config['group_image_quality'];

        //Process the image
        if (!$this->resizeImage($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality)) {
            return ProcessingResult::failure(['Failed to resize group image']);
        }

        $processedSize = file_exists($destinationPath) ? filesize($destinationPath) : $originalSize;

        return ProcessingResult::success($originalSize, $processedSize);
    }

    //Resize image while maintaining aspect ratio
    private function resizeImage(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight, int $quality = 85): bool
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$originalWidth, $originalHeight, $imageType] = $imageInfo;

        //Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                app_log("Failed to create directory: $destDir");
                return false;
            }
        }

        //Calculate new dimensions
        $ratio     = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth  = (int)round($originalWidth * $ratio);
        $newHeight = (int)round($originalHeight * $ratio);

        //Create source image
        $sourceImage = $this->createImageFromFile($sourcePath, $imageType);
        if ($sourceImage === false) {
            return false;
        }

        //Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        //Preserve transparency for PNG
        if ($imageType === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        //Resize
        $resized = imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        if (!$resized) {
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            return false;
        }

        //Save image
        $saved = $this->saveImageToFile($newImage, $destPath, $imageType, $quality);

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $saved;
    }

    //Create image resource from file
    private function createImageFromFile(string $filePath, int $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            default:
                return false;
        }
    }

    //Save image resource to file
    private function saveImageToFile($imageResource, string $filePath, int $imageType, int $quality): bool
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $filePath, $quality);
            case IMAGETYPE_PNG:
                $pngQuality = (int)round(9 - ($quality / 100 * 9));
                return imagepng($imageResource, $filePath, $pngQuality);
            case IMAGETYPE_GIF:
                return imagegif($imageResource, $filePath);
            default:
                return false;
        }
    }
}
