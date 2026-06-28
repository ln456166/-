<?php
error_reporting(0);
header('Content-type: text/json;charset=utf-8');

define('VS_', 600);
define('VS__', 1);
define('PATH', 'cache/mg');

if (!file_exists(PATH)) {
    mkdir(PATH, 0777, true);
}

$tk1 = '';
if (file_exists('mgcookie.txt')) {
    $tk1 = trim(file_get_contents('mgcookie.txt'));
}

$list = array(
    "0" => $tk1,
);

function dataPollingInterval($list, $polling_time, $polling_number) {
    $interval = false;
    $arg = array(
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 86400,
    );

    foreach ($arg as $k => $v) {
        if (false !== stripos($polling_time, $k)) {
            $interval = intval($polling_time) * $v;
            break;
        }
    }

    if (!is_int($interval)) {
        return false;
    }

    $this_year_begin_second = strtotime(date('Y-01-01 01:00:01', time()));
    $polling_time = time() - $this_year_begin_second;
    $len = count($list);
    $start_index = intval($polling_time / $interval);
    $start_index = $polling_number * $start_index % $len;

    $res = array();
    for ($i = 0; $i < $len; ++$i) {
        $index = $i + $start_index;
        if ($index >= $len) {
            $index = $index - $len;
        }
        $res[] = $list[$index];
    }
    return $res;
}

$new_list = dataPollingInterval($list, '1800 sec', 1);
$cookie = !empty($new_list[0]) ? $new_list[0] : '';

