<?php

class YoukuParser extends BaseParser
{
    protected $platform = 'youku';
    protected $platformName = '优酷';
    protected $hosts = ['youku.com', 'v.youku.com', 'so.youku.com', 'm.youku.com'];

    public function parse($url, $parsedUrl)
    {
        $path = $parsedUrl['path'] ?? '';
        $name = '';
        $episode = 1;

        if (preg_match('/id_([a-zA-Z0-9]+)/', $path, $matches)) {
            $videoId = $matches[1];
            $apiUrl = 'https://openapi.youku.com/v2/videos/show.json?video_id=' . $videoId . '&client_id=921e50b89bd711e08a3c';
            $response = $this->httpGet($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['title'])) {
                    $name = $data['title'];
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
            '优酷',
            'youku',
            '视频',
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
