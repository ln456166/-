<?php

class MgtvParser extends BaseParser
{
    protected $platform = 'mgtv';
    protected $platformName = '芒果TV';
    protected $hosts = ['mgtv.com', 'www.mgtv.com', 'm.mgtv.com'];
    protected $mobileUrlTemplate = true;

    protected $defaultTitleKeywords = [
        '芒果TV', '芒果', 'mgtv', 'MGTV',
        '高清视频', '视频', '在线观看',
        '剧集', '电影', '综艺', '动漫',
    ];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $host = $parsedUrl['host'] ?? '';

        $apiName = '';
        $apiEpisode = 1;

        if (preg_match('/\/b\/(\d+)\/(\d+)\.html/', $path, $matches)) {
            $vid = $matches[2];
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

    private function parseByApi($vid)
    {
        $apiUrl = 'https://pcweb.api.mgtv.com/player/video?video_id=' . $vid;
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['data']['info']['title'])) return false;

        $name = $data['data']['info']['title'];
        $episode = 1;

        if (isset($data['data']['info']['tname'])) {
            $epName = $data['data']['info']['tname'];
            if (preg_match('/第(\d+)[集期]/', $epName, $epMatches)) {
                $episode = intval($epMatches[1]);
            }
        }

        return ['name' => $name, 'episode' => $episode];
    }
}
