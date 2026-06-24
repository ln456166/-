<?php

class YoukuParser extends BaseParser
{
    protected $platform = 'youku';
    protected $platformName = '优酷';
    protected $hosts = ['youku.com', 'v.youku.com', 'so.youku.com', 'm.youku.com'];
    protected $mobileUrlTemplate = true;

    protected $defaultTitleKeywords = [
        '优酷', 'youku', 'YOUKU',
        '视频', '剧集', '电影', '综艺', '动漫',
        '高清', '完整版', '在线观看',
    ];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);

        $videoId = '';
        if (preg_match('/id_([a-zA-Z0-9]+)/', $path, $matches)) {
            $videoId = $matches[1];
        }

        $apiName = '';
        if (!empty($videoId)) {
            $apiResult = $this->parseByApi($videoId);
            if ($apiResult) {
                $apiName = $apiResult;
            }
        }

        $smartResult = $this->parseSmart($url, $parsedUrl);

        if ($apiName && !$this->isDefaultOrInvalid($apiName)) {
            $cleaned = $this->cleanTitle($apiName);
            if ($cleaned && mb_strlen($cleaned, 'UTF-8') >= 2) {
                return ['name' => $cleaned, 'episode' => 1, 'source' => 'api'];
            }
        }

        if ($smartResult) {
            return $smartResult;
        }

        return false;
    }

    private function parseByApi($videoId)
    {
        $apiUrl = 'https://openapi.youku.com/v2/videos/show.json?video_id=' . $videoId . '&client_id=921e50b89bd711e08a3c';
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['title'])) return false;

        return $data['title'];
    }
}
