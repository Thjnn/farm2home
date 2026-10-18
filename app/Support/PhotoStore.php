<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu ảnh chụp từ điện thoại lên disk "public": xoay đúng chiều, thu nhỏ còn cạnh dài tối đa 1280px, nén JPEG,
 * để trang QR mở nhanh bằng 4G (ảnh gốc điện thoại thường 3–5 MB).
 */
final class PhotoStore
{
    private const MAX_SIDE = 1280;

    private const QUALITY = 80;

    public static function store(UploadedFile $file, string $directory): string
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        $image = self::orient($image, $file);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        }

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $path = $directory.'/'.Str::random(24).'.jpg';
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }

    public static function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Ảnh điện thoại thường lưu nằm ngang kèm cờ EXIF xoay; GD bỏ cờ đó nên phải tự xoay.
     */
    private static function orient(GdImage $image, UploadedFile $file): GdImage
    {
        $exif = $file->getMimeType() === 'image/jpeg' ? @exif_read_data($file->getRealPath()) : false;
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }
}
