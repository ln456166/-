<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/VideoReplace.php';

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$episode = isset($_GET['episode']) ? intval($_GET['episode']) : 0;
$format = isset($_GET['format']) ? trim($_GET['format']) : 'json';
$redirect = isset($_GET['redirect']) ? intval($_GET['redirect']) : 0;
$parse = isset($_GET['parse']) ? intval($_GET['parse']) : 0;

if (empty($url) && empty($name)) {
    echo json_encode([
        'code' => 400,
        'msg' => '参数错误，请提供 url 或 name 参数',
        'url' => '',
        'data' => [
            'resource_url' => '',
            'original_url' => '',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$videoReplace = new VideoReplace();
$result = $videoReplace->replace($url, $name, $episode, (bool)$parse);

if ($redirect && $result['code'] === 200 && !empty($result['url'])) {
    header('Location: ' . $result['url']);
    exit;
}

if ($format === 'text') {
    if ($result['code'] === 200) {
        echo $result['url'];
    } else {
        echo '错误: ' . $result['msg'];
    }
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
