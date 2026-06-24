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

        $html = $this->httpGetMobile($mobileUrl);
        if ($html) {
            $name = $this->extractNameFromMeta($html);
            if (empty($name)) $name = $this->extractNameFromTitle($html);
            if (empty($name)) $name = $this->extractNameFromJsonLd($html);
            $episode = $this->extractEpisodeFromHtml($html, $episode);
        }

        if (empty($name) && preg_match('/\/b\/(\d+)\/(\d+)\.html/', $path, $matches)) {
            $vid = $matches[2];
            $apiUrl = 'https://pcweb.api.mgtv.com/player/video?video_id=' . $vid;
            $response = $this->httpGet($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['data']['info']['title'])) {
                    $name = $data['data']['info']['title'];
                    if (isset($data['data']['info']['tname'])) {
                        $epName = $data['data']['info']['tname'];
                        if (preg_match('/第(\d+)[集期]/', $epName, $epMatches)) {
                            $episode = intval($epMatches[1]);
                        }
                    }
                }
            }
        }

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return $this->parseByHtml($url);
    }
}
