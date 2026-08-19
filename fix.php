<?php
$baseDir = __DIR__;

function replaceInDir($dir) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_dir($path)) {
            replaceInDir($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            $newContent = str_replace(
                ['/al-asafy-deposits/public/', '/al-asafy-deposits/assets/', '/al-asafy-deposits/'],
                ['/', '/assets/', '/'],
                $content
            );
            if ($newContent !== $content) {
                file_put_contents($path, $newContent);
                echo "Fixed: $f <br>";
            }
        }
    }
}
replaceInDir($baseDir . '/public');
replaceInDir($baseDir . '/includes');
replaceInDir($baseDir . '/config');
if (is_dir($baseDir . '/assets')) {
    if (!is_dir($baseDir . '/public/assets')) {
        rename($baseDir . '/assets', $baseDir . '/public/assets');
    }
}
echo "<h3>تم تحديث التصميم بنجاح للنسخة الجديدة!</h3>";
?>
