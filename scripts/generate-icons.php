<?php

declare(strict_types=1);

$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$dir = __DIR__.'/../public/icons';
if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
    throw new RuntimeException('Could not create icon directory.');
}

$navy = [26, 60, 94];
$gold = [245, 166, 35];

function canvas(int $width, int $height, array $navy): GdImage
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, true);
    $color = imagecolorallocate($image, $navy[0], $navy[1], $navy[2]);
    imagefilledrectangle($image, 0, 0, $width, $height, $color);

    return $image;
}

function mark(GdImage $image, int $size, array $gold, string $font, bool $maskable): void
{
    $pad = (int) round($size * ($maskable ? 0.22 : 0.14));
    $goldColor = imagecolorallocate($image, $gold[0], $gold[1], $gold[2]);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), $size - (2 * $pad), $size - (2 * $pad), $goldColor);
    $text = 'OC';
    $box = imagettfbbox((int) ($size * 0.28), 0, $font, $text);
    $textWidth = $box[2] - $box[0];
    $textHeight = $box[1] - $box[7];
    imagettftext(
        $image,
        (int) ($size * 0.28),
        0,
        (int) (($size - $textWidth) / 2),
        (int) (($size + $textHeight) / 2) - (int) ($size * 0.02),
        $white,
        $font,
        $text,
    );
}

foreach ([192, 512] as $size) {
    foreach ([false, true] as $maskable) {
        $image = canvas($size, $size, $navy);
        mark($image, $size, $gold, $font, $maskable);
        $name = $maskable ? "icon-{$size}-maskable.png" : "icon-{$size}.png";
        imagepng($image, $dir.'/'.$name, 9);
        imagedestroy($image);
    }
}

foreach ([180, 167, 152] as $size) {
    $image = canvas($size, $size, $navy);
    mark($image, $size, $gold, $font, false);
    imagepng($image, $dir."/apple-touch-icon-{$size}.png", 9);
    imagedestroy($image);
}

$splashes = [
    [1170, 2532], [1284, 2778], [1179, 2556], [1290, 2796],
    [1536, 2048], [1668, 2388], [2048, 2732],
    [2048, 1536], [2388, 1668], [2732, 2048],
];

foreach ($splashes as [$width, $height]) {
    $image = canvas($width, $height, $navy);
    $badge = min($width, $height);
    $scale = (int) round($badge * 0.28);
    $badgeImage = canvas($scale, $scale, $navy);
    mark($badgeImage, $scale, $gold, $font, false);
    imagecopy(
        $image,
        $badgeImage,
        (int) (($width - $scale) / 2),
        (int) (($height - $scale) / 2) - (int) ($height * 0.04),
        0,
        0,
        $scale,
        $scale,
    );
    $white = imagecolorallocate($image, 255, 255, 255);
    $label = 'Original Concepts';
    $fontSize = (int) max(28, round($width * 0.035));
    $box = imagettfbbox($fontSize, 0, $font, $label);
    imagettftext($image, $fontSize, 0, (int) (($width - ($box[2] - $box[0])) / 2), (int) ($height * 0.72), $white, $font, $label);
    imagepng($image, $dir."/splash-{$width}x{$height}.png", 9);
    imagedestroy($badgeImage);
    imagedestroy($image);
}

echo "icons written to {$dir}\n";
