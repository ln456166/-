<?php

class IqiyiParser extends BaseParser
{
    protected $platform = 'iqiyi';
    protected $platformName = '爱奇艺';
    protected $hosts = ['iqiyi.com', 'www.iqiyi.com', 'm.iqiyi.com'];
    protected $mobileUrlTemplate = true;

    protected $defaultTitleKeywords = [
        '爱奇艺', 'iQIYI', 'iqiyi',
        '在线视频网站', '海量正版高清视频',
        '视频', '剧集', '电影', '综艺', '动漫',
        '高清', '完整版', '在线观看',
    ];

    public function parse($url, $parsedUrl)
    {
        $host = $parsedUrl['host'] ?? '';

        if (strpos($host, 'www.iqiyi.com') !== false) {
            $mobileUrl = str_replace('www.iqiyi.com', 'm.iqiyi.com', $url);
            $mobileHtml = $this->httpGet($mobileUrl, true);
            if ($mobileHtml) {
                $mobileCandidates = $this->extractTitleCandidates($mobileHtml);
                foreach ($mobileCandidates as &$c) {
                    $c['source'] = 'mobile_' . $c['source'];
                }
                unset($c);

                $pcHtml = $this->httpGet($url, false);
                $pcCandidates = [];
                if ($pcHtml) {
                    $pcCandidates = $this->extractTitleCandidates($pcHtml);
                    foreach ($pcCandidates as &$c) {
                        $c['source'] = 'pc_' . $c['source'];
                    }
                    unset($c);
                }

                $allCandidates = array_merge($mobileCandidates, $pcCandidates);
                $best = $this->selectBestTitle($allCandidates);

                if ($best) {
                    $allHtml = $mobileHtml . ($pcHtml ?: '');
                    $episode = $this->extractEpisode($allHtml, $best['title']);
                    $cleaned = $this->cleanTitle($best['title']);

                    if ($cleaned && mb_strlen($cleaned, 'UTF-8') >= 2) {
                        return [
                            'name' => $cleaned,
                            'episode' => $episode,
                            'source' => $best['source'],
                        ];
                    }
                }
            }
        }

        return $this->parseSmart($url, $parsedUrl);
    }
}