$url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($url)) {
    $arr = array(
        "code" => 404,
        "msg" => "缺少URL参数"
    );
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!preg_match('/mgtv\.com/i', $url)) {
    $arr = array(
        "code" => 400,
        "msg" => "URL格式不正确，仅支持芒果TV链接"
    );
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$ep_file = PATH . '/' . md5($url) . '.m3u8';

if (!file_exists($ep_file) || filemtime($ep_file) + VS_ < time() || VS__ == 0) {

    $vid = '';
    $cid = '';

    if (preg_match('/\/b\/(\d+)\/(\d+)\.html/i', $url, $matches)) {
        $cid = $matches[1];
        $vid = $matches[2];
    } elseif (preg_match('/\/s\/(\d+)\.html/i', $url, $matches)) {
        $vid = $matches[1];
    } elseif (preg_match('/\/v\/\d+\/\d+\/c\/(\d+)\.html/i', $url, $matches)) {
        $vid = $matches[1];
    } elseif (preg_match('/\/(\d+)\.html/i', $url, $matches)) {
        $vid = $matches[1];
    }

    if (empty($vid)) {
        $arr = array(
            "code" => 400,
            "msg" => "无法从URL中提取视频ID"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $did = generateUUID();
    $suuid = generateUUID();
    $pno = "1030";
    $ver = "0.3.0301";
    $clit = time();
    $_support = "10000000";

    $tk2 = getTK2(array(
        "did" => $did,
        "ver" => $ver,
        "pno" => $pno,
        "clit" => $clit
    ));

    $api_domains = array(
        "https://pcweb.api.mgtv.com",
        "https://pcweb2.api.mgtv.com",
        "https://pcweb3.api.mgtv.com",
    );

    $video_data = null;
    foreach ($api_domains as $api_base) {
        $api_url = "{$api_base}/player/video?video_id={$vid}&suuid={$suuid}&cid={$cid}&tk2={$tk2}&_support={$_support}&type=pch5&auth_mode=1&src=&abroad=&allowedRC=1&definitionType=2";

        $html = curl_request($api_url, $url, $cookie);
        $data = json_decode($html, true);

        if ($data && isset($data['code']) && $data['code'] == 200) {
            $video_data = $data;
            break;
        }
    }

    if (!$video_data) {
        $arr = array(
            "code" => 500,
            "msg" => "获取视频信息失败",
            "debug" => isset($data['msg']) ? $data['msg'] : '未知错误'
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!isset($video_data['data']['atc']['pm2'])) {
        $arr = array(
            "code" => 500,
            "msg" => "获取视频参数失败"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $pm2 = $video_data['data']['atc']['pm2'];

    $tk2_new = getTK2(array(
        "did" => $did,
        "ver" => $ver,
        "pno" => $pno,
        "clit" => time()
    ));

    $source_data = null;
    foreach ($api_domains as $api_base) {
        $source_url = "{$api_base}/player/getSource?tk2={$tk2_new}&pm2={$pm2}&video_id={$vid}&_support={$_support}&did={$did}&suuid={$suuid}&type=pch5&auth_mode=1&src=&abroad=&allowedRC=1&definitionType=2";

        $source_html = curl_request($source_url, $url, $cookie);
        $src_data = json_decode($source_html, true);

        if ($src_data && isset($src_data['code']) && $src_data['code'] == 200) {
            $source_data = $src_data;
            break;
        }
    }

    if (!$source_data) {
        $arr = array(
            "code" => 500,
            "msg" => "获取视频源失败",
            "debug" => isset($src_data['msg']) ? $src_data['msg'] : '未知错误'
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stream = isset($source_data['data']['stream']) ? $source_data['data']['stream'] : array();
    $stream_domain = isset($source_data['data']['stream_domain']) ? $source_data['data']['stream_domain'] : array();

    if (empty($stream) || empty($stream_domain)) {
        $arr = array(
            "code" => 500,
            "msg" => "未找到可用的视频流"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $best_def = 0;
    $best_stream_url = '';
    $best_domain = '';

    foreach ($stream as $key => $value) {
        if (!empty($value['url']) && isset($value['def'])) {
            $def = intval($value['def']);
            if ($def > $best_def && $def != 1) {
                $domain = isset($stream_domain[$key]) ? $stream_domain[$key] : (isset($stream_domain[0]) ? $stream_domain[0] : '');
                if (!empty($domain)) {
                    $best_stream_url = $value['url'];
                    $best_domain = $domain;
                    $best_def = $def;
                }
            }
        }
    }

    if (empty($best_stream_url) && !empty($stream[0]['url'])) {
        $best_stream_url = $stream[0]['url'];
        $best_domain = isset($stream_domain[0]) ? $stream_domain[0] : '';
    }

    if (empty($best_stream_url) || empty($best_domain)) {
        $arr = array(
            "code" => 500,
            "msg" => "无法获取视频播放地址"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $dispatch_url = $best_domain . $best_stream_url;

    $dispatch_html = curl_request($dispatch_url, $url, $cookie);
    $dispatch_data = json_decode($dispatch_html, true);

    if (!$dispatch_data || !isset($dispatch_data['info'])) {
        $arr = array(
            "code" => 500,
            "msg" => "解析分发地址失败"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $m3u8_url = $dispatch_data['info'];

    if (preg_match('/^http:\/\//i', $m3u8_url)) {
        $m3u8_url = preg_replace('/^http:\/\//i', 'https://', $m3u8_url);
    }

    $m3u8_content = curl_request($m3u8_url, $url, $cookie);

    if (empty($m3u8_content) || strpos($m3u8_content, 'EXTM3U') === false) {
        $arr = array(
            "code" => 500,
            "msg" => "获取m3u8内容失败"
        );
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $m3u8_base = dirname($m3u8_url) . '/';

    $hls = preg_replace_callback(
        '/^([^#\s].*)$/m',
        function ($matches) use ($m3u8_base) {
            $line = trim($matches[1]);
            if (empty($line)) return $matches[0];
            if (preg_match('/^https?:\/\//i', $line)) {
                return $line;
            }
            return $m3u8_base . $line;
        },
        $m3u8_content
    );

    file_put_contents($ep_file, $hls);
}

$json = @file_get_contents($ep_file);

if (empty($json)) {
    $videoinfo['code'] = 404;
    $videoinfo['msg'] = '解析失败，缓存文件为空';
    echo json_encode($videoinfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    die;
} else {
    $videoinfo['success'] = 1;
    $videoinfo['code'] = 200;
    $videoinfo['type'] = 'm3u8';
    $videoinfo['url'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $ep_file;
    echo json_encode($videoinfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getTK2($params) {
    $did = $params['did'];
    $ver = $params['ver'];
    $pno = $params['pno'];
    $clit = $params['clit'];

    $str = "did={$did}|pno={$pno}|ver={$ver}|clit={$clit}";
    $str = base64_encode($str);
    $str = str_replace(array('+', '/', '='), array('_', '~', '-'), $str);
    $str = strrev($str);

    return $str;
}

function generateUUID() {
    $chars = '0123456789abcdef';
    $uuid = '';

    $lengths = array(8, 4, 4, 4, 12);
    $parts = array();

    foreach ($lengths as $len) {
        $part = '';
        for ($i = 0; $i < $len; $i++) {
            $part .= $chars[mt_rand(0, 15)];
        }
        $parts[] = $part;
    }

    return implode('-', $parts);
}

function curl_request($url, $referer = '', $cookie = '') {
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_HEADER, 0);
    curl_setopt($c, CURLOPT_NOBODY, 0);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($c, CURLOPT_TIMEOUT, 30);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);

    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';
    curl_setopt($c, CURLOPT_USERAGENT, $ua);

    $headers = array(
        'Accept: */*',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    );

    if (!empty($referer)) {
        $headers[] = 'Referer: ' . $referer;
    }

    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);

    if (!empty($cookie)) {
        curl_setopt($c, CURLOPT_COOKIE, $cookie);
    }

    $content = curl_exec($c);
    curl_close($c);

    return $content;
}
