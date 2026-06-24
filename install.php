<?php
/**
 * 视频地址替换系统 - 完整安装/更新脚本
 * 使用方法：浏览器访问 install.php
 */

header('Content-Type: text/html; charset=utf-8');

$repoBase = 'https://raw.githubusercontent.com/ln456166/-/main/';

$files = [
    'VideoReplace.php',
    'replace.php',
    'config.php',
    'index.html',
    'update.php',
    'parsers/BaseParser.php',
    'parsers/IqiyiParser.php',
    'parsers/YoukuParser.php',
    'parsers/TencentParser.php',
    'parsers/MgtvParser.php',
    'parsers/BilibiliParser.php',
];

$writableDirs = [
    'cache',
];

$successCount = 0;
$failCount = 0;
$total = count($files);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>视频地址替换系统 - 安装</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:30px auto;padding:20px;background:#f5f5f5}";
echo ".success{color:#155724;background:#d4edda;padding:10px;border-radius:4px;margin:5px 0}";
echo ".error{color:#721c24;background:#f8d7da;padding:10px;border-radius:4px;margin:5px 0}";
echo ".info{color:#004085;background:#cce5ff;padding:10px;border-radius:4px;margin:5px 0}";
echo ".warn{color:#856404;background:#fff3cd;padding:10px;border-radius:4px;margin:5px 0}";
echo "h1{color:#333} .file{margin:8px 0;padding:10px;background:#fff;border-radius:4px}";
echo "code{background:#f0f0f0;padding:2px 6px;border-radius:3px}";
echo "</style></head><body>";
echo "<h1>🚀 视频地址替换系统 - 完整安装</h1>";

echo "<div class='info'>📁 安装目录: " . __DIR__ . "</div>";
echo "<div class='info'>🌐 源码来源: GitHub (ln456166/-)</div>";

echo "<h3>📦 创建必要目录...</h3>";

foreach ($writableDirs as $dir) {
    $dirPath = __DIR__ . '/' . $dir;
    if (!is_dir($dirPath)) {
        if (mkdir($dirPath, 0755, true)) {
            echo "<div class='success'>✅ 已创建目录: {$dir}</div>";
        } else {
            echo "<div class='error'>❌ 创建目录失败: {$dir}</div>";
        }
    } else {
        echo "<div class='info'>📂 目录已存在: {$dir}</div>";
    }
}

echo "<h3>📥 下载并安装文件 ({$total} 个)...</h3>";

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

foreach ($files as $file) {
    $localFile = __DIR__ . '/' . $file;
    $remoteUrl = $repoBase . $file;

    $dir = dirname($localFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    echo "<div class='file'>";
    echo "<strong>📄 {$file}</strong><br>";

    $content = @file_get_contents($remoteUrl, false, $context);

    if ($content === false) {
        echo "  <span style='color:#dc3545'>❌ 下载失败</span><br>";
        echo "  地址: <code style='font-size:12px'>{$remoteUrl}</code><br>";
        $failCount++;
    } else {
        $bytes = file_put_contents($localFile, $content);
        if ($bytes !== false) {
            $size = strlen($content);
            echo "  <span style='color:#28a745'>✅ 安装成功 ({$size} bytes)</span><br>";
            $successCount++;
        } else {
            echo "  <span style='color:#dc3545'>❌ 写入失败，请检查目录权限</span><br>";
            $failCount++;
        }
    }

    echo "</div>";
}

echo "<h3>🔐 设置权限...</h3>";
foreach ($writableDirs as $dir) {
    $dirPath = __DIR__ . '/' . $dir;
    if (is_dir($dirPath)) {
        chmod($dirPath, 0755);
    }
}

if (function_exists('opcache_reset')) {
    opcache_reset();
}

echo "<div style='margin-top:20px;padding:15px;background:#fff;border-radius:4px'>";
echo "<h3>📊 安装结果</h3>";
echo "成功: <span style='color:#28a745;font-weight:bold;font-size:18px'>{$successCount}/{$total}</span><br>";
echo "失败: <span style='color:#dc3545;font-weight:bold;font-size:18px'>{$failCount}/{$total}</span><br>";

if ($failCount == 0) {
    echo "<div class='success' style='margin-top:15px'>";
    echo "<strong>🎉 安装完成！所有文件安装成功！</strong><br><br>";
    echo "📌 快速测试地址：<br>";
    echo "  • 首页: <code>index.html</code><br>";
    echo "  • 替换接口: <code>replace.php?url=视频地址</code><br>";
    echo "  • 后续更新: <code>update.php</code><br>";
    echo "</div>";
} else {
    echo "<div class='error' style='margin-top:15px'>";
    echo "<strong>⚠️  部分文件下载失败</strong><br>";
    echo "可能原因：服务器无法访问 GitHub<br>";
    echo "建议：检查服务器网络或手动下载上传</div>";
}

echo "</div>";

echo "<div class='warn' style='margin-top:20px'>";
echo "<strong>⚠️  安全提示：</strong>安装完成后，请删除 <code>install.php</code> 文件！";
echo "</div>";

echo "</body></html>";
