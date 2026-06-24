<?php

class TencentParser extends BaseParser
{
    protected $platform = 'tencent';
    protected $platformName = '腾讯视频';
    protected $hosts = ['qq.com', 'v.qq.com', 'm.v.qq.com'];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);
        $name = '';
        $episode = 1;

        $vid = $this->extractVid($url, $path, $queryParams);

        if (!empty($vid)) {
            $apiUrl = 'https://vv.video.qq.com/getinfo?vids=' . $vid . '&platform=101001&charge=0&otype=json';
            $response = $this->httpGet($apiUrl);
            if ($response) {
                $response = trim($response);
                if (preg_match('/QZOutputJson=\((.*?)\);?$/s', $response, $matches)) {
                    $json = json_decode($matches[1], true);
                } else {
                    $json = json_decode($response, true);
                }
                if ($json && isset($json['vl']['vi'][0])) {
                    $videoInfo = $json['vl']['vi'][0];
                    $name = $videoInfo['ti'] ?? '';
                    if (isset($videoInfo['p']) && is_numeric($videoInfo['p'])) {
                        $episode = intval($videoInfo['p']);
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

    private function extractVid($url, $path, $queryParams)
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

        if (preg_match('/page\/([a-zA-Z0-9]+)\.html/', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('/x\/cover\/[^\/]+\/([a-zA-Z0-9]+)\.html/', $path, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function isDefaultTitle($title)
    {
        $defaultKeywords = [
            '腾讯视频',
            'v.qq.com',
            '中国',
            '热门',
            '电视剧',
            '电影',
            '综艺',
            '动漫',
            '少儿',
        ];

        foreach ($defaultKeywords as $keyword) {
            if ($title === $keyword) {
                return true;
            }
        }

        if (mb_strlen($title, 'UTF-8') < 2) {
            return true;
        }

        return false;
    }
}
