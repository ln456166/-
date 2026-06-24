<?php

class BilibiliParser extends BaseParser
{
    protected $platform = 'bilibili';
    protected $platformName = '哔哩哔哩';
    protected $hosts = ['bilibili.com', 'www.bilibili.com', 'm.bilibili.com', 'b23.tv'];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);
        $name = '';
        $episode = 1;

        if (preg_match('/\/bangumi\/play\/ep(\d+)/', $path, $matches)) {
            $epid = intval($matches[1]);
            $apiUrl = 'https://api.bilibili.com/pgc/view/web/season?ep_id=' . $epid;
            $response = $this->httpGet($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['result']['share_copy'])) {
                    $name = $data['result']['share_copy'];
                    if (isset($data['result']['episodes'])) {
                        foreach ($data['result']['episodes'] as $ep) {
                            if ($ep['ep_id'] == $epid) {
                                $epTitle = $ep['title'] ?? 1;
                                $episode = is_numeric($epTitle) ? intval($epTitle) : 1;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (empty($name) && preg_match('/\/video\/(BV[a-zA-Z0-9]+)/', $path, $matches)) {
            $bvid = $matches[1];
            $apiUrl = 'https://api.bilibili.com/x/web-interface/view?bvid=' . $bvid;
            $response = $this->httpGet($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['data']['title'])) {
                    $name = $data['data']['title'];
                    if (isset($queryParams['p']) && intval($queryParams['p']) > 0) {
                        $episode = intval($queryParams['p']);
                    }
                }
            }
        }

        if (empty($name) || $this->isDefaultTitle($name)) {
            $html = $this->httpGet($url);
            if ($html) {
                $htmlName = $this->extractNameFromMeta($html);
                if (empty($htmlName)) $htmlName = $this->extractNameFromTitle($html);
                if (empty($htmlName)) $htmlName = $this->extractNameFromJsonLd($html);

                if (!empty($htmlName) && !$this->isDefaultTitle($htmlName)) {
                    $name = $htmlName;
                    $episode = $this->extractEpisodeFromHtml($html, $episode);
                }
            }
        }

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return false;
    }

    private function isDefaultTitle($title)
    {
        $defaultKeywords = [
            '哔哩哔哩',
            'bilibili',
            'b站',
            '首页',
        ];

        foreach ($defaultKeywords as $keyword) {
            if (mb_strtolower($title, 'UTF-8') === mb_strtolower($keyword, 'UTF-8')) {
                return true;
            }
        }

        if (mb_strlen($title, 'UTF-8') < 2) {
            return true;
        }

        return false;
    }
}
