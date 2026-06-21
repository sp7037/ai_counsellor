<?php

$source = __DIR__.'/../public/images/Ai_counsellor_logo.png';
$target = __DIR__.'/../public/images/favicon.png';

if (! is_file($source)) {
    fwrite(STDERR, "Logo source not found.\n");
    exit(1);
}

if (! extension_loaded('gd')) {
    copy($source, $target);
    echo "Copied logo as favicon (GD unavailable).\n";
    exit(0);
}

$image = imagecreatefrompng($source);
if ($image === false) {
    copy($source, $target);
    echo "Copied logo as favicon (could not decode PNG).\n";
    exit(0);
}

$size = 64;
$output = imagecreatetruecolor($size, $size);
imagealphablending($output, false);
imagesavealpha($output, true);
$transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
imagefilledrectangle($output, 0, 0, $size, $size, $transparent);
imagecopyresampled($output, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
imagepng($output, $target);
imagedestroy($image);
imagedestroy($output);

echo "Generated public/images/favicon.png\n";
