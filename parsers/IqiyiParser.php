<?php

class IqiyiParser extends BaseParser
{
    protected $platform = 'iqiyi';
    protected $platformName = '爱奇艺';
    protected $hosts = ['iqiyi.com', 'www.iqiyi.com', 'm.iqiyi.com'];

    public function parse($url, $parsedUrl)
    {
        $host = $parsedUrl['host'] ?? '';
        $name = '';
        $episode = 1;

        $mobileUrl = $url;
        if (strpos($host, 'www.iqiyi.com') !== false) {
            $mobileUrl = str_replace('www.iqiyi.com', 'm.iqiyi.com', $url);
        }

        $html = $this->httpGetMobile($mobileUrl);
        if ($html) {
            $name = $this->extractNameFromMeta($html);
            if (empty($name)) $name = $this->extractNameFromTitle($html);
            if (empty($name)) $name = $this->extractNameFromJsonLd($html);
            $episode = $this->extractEpisodeFromHtml($html, $episode);
        }

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return $this->parseByHtml($url);
    }
}
