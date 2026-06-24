<?php

class YoukuParser extends BaseParser
{
    protected $platform = 'youku';
    protected $platformName = '优酷';
    protected $hosts = ['youku.com', 'v.youku.com', 'so.youku.com'];

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

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return $this->parseByHtml($url);
    }
}
