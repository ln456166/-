<?php

class TencentParser extends BaseParser
{
    protected $platform = 'tencent';
    protected $platformName = '腾讯视频';
    protected $hosts = ['qq.com', 'v.qq.com', 'm.v.qq.com'];
    protected $mobileUrlTemplate = true;

    protected $defaultTitleKeywords = [
        '腾讯视频', 'v.qq.com', '腾讯', 'QQ',
        '中国', '热门', '推荐',
    ];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);

        $vid = $this->extractVid($path, $queryParams);
        $apiName = '';
        $apiEpisode = 1;

        if (!empty($vid)) {
            $apiResult = $this->parseByApi($vid);
            if ($apiResult) {
                $apiName = $apiResult['name'];
                $apiEpisode = $apiResult['episode'];
            }
        }

        $smartResult = $this->parseSmart($url, $parsedUrl);

        if ($apiName && !$this->isDefaultOrInvalid($apiName)) {
            $cleaned = $this->cleanTitle($apiName);
            if ($cleaned && mb_strlen($cleaned, 'UTF-8') >= 2) {
                return ['name' => $cleaned, 'episode' => $apiEpisode, 'source' => 'api'];
            }
        }

        if ($smartResult) {
            return $smartResult;
        }

        return false;
    }

    private function extractVid($path, $queryParams)
    {
        if (isset($queryParams['vid']) && !empty($queryParams['vid'])) {
            return $queryParams['vid'];
        }

        if (preg_match('/\/([a-zA-Z0-9]{10,})\.html$/', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('/cover\/[^\/]+\/([a-zA-Z0-9]{6,})\.html/', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('/x\/cover\/[^\/]+\/([a-zA-Z0-9]+)\.html/', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('/page\/([a-zA-Z0-9]+)\.html/', $path, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function parseByApi($vid)
    {
        $apiUrl = 'https://vv.video.qq.com/getinfo?vids=' . $vid . '&platform=101001&charge=0&otype=json';
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $response = trim($response);
        $jsonStr = preg_replace('/^QZOutputJson=/', '', $response);
        $jsonStr = rtrim($jsonStr, ';');
        $json = json_decode($jsonStr, true);

        if (!$json || !isset($json['vl']['vi'][0])) return false;

        $videoInfo = $json['vl']['vi'][0];
        $name = $videoInfo['ti'] ?? '';
        $episode = 1;
        if (isset($videoInfo['p']) && is_numeric($videoInfo['p'])) {
            $episode = intval($videoInfo['p']);
        }

        if (empty($name)) return false;
        return ['name' => $name, 'episode' => $episode];
    }
}
