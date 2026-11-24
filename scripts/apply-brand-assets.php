<?php

declare(strict_types=1);

$brand = dirname(__DIR__).'/public/brand';
$icons = dirname(__DIR__).'/public/icons';
foreach ([$brand, $icons, dirname(__DIR__).'/public'] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        throw new RuntimeException('Could not create '.$dir);
    }
}

$srcLogo = '/tmp/oc-brand/logo.png';
$srcIcon = '/tmp/oc-brand/logo-icon.png';
$srcTouch = '/tmp/oc-brand/apple-touch-icon.png';
$src32 = '/tmp/oc-brand/favicon-32.png';
$src64 = '/tmp/oc-brand/favicon-64.png';

copy($src32, dirname(__DIR__).'/public/favicon-32.png');
copy($src64, dirname(__DIR__).'/public/favicon-64.png');
copy($srcTouch, $icons.'/apple-touch-icon-180.png');

$logo = imagecreatefrompng($srcLogo);
$targetWidth = 1600;
$targetHeight = (int) round(imagesy($logo) * ($targetWidth / imagesx($logo)));
$resized = imagecreatetruecolor($targetWidth, $targetHeight);
imagealphablending($resized, false);
imagesavealpha($resized, true);
imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($logo), imagesy($logo));
imagepng($resized, $brand.'/logo.png', 9);
imagedestroy($logo);
imagedestroy($resized);

$giraffe = imagecreatefrompng($srcIcon);

function canvas(int $size, bool $alpha = false): GdImage
{
    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $bg = $alpha
        ? imagecolorallocatealpha($image, 0, 0, 0, 127)
        : imagecolorallocate($image, 11, 15, 26);
    imagefilledrectangle($image, 0, 0, $size, $size, $bg);
    imagealphablending($image, true);

    return $image;
}

function place(GdImage $canvas, GdImage $mark, int $box): void
{
    $size = imagesx($canvas);
    $offset = (int) (($size - $box) / 2);
    imagecopyresampled($canvas, $mark, $offset, $offset, 0, 0, $box, $box, imagesx($mark), imagesy($mark));
}

foreach ([180 => 'apple-touch-icon-180.png', 167 => 'apple-touch-icon-167.png', 152 => 'apple-touch-icon-152.png'] as $size => $name) {
    $image = canvas($size);
    place($image, $giraffe, (int) round($size * 0.72));
    imagepng($image, $icons.'/'.$name, 9);
    imagedestroy($image);
}

foreach ([192, 512] as $size) {
    $any = canvas($size);
    place($any, $giraffe, (int) round($size * 0.78));
    imagepng($any, $icons."/icon-{$size}.png", 9);
    imagedestroy($any);

    $mask = canvas($size);
    place($mask, $giraffe, (int) round($size * 0.62));
    imagepng($mask, $icons."/icon-{$size}-maskable.png", 9);
    imagedestroy($mask);
}

$wordmark = imagecreatefrompng($brand.'/logo.png');
$splashes = [
    [1170, 2532], [1284, 2778], [1179, 2556], [1290, 2796],
    [1536, 2048], [1668, 2388], [2048, 2732],
    [2048, 1536], [2388, 1668], [2732, 2048],
];
foreach ($splashes as [$width, $height]) {
    $image = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($image, 11, 15, 26);
    imagefilledrectangle($image, 0, 0, $width, $height, $bg);
    $logoWidth = (int) round(min($width, $height) * 0.72);
    $logoHeight = (int) round(imagesy($wordmark) * ($logoWidth / imagesx($wordmark)));
    imagecopyresampled(
        $image,
        $wordmark,
        (int) (($width - $logoWidth) / 2),
        (int) (($height - $logoHeight) / 2),
        0,
        0,
        $logoWidth,
        $logoHeight,
        imagesx($wordmark),
        imagesy($wordmark),
    );
    imagepng($image, $icons."/splash-{$width}x{$height}.png", 9);
    imagedestroy($image);
}

echo "brand assets written\n";
