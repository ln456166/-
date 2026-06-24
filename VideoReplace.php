<?php

class VideoReplace
{
    private $config;
    private $cacheDir;
    private $parsers = [];

    public function __construct()
    {
        $this->config = require __DIR__ . '/config.php';
        $this->cacheDir = $this->config['cache_dir'];
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        $this->loadParsers();
    }

    private function loadParsers()
    {
        $parserDir = __DIR__ . '/parsers/';
        require_once $parserDir . 'BaseParser.php';

        $parserFiles = [
            'TencentParser.php',
            'YoukuParser.php',
            'IqiyiParser.php',
            'BilibiliParser.php',
            'MgtvParser.php',
        ];

        foreach ($parserFiles as $file) {
            if (file_exists($parserDir . $file)) {
                require_once $parserDir . $file;
                $className = basename($file, '.php');
                if (class_exists($className)) {
                    $parser = new $className();
                    $this->parsers[$parser->getPlatform()] = $parser;
                }
            }
        }
    }

    public function replace($url = '', $name = '', $episode = 0, $parse = false)
    {
        $platform = '';
        if (!empty($url)) {
            $parsed = $this->parseUrl($url);
            if ($parsed === false) {
                return $this->error('无法解析视频播放地址');
            }
            $name = $parsed['name'];
            $episode = $parsed['episode'];
            $platform = $parsed['platform'] ?? '';
        }

        if (empty($name)) {
            return $this->error('视频名称不能为空');
        }

        $video = $this->searchVideo($name);
        if (!$video) {
            return $this->error('未找到对应的视频资源');
        }

        $playUrl = $this->getEpisodeUrl($video, $episode);
        if (!$playUrl) {
            return $this->error('未找到第 ' . $episode . ' 集的播放地址');
        }

        $finalUrl = $playUrl;
        if ($parse && !empty($playUrl)) {
            $parseResult = $this->parsePlayUrl($playUrl);
            if ($parseResult && !empty($parseResult['url'])) {
                $finalUrl = $parseResult['url'];
            }
        }

        return [
            'code' => 200,
            'msg' => '解析成功',
            'url' => $finalUrl,
            'data' => [
                'resource_url' => $playUrl,
                'original_url' => $url,
            ]
        ];
    }

    public function parseUrl($url)
    {
        if (empty($url)) return false;

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) return false;

        $host = strtolower($parsedUrl['host']);

        foreach ($this->parsers as $parser) {
            if ($parser->matchHost($host)) {
                $result = $parser->parse($url, $parsedUrl);
                if ($result && !empty($result['name'])) {
                    $result['platform'] = $parser->getPlatformName();
                    return $result;
                }
            }
        }

