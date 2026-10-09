<?php
header('Content-Type: text/plain');
echo "GD loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
echo "WebP support: " . (function_exists('imagewebp') ? 'YES' : 'NO') . "\n";
echo "Imagick loaded: " . (extension_loaded('imagick') ? 'YES' : 'NO') . "\n";

if (extension_loaded('gd')) {
    $info = gd_info();
    echo "GD WebP Support: " . (!empty($info['WebP Support']) ? 'YES' : 'NO') . "\n";
    echo "GD AVIF Support: " . (!empty($info['AVIF Support']) ? 'YES' : 'NO') . "\n";
}