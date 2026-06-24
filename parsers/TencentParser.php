<?php

class TencentParser extends BaseParser
{
    protected $platform = 'tencent';
    protected $platformName = '腾讯视频';
    protected $hosts = ['qq.com', 'v.qq.com'];

    public function parse($url, $parsedUrl)
    {
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $queryParams);
        $name = '';
        $episode = 1;

        if (isset($queryParams['vid']) && !empty($queryParams['vid'])) {
            $vid = $queryParams['vid'];
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

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return $this->parseByHtml($url);
    }
}
