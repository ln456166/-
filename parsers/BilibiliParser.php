<?php

class BilibiliParser extends BaseParser
{
    protected $platform = 'bilibili';
    protected $platformName = '哔哩哔哩';
    protected $hosts = ['bilibili.com', 'www.bilibili.com', 'm.bilibili.com', 'b23.tv'];
    protected $mobileUrlTemplate = true;

    protected $defaultTitleKeywords = [
        '哔哩哔哩', 'bilibili', 'B站', 'b站',
        ' bilibili', '哔哩哔哩 (゜-゜)つロ 干杯~-bilibili',
        '干杯', '弹幕',
    ];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);

        $apiName = '';
        $apiEpisode = 1;

        if (preg_match('/\/bangumi\/play\/ep(\d+)/', $path, $matches)) {
            $epid = intval($matches[1]);
            $apiResult = $this->parseBangumiByApi($epid);
            if ($apiResult) {
                $apiName = $apiResult['name'];
                $apiEpisode = $apiResult['episode'];
            }
        } elseif (preg_match('/\/video\/(BV[a-zA-Z0-9]+)/', $path, $matches)) {
            $bvid = $matches[1];
            $apiResult = $this->parseVideoByApi($bvid);
            if ($apiResult) {
                $apiName = $apiResult['name'];
                if (isset($queryParams['p']) && intval($queryParams['p']) > 0) {
                    $apiEpisode = intval($queryParams['p']);
                }
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

    private function parseBangumiByApi($epid)
    {
        $apiUrl = 'https://api.bilibili.com/pgc/view/web/season?ep_id=' . $epid;
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['result']['share_copy'])) return false;

        $name = $data['result']['share_copy'];
        $episode = 1;

        if (isset($data['result']['episodes'])) {
            foreach ($data['result']['episodes'] as $ep) {
                if ($ep['ep_id'] == $epid) {
                    $epTitle = $ep['title'] ?? 1;
                    $episode = is_numeric($epTitle) ? intval($epTitle) : 1;
                    break;
                }
            }
        }

        return ['name' => $name, 'episode' => $episode];
    }

    private function parseVideoByApi($bvid)
    {
        $apiUrl = 'https://api.bilibili.com/x/web-interface/view?bvid=' . $bvid;
        $response = $this->httpGet($apiUrl);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!$data || !isset($data['data']['title'])) return false;

        return ['name' => $data['data']['title'], 'episode' => 1];
    }
}
