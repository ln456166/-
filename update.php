<?php
/**
 * 视频地址替换系统 - 一键更新脚本
 * 使用方法：浏览器访问 update.php 或命令行执行 php update.php
 */

header('Content-Type: text/html; charset=utf-8');

$repoUrl = 'https://raw.githubusercontent.com/ln456166/-/main/';

$files = [
    'VideoReplace.php',
    'parsers/BaseParser.php',
    'replace.php',
];

$backupDir = __DIR__ . '/backup_' . date('Ymd_His');
$successCount = 0;
$failCount = 0;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>系统更新</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5}";
echo ".success{color:#155724;background:#d4edda;padding:10px;border-radius:4px;margin:5px 0}";
echo ".error{color:#721c24;background:#f8d7da;padding:10px;border-radius:4px;margin:5px 0}";
echo ".info{color:#004085;background:#cce5ff;padding:10px;border-radius:4px;margin:5px 0}";
echo "h1{color:#333} .file{margin:10px 0;padding:10px;background:#fff;border-radius:4px}";
echo "</style></head><body>";
echo "<h1>🔄 视频地址替换系统 - 一键更新</h1>";

echo "<div class='info'>📦 备份目录: {$backupDir}</div>";

if (!mkdir($backupDir, 0755, true)) {
    die("<div class='error'>❌ 创建备份目录失败</div></body></html>");
}

echo "<div class='info'>🌐 从 GitHub 下载更新文件...</div>";

foreach ($files as $file) {
    $localFile = __DIR__ . '/' . $file;
    $remoteUrl = $repoUrl . $file;

    echo "<div class='file'>";
    echo "<strong>📄 {$file}</strong><br>";

    if (file_exists($localFile)) {
        $backupFile = $backupDir . '/' . str_replace('/', '_', $file);
        if (copy($localFile, $backupFile)) {
            echo "  ✅ 已备份到 backup/" . basename($backupDir) . "<br>";
        } else {
            echo "  ⚠️  备份失败，继续更新<br>";
        }
    }

    $dir = dirname($localFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $content = @file_get_contents($remoteUrl, false, $context);

    if ($content === false) {
        echo "  <span style='color:#dc3545'>❌ 下载失败</span><br>";
        echo "  尝试地址: {$remoteUrl}<br>";
        $failCount++;
    } else {
        if (file_put_contents($localFile, $content) !== false) {
            $size = strlen($content);
            echo "  <span style='color:#28a745'>✅ 更新成功 ({$size} bytes)</span><br>";
            $successCount++;
        } else {
            echo "  <span style='color:#dc3545'>❌ 写入失败</span><br>";
            $failCount++;
        }
    }

    echo "</div>";
}

if (function_exists('opcache_reset')) {
    opcache_reset();
}

$total = count($files);
echo "<div style='margin-top:20px;padding:15px;background:#fff;border-radius:4px'>";
echo "<h3>📊 更新结果</h3>";
echo "成功: <span style='color:#28a745;font-weight:bold'>{$successCount}/{$total}</span><br>";
echo "失败: <span style='color:#dc3545;font-weight:bold'>{$failCount}/{$total}</span><br>";

if ($failCount == 0) {
    echo "<div class='success' style='margin-top:10px'>🎉 所有文件更新成功！</div>";
} else {
    echo "<div class='error' style='margin-top:10px'>⚠️  部分文件更新失败，请检查网络或手动更新</div>";
}

echo "</div>";
echo "<div class='info' style='margin-top:20px'>";
echo "💡 提示：更新完成后可以删除此文件（update.php）和 backup_xxx 备份目录";
echo "</div>";

echo "</body></html>";
