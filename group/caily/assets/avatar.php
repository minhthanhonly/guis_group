<?php
header('Content-Type: image/png');

// Get parameters
$text = isset($_GET['text']) ? $_GET['text'] : 'U';
$size = isset($_GET['size']) ? intval($_GET['size']) : 32;
$bg_color = isset($_GET['bg']) ? $_GET['bg'] : '6c757d';
$text_color = isset($_GET['color']) ? $_GET['color'] : 'ffffff';

// Create image
$image = imagecreatetruecolor($size, $size);

// Convert hex colors to RGB
$bg_rgb = sscanf($bg_color, "%02x%02x%02x");
$text_rgb = sscanf($text_color, "%02x%02x%02x");

// Allocate colors
$bg_color_alloc = imagecolorallocate($image, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2]);
$text_color_alloc = imagecolorallocate($image, $text_rgb[0], $text_rgb[1], $text_rgb[2]);

// Fill background
imagefill($image, 0, 0, $bg_color_alloc);

// Calculate font size (about 60% of image size)
$font_size = max(8, intval($size * 0.6));

// Get text dimensions
$bbox = imagettfbbox($font_size, 0, __DIR__ . '/fonts/arial.ttf', $text);
$text_width = $bbox[4] - $bbox[0];
$text_height = $bbox[1] - $bbox[5];

// Calculate position to center text
$x = ($size - $text_width) / 2;
$y = ($size + $text_height) / 2;

// Try to use TTF font, fallback to built-in font
if (file_exists(__DIR__ . '/fonts/arial.ttf')) {
    imagettftext($image, $font_size, 0, $x, $y, $text_color_alloc, __DIR__ . '/fonts/arial.ttf', $text);
} else {
    // Fallback to built-in font
    $font_size = max(1, intval($size / 8));
    $text_width = strlen($text) * imagefontwidth($font_size);
    $text_height = imagefontheight($font_size);
    $x = ($size - $text_width) / 2;
    $y = ($size - $text_height) / 2;
    imagestring($image, $font_size, $x, $y, $text, $text_color_alloc);
}

// Output image
imagepng($image);
imagedestroy($image);
?> 