        return false;
    }

    public function searchVideo($name)
    {
        $cacheKey = 'search_' . md5($name);
        $cached = $this->getCache($cacheKey);
        if ($cached) return $cached;

        $apiUrl = $this->config['resource_api'] . '?ac=list&wd=' . urlencode($name);
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['list']) || empty($data['list'])) return false;

        $bestMatch = $this->findBestMatch($name, $data['list']);
        if (!$bestMatch) return false;

        $videoDetail = $this->getVideoDetail($bestMatch['vod_id']);
        if ($videoDetail) {
            $this->setCache($cacheKey, $videoDetail, $this->config['cache_time']);
            return $videoDetail;
        }

        return false;
    }

    private function findBestMatch($name, $list)
    {
        $bestMatch = null;
        $bestScore = 0;
        $searchNormalized = $this->normalizeName($name);

        foreach ($list as $item) {
            $vodName = trim($item['vod_name']);
            $score = $this->calcMatchScore($name, $vodName);

            $vodNormalized = $this->normalizeName($vodName);
            if ($searchNormalized && $vodNormalized && $searchNormalized !== $name && $vodNormalized !== $vodName) {
                $normScore = $this->calcMatchScore($searchNormalized, $vodNormalized);
                if ($normScore > $score) $score = $normScore;
            }

            if (!empty($item['vod_sub'])) {
                foreach (explode('/', $item['vod_sub']) as $subName) {
                    $subName = trim($subName);
                    if (empty($subName)) continue;
                    $subScore = $this->calcMatchScore($name, $subName);
                    if ($subScore > $score) $score = $subScore;
                    $subNorm = $this->normalizeName($subName);
                    if ($searchNormalized && $subNorm) {
                        $subNormScore = $this->calcMatchScore($searchNormalized, $subNorm);
                        if ($subNormScore > $score) $score = $subNormScore;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        return ($bestScore >= 25) ? $bestMatch : null;
    }

    private function normalizeName($name)
    {
        $name = trim($name);
        $name = preg_replace('/[《》「」【】\[\]()（）『』"\'\s\-—_·|｜]/u', '', $name);
        $name = preg_replace('/第\s*\d+\s*[集期话]/u', '', $name);
        $name = preg_replace('/\d+$/', '', $name);
        return trim($name);
    }

    private function calcMatchScore($searchName, $targetName)
    {
        $searchName = trim($searchName);
        $targetName = trim($targetName);

        if ($searchName === $targetName) return 200;

        $searchLower = mb_strtolower($searchName, 'UTF-8');
        $targetLower = mb_strtolower($targetName, 'UTF-8');
        if ($searchLower === $targetLower) return 195;

        $searchLen = mb_strlen($searchName, 'UTF-8');
        $targetLen = mb_strlen($targetName, 'UTF-8');

        if (mb_strpos($targetLower, $searchLower, 0, 'UTF-8') === 0) {
            return 150 + max(0, 50 - ($targetLen - $searchLen) * 2);
        }

        if (mb_strpos($searchLower, $targetLower, 0, 'UTF-8') === 0) {
            return 140 + max(0, 40 - ($searchLen - $targetLen) * 2);
        }

        if (mb_strpos($targetLower, $searchLower, 0, 'UTF-8') !== false) {
            return 100;
        }

        if (mb_strpos($searchLower, $targetLower, 0, 'UTF-8') !== false) {
            return 85;
        }

        $searchBase = preg_replace('/\d+$/', '', $searchName);
        $targetBase = preg_replace('/\d+$/', '', $targetName);
        $searchNum = '';
        $targetNum = '';
        if (preg_match('/(\d+)$/', $searchName, $sm)) $searchNum = $sm[1];
        if (preg_match('/(\d+)$/', $targetName, $tm)) $targetNum = $tm[1];

        if ($searchBase && $targetBase && $searchBase === $targetBase) {
            if ($searchNum === $targetNum) return 180;
            elseif ($searchNum && $targetNum) return 160;
            else return 140;
        }

        $commonChars = 0;
        $searchChars = [];
        for ($i = 0; $i < $searchLen; $i++) {
            $searchChars[mb_substr($searchName, $i, 1, 'UTF-8')] = true;
        }
        for ($i = 0; $i < $targetLen; $i++) {
            if (isset($searchChars[mb_substr($targetName, $i, 1, 'UTF-8')])) $commonChars++;
        }

        if ($commonChars > 0) {
            $precision = $commonChars / $targetLen;
            $recall = $commonChars / $searchLen;
            $f1 = 2 * $precision * $recall / ($precision + $recall + 0.001);
            return $f1 * 100;
        }

        similar_text($searchLower, $targetLower, $score);
        return $score;
    }

    public function getVideoDetail($vodId)
    {
        $cacheKey = 'detail_' . $vodId;
        $cached = $this->getCache($cacheKey);
        if ($cached) return $cached;

        $apiUrl = $this->config['resource_api'] . '?ac=detail&ids=' . $vodId;
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['list'][0])) return false;

        $video = $data['list'][0];
        $this->setCache($cacheKey, $video, $this->config['cache_time']);
        return $video;
    }

    public function getEpisodeUrl($video, $episode)
    {
        if (!isset($video['vod_play_url']) || empty($video['vod_play_url'])) return false;

        $episodes = $this->parsePlayList($video['vod_play_url']);
        if (empty($episodes)) return false;

        $targetEpisode = max(1, intval($episode));

        foreach ($episodes as $ep) {
            if ($ep['num'] === $targetEpisode) return $ep['url'];
        }

        $allZero = true;
        foreach ($episodes as $ep) {
            if ($ep['num'] > 0) { $allZero = false; break; }
        }
        if ($allZero && !empty($episodes)) return $episodes[0]['url'];

        if (count($episodes) === 1) return $episodes[0]['url'];

        return false;
    }

    private function parsePlayList($playUrlStr)
    {
        $episodes = [];
        foreach (explode('#', $playUrlStr) as $item) {
            if (empty($item)) continue;
            $parts = explode('$', $item);
            if (count($parts) < 2) continue;
            $name = trim($parts[0]);
            $url = trim($parts[1]);
            $num = $this->extractEpisodeNumber($name);
            $episodes[] = ['name' => $name, 'num' => $num, 'url' => $url];
        }
        return $episodes;
    }

    private function extractEpisodeNumber($name)
    {
        $patterns = [
            '/第(\d+)集/', '/第(\d+)期/', '/第(\d+)话/',
            '/^(\d+)$/', '/EP(\d+)/i', '/(\d+)\s*集/',
            '/(\d+)\s*期/', '/(\d{8})/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return intval($matches[1]);
            }
        }
        return 0;
    }

    public function parsePlayUrl($playUrl)
    {
        if (empty($playUrl)) {
            return ['success' => false, 'url' => $playUrl, 'msg' => '播放地址为空', 'type' => ''];
        }

        $cacheKey = 'parse_' . md5($playUrl);
        $cached = $this->getCache($cacheKey);
        if ($cached) return $cached;

        $parseConfig = $this->config['parse_api'] ?? [];
        if (empty($parseConfig['url']) || empty($parseConfig['key'])) {
            $result = ['success' => false, 'url' => $playUrl, 'msg' => '解析API未配置', 'type' => ''];
            $this->setCache($cacheKey, $result, 300);
            return $result;
        }

        $apiUrl = $parseConfig['url'] . '?key=' . $parseConfig['key'] . '&url=' . urlencode($playUrl);
        $timeout = $parseConfig['timeout'] ?? 15;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = ['success' => false, 'url' => $playUrl, 'msg' => '', 'type' => ''];

        if ($httpCode !== 200 || $response === false) {
            $result['msg'] = '解析API请求失败';
            $this->setCache($cacheKey, $result, 300);
            return $result;
        }

        $data = json_decode($response, true);
        if (!$data) {
            $result['msg'] = '解析API返回格式错误';
            $this->setCache($cacheKey, $result, 300);
            return $result;
        }

        $code = $data['code'] ?? -1;
        $parsedUrl = $data['url'] ?? '';

        if ($code == 200 && !empty($parsedUrl)) {
            $result['success'] = true;
            $result['url'] = $parsedUrl;
            $result['msg'] = $data['msg'] ?? '';
            $result['type'] = $data['type'] ?? '';
        } else {
            $result['url'] = !empty($parsedUrl) ? $parsedUrl : $playUrl;
            $result['msg'] = $data['msg'] ?? '';
        }

        $cacheTime = $parseConfig['cache_time'] ?? 7200;
        $this->setCache($cacheKey, $result, $cacheTime);
        return $result;
    }

    private function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Referer: http://www.dyttzyapi.com/',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode === 200 && $response !== false) ? $response : false;
    }

    private function getCache($key)
    {
        $file = $this->cacheDir . '/' . $key . '.cache';
        if (!file_exists($file)) return false;
        $data = @file_get_contents($file);
        if ($data === false) return false;
        $data = @unserialize($data);
        if (!$data || !isset($data['expire']) || !isset($data['content'])) return false;
        if (time() > $data['expire']) { @unlink($file); return false; }
        return $data['content'];
    }

    private function setCache($key, $content, $ttl)
    {
        $file = $this->cacheDir . '/' . $key . '.cache';
        $data = ['expire' => time() + $ttl, 'content' => $content];
        @file_put_contents($file, serialize($data));
    }

    private function error($msg)
    {
        return [
            'code' => 400,
            'msg' => '解析失败',
            'url' => '',
            'data' => ['resource_url' => '', 'original_url' => '']
        ];
    }

    public function getSupportedPlatforms()
    {
        $platforms = [];
        foreach ($this->parsers as $parser) {
            $platforms[] = [
                'key' => $parser->getPlatform(),
                'name' => $parser->getPlatformName(),
                'hosts' => $parser->getHosts(),
            ];
        }
        return $platforms;
    }
}
