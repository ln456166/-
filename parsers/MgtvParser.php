<?php

class MgtvParser extends BaseParser
{
    protected $platform = 'mgtv';
    protected $platformName = '芒果TV';
    protected $hosts = ['mgtv.com', 'www.mgtv.com', 'm.mgtv.com'];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $host = $parsedUrl['host'] ?? '';
        $name = '';
        $episode = 1;

        $mobileUrl = $url;
        if (strpos($host, 'www.mgtv.com') !== false) {
            $mobileUrl = str_replace('www.mgtv.com', 'm.mgtv.com', $url);
        }

        $mobileHtml = $this->httpGetMobile($mobileUrl);
        if ($mobileHtml) {
            $name = $this->extractNameFromMeta($mobileHtml);
            if (empty($name)) $name = $this->extractNameFromTitle($mobileHtml);
            if (empty($name)) $name = $this->extractNameFromJsonLd($mobileHtml);
            $episode = $this->extractEpisodeFromHtml($mobileHtml, $episode);
        }

        if (empty($name) || $this->isDefaultTitle($name)) {
            if (preg_match('/\/b\/(\d+)\/(\d+)\.html/', $path, $matches)) {
                $vid = $matches[2];
                $apiUrl = 'https://pcweb.api.mgtv.com/player/video?video_id=' . $vid;
                $response = $this->httpGet($apiUrl);
                if ($response) {
                    $data = json_decode($response, true);
                    if ($data && isset($data['data']['info']['title'])) {
                        $apiName = $data['data']['info']['title'];
                        if (!empty($apiName) && !$this->isDefaultTitle($apiName)) {
                            $name = $apiName;
                            if (isset($data['data']['info']['tname'])) {
                                $epName = $data['data']['info']['tname'];
                                if (preg_match('/第(\d+)[集期]/', $epName, $epMatches)) {
                                    $episode = intval($epMatches[1]);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($name) || $this->isDefaultTitle($name)) {
            $pcHtml = $this->httpGet($url);
            if ($pcHtml) {
                $pcName = $this->extractNameFromMeta($pcHtml);
                if (empty($pcName)) $pcName = $this->extractNameFromTitle($pcHtml);
                if (empty($pcName)) $pcName = $this->extractNameFromJsonLd($pcHtml);

                if (!empty($pcName) && !$this->isDefaultTitle($pcName)) {
                    $name = $pcName;
                    $episode = $this->extractEpisodeFromHtml($pcHtml, $episode);
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
            '芒果TV',
            'mgtv',
            '芒果',
            '首页',
            '湖南卫视',
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
