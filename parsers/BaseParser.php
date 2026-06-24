<?php

abstract class BaseParser
{
    protected $platform = '';
    protected $platformName = '';
    protected $hosts = [];

    protected $defaultTitleKeywords = [];
    protected $mobileUrlTemplate = '';

    abstract public function parse($url, $parsedUrl);

    public function getPlatform()
    {
        return $this->platform;
    }

    public function getPlatformName()
    {
        return $this->platformName;
    }

    public function getHosts()
    {
        return $this->hosts;
    }

    public function matchHost($host)
    {
        $host = strtolower($host);
        foreach ($this->hosts as $domain) {
            if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain) {
                return true;
            }
        }
        return false;
    }

    protected function httpGet($url, $isMobile = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

        if ($isMobile) {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1');
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $response !== false) ? $response : false;
    }

    protected function parseSmart($url, $parsedUrl)
    {
        $candidates = [];

        $pcHtml = $this->httpGet($url, false);
        if ($pcHtml) {
            $pcCandidates = $this->extractTitleCandidates($pcHtml);
            foreach ($pcCandidates as $c) {
                $c['source'] = 'pc_' . $c['source'];
                $candidates[] = $c;
            }
        }

        $mobileUrl = $this->getMobileUrl($url, $parsedUrl);
        if ($mobileUrl && $mobileUrl !== $url) {
            $mobileHtml = $this->httpGet($mobileUrl, true);
            if ($mobileHtml) {
                $mobileCandidates = $this->extractTitleCandidates($mobileHtml);
                foreach ($mobileCandidates as $c) {
                    $c['source'] = 'mobile_' . $c['source'];
                    $candidates[] = $c;
                }
            }
        }

        $best = $this->selectBestTitle($candidates);
        if (!$best) return false;

        $episode = 1;
        $allHtml = ($pcHtml ?: '') . ($mobileHtml ?: '');
        $episode = $this->extractEpisode($allHtml, $best['title']);

        $cleaned = $this->cleanTitle($best['title']);
        if (empty($cleaned) || mb_strlen($cleaned, 'UTF-8') < 2) {
            return false;
        }

        return [
            'name' => $cleaned,
            'episode' => $episode,
            'source' => $best['source'],
        ];
    }

    protected function getMobileUrl($url, $parsedUrl)
    {
        if (!empty($this->mobileUrlTemplate)) {
            $host = $parsedUrl['host'] ?? '';
            foreach ($this->hosts as $domain) {
                if (strpos($host, $domain) !== false) {
                    $mobileHost = 'm.' . $domain;
                    return str_replace($host, $mobileHost, $url);
                }
            }
        }
        return $url;
    }

    protected function extractTitleCandidates($html)
    {
        $candidates = [];

        $ogTitle = $this->extractMetaContent($html, 'og:title');
        if ($ogTitle) $candidates[] = ['title' => $ogTitle, 'source' => 'og_title', 'score' => 80];

        $ogVideoTitle = $this->extractMetaContent($html, 'og:video:title');
        if ($ogVideoTitle) $candidates[] = ['title' => $ogVideoTitle, 'source' => 'og_video_title', 'score' => 85];

        $videoTitle = $this->extractMetaContent($html, 'video:title');
        if ($videoTitle) $candidates[] = ['title' => $videoTitle, 'source' => 'video_title', 'score' => 85];

        $itempropName = $this->extractMetaContent($html, 'name', 'itemprop');
        if ($itempropName) $candidates[] = ['title' => $itempropName, 'source' => 'itemprop_name', 'score' => 75];

        $metaTitle = $this->extractMetaContent($html, 'title', 'name');
        if ($metaTitle) $candidates[] = ['title' => $metaTitle, 'source' => 'meta_title', 'score' => 60];

        $titleTag = $this->extractTitleTag($html);
        if ($titleTag) $candidates[] = ['title' => $titleTag, 'source' => 'title_tag', 'score' => 50];

        $jsonLd = $this->extractJsonLdName($html);
        if ($jsonLd) $candidates[] = ['title' => $jsonLd, 'source' => 'json_ld', 'score' => 90];

        $twitterTitle = $this->extractMetaContent($html, 'twitter:title');
        if ($twitterTitle) $candidates[] = ['title' => $twitterTitle, 'source' => 'twitter_title', 'score' => 70];

        return $candidates;
    }

    protected function extractMetaContent($html, $attrValue, $attrName = 'property')
    {
        $patterns = [
            '/<meta[^>]*' . $attrName . '=["\']' . preg_quote($attrValue, '/') . '["\'][^>]*content=["\']([^"\']+)["\']/is',
            '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*' . $attrName . '=["\']' . preg_quote($attrValue, '/') . '["\']/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = trim($matches[1]);
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!empty($title) && mb_strlen($title, 'UTF-8') > 1) {
                    return $title;
                }
            }
        }
        return '';
    }

    protected function extractTitleTag($html)
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim($matches[1]);
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $title;
        }
        return '';
    }

    protected function extractJsonLdName($html)
    {
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = json_decode($jsonStr, true);
                if ($data) {
                    if (isset($data['name']) && is_string($data['name'])) {
                        return trim($data['name']);
                    }
                    if (isset($data['headline']) && is_string($data['headline'])) {
                        return trim($data['headline']);
                    }
                    if (isset($data['@graph']) && is_array($data['@graph'])) {
                        foreach ($data['@graph'] as $item) {
                            if (isset($item['name'])) return trim($item['name']);
                        }
                    }
                }
            }
        }
        return '';
    }

    protected function selectBestTitle($candidates)
    {
        if (empty($candidates)) return null;

        $scored = [];
        foreach ($candidates as $c) {
            $title = $c['title'];
            $score = $c['score'];

            if ($this->isDefaultOrInvalid($title)) {
                continue;
            }

            $len = mb_strlen($title, 'UTF-8');
            if ($len >= 2 && $len <= 20) {
                $score += 15;
            } elseif ($len > 20 && $len <= 40) {
                $score += 5;
            } elseif ($len > 60) {
                $score -= 20;
            }

            if (preg_match('/第\s*\d+\s*[集期话]/u', $title)) {
                $score += 10;
            }

            $cleaned = $this->cleanTitle($title);
            $cleanedLen = mb_strlen($cleaned, 'UTF-8');
            if ($cleanedLen >= 2 && $cleanedLen <= 15) {
                $score += 10;
            }

            $scored[] = [
                'title' => $title,
                'source' => $c['source'],
                'score' => $score,
            ];
        }

        if (empty($scored)) return null;

        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $scored[0];
    }

    protected function isDefaultOrInvalid($title)
    {
        $title = trim($title);
        if (empty($title) || mb_strlen($title, 'UTF-8') < 2) {
            return true;
        }

        $defaults = array_merge([
            '在线观看', '高清在线观看', '免费在线观看',
            '视频', '高清视频', '完整版', '高清完整版',
            '电视剧', '电影', '动漫', '综艺', '纪录片',
            '首页', '最新', '全部', '搜索',
            '播放', '正片', '预告', '花絮',
        ], $this->defaultTitleKeywords);

        $lowerTitle = mb_strtolower($title, 'UTF-8');
        foreach ($defaults as $kw) {
            if ($lowerTitle === mb_strtolower($kw, 'UTF-8')) {
                return true;
            }
        }

        if (preg_match('/^[a-z0-9\-\.]+$/i', $title) && !preg_match('/[\x{4e00}-\x{9fa5}]/u', $title)) {
            if (strlen($title) < 10) return true;
        }

        return false;
    }

    protected function extractEpisode($html, $title = '')
    {
        $candidates = [];

        if (!empty($title)) {
            $patterns = [
                ['/第\s*(\d+)\s*集/u', 95],
                ['/第\s*(\d+)\s*期/u', 95],
                ['/第\s*(\d+)\s*话/u', 95],
                ['/第\s*(\d+)\s*季/u', 50],
                ['/EP?\s*(\d+)/i', 85],
                ['/\[(\d+)\]/', 70],
                ['/(\d+)\s*集/u', 80],
                ['/(\d+)\s*期/u', 80],
                ['/^(\d+)$/', 60],
            ];

            foreach ($patterns as $patternInfo) {
                list($pattern, $score) = $patternInfo;
                if (preg_match($pattern, $title, $m)) {
                    $ep = intval($m[1]);
                    if ($ep > 0 && $ep < 5000) $candidates[] = ['ep' => $ep, 'score' => $score];
                }
            }

            if (preg_match('/(\d+)_\d+$/', $title, $m)) {
                $ep = intval($m[1]);
                if ($ep > 0 && $ep < 5000) {
                    $before = preg_replace('/\d+_\d+$/', '', $title);
                    if (mb_strlen($before, 'UTF-8') >= 2) {
                        $candidates[] = ['ep' => $ep, 'score' => 75];
                    }
                }
            }

            if (preg_match('/(\d+)-\d+$/', $title, $m)) {
                $ep = intval($m[1]);
                if ($ep > 0 && $ep < 5000) {
                    $before = preg_replace('/\d+-\d+$/', '', $title);
                    if (mb_strlen($before, 'UTF-8') >= 2) {
                        $candidates[] = ['ep' => $ep, 'score' => 70];
                    }
                }
            }

            if (preg_match('/[_-](\d+)$/', $title, $m)) {
                $ep = intval($m[1]);
                if ($ep > 0 && $ep < 5000) {
                    $before = preg_replace('/[_-]\d+$/', '', $title);
                    if (mb_strlen($before, 'UTF-8') >= 2) {
                        $lastChar = mb_substr($before, -1, 1, 'UTF-8');
                        if (preg_match('/[\x{4e00}-\x{9fa5}a-zA-Z]/u', $lastChar)) {
                            $candidates[] = ['ep' => $ep, 'score' => 80];
                        }
                    }
                }
            }
        }

        $htmlPatterns = [
            ['/"episode"\s*:\s*(\d+)/i', 80],
            ['/"ep"\s*:\s*(\d+)/i', 75],
            ['/"ep_index"\s*:\s*(\d+)/i', 75],
            ['/"currentEp"\s*:\s*(\d+)/i', 85],
            ['/"currEpisode"\s*:\s*(\d+)/i', 85],
            ['/"ep_num"\s*:\s*(\d+)/i', 70],
            ['/第\s*(\d+)\s*集/u', 60],
            ['/第\s*(\d+)\s*期/u', 60],
            ['/第\s*(\d+)\s*话/u', 60],
            ['/data-episode=["\'](\d+)/i', 70],
            ['/data-ep=["\'](\d+)/i', 70],
        ];

        foreach ($htmlPatterns as $patternInfo) {
            list($pattern, $score) = $patternInfo;
            if (preg_match($pattern, $html, $m)) {
                $ep = intval($m[1]);
                if ($ep > 0 && $ep < 5000) {
                    $candidates[] = ['ep' => $ep, 'score' => $score];
                }
            }
        }

        if (empty($candidates)) return 1;

        $counts = [];
        foreach ($candidates as $c) {
            $ep = $c['ep'];
            if (!isset($counts[$ep])) {
                $counts[$ep] = ['count' => 0, 'totalScore' => 0];
            }
            $counts[$ep]['count']++;
            $counts[$ep]['totalScore'] += $c['score'];
        }

        $bestEp = 1;
        $bestScore = 0;
        foreach ($counts as $ep => $info) {
            $totalScore = $info['totalScore'] + ($info['count'] - 1) * 20;
            if ($totalScore > $bestScore) {
                $bestScore = $totalScore;
                $bestEp = $ep;
            }
        }

        return $bestEp;
    }

    protected function cleanTitle($title)
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);
        $title = preg_replace('/\s+/u', ' ', $title);

        if (preg_match('/\x{300A}([^\x{300B}]+)\x{300B}/u', $title, $m)) {
            $inside = trim($m[1]);
            if (mb_strlen($inside, 'UTF-8') >= 2) {
                $title = $inside;
            }
        }

        $title = preg_replace('/[\x{300A}\x{300B}]/u', '', $title);
        $title = preg_replace('/[\x{300C}\x{300D}\x{300E}\x{300F}]/u', '', $title);

        $separators = ['-', '_', '|', '｜', '—', '·', '~', '～'];
        $title = $this->removeSiteSuffixFromSeparators($title, $separators);

        $title = $this->removePlatformSuffixes($title);

        if (preg_match('/^(.*?)第\s*\d+\s*[集期话](.*)$/u', $title, $m)) {
            $before = trim($m[1]);
            if (mb_strlen($before, 'UTF-8') >= 2) {
                $title = $before;
            }
        }

        $title = preg_replace('/第\s*\d+\s*[集期话].*$/u', '', $title);

        $title = $this->cleanEpisodeSuffix($title);

        $title = $this->mbTrim($title, " \t\n\r\0\x0B-_—|｜·[]()（）【】《》「」『』、，,。.");
        return $title;
    }

    protected function cleanEpisodeSuffix($title)
    {
        if (preg_match('/^(.*?)\d+_\d+$/u', $title, $m)) {
            $before = trim($m[1]);
            if (mb_strlen($before, 'UTF-8') >= 2) {
                $title = $before;
            }
        }

        if (preg_match('/^(.*?)\d+-\d+$/u', $title, $m)) {
            $before = trim($m[1]);
            if (mb_strlen($before, 'UTF-8') >= 2) {
                $title = $before;
            }
        }

        if (preg_match('/^(.*?)[_-](\d+)$/u', $title, $m)) {
            $before = trim($m[1]);
            $epNum = intval($m[2]);
            if (mb_strlen($before, 'UTF-8') >= 2 && $epNum > 0 && $epNum < 5000) {
                $lastChar = mb_substr($before, -1, 1, 'UTF-8');
                if (preg_match('/[\x{4e00}-\x{9fa5}a-zA-Z]/u', $lastChar)) {
                    $title = $before;
                }
            }
        }

        if (preg_match('/^(.*?[^\d])\d+$/u', $title, $m)) {
            $before = trim($m[1]);
            $len = mb_strlen($before, 'UTF-8');
            $lastChar = mb_substr($before, -1, 1, 'UTF-8');
            $isChineseOrLetter = preg_match('/[\x{4e00}-\x{9fa5}a-zA-Z]/u', $lastChar);
            if ($len >= 2 && $isChineseOrLetter) {
                $title = $before;
            }
        }

        return $title;
    }

    protected function removeSiteSuffixFromSeparators($title, $separators)
    {
        $siteKeywords = array_merge([
            '在线观看', '高清在线观看', '免费在线观看',
            '完整版视频在线观看', '全集高清视频', '全集高清',
            '高清完整版', '高清视频', '高清', '视频',
            '完整版', '全集', '正片', '预告',
        ], $this->defaultTitleKeywords);

        foreach ($separators as $sep) {
            $parts = explode($sep, $title);
            if (count($parts) <= 1) continue;

            $keepParts = [];
            foreach ($parts as $part) {
                $part = trim($part);
                $isSite = false;
                foreach ($siteKeywords as $kw) {
                    if (mb_strpos($part, $kw, 0, 'UTF-8') !== false) {
                        $isSite = true;
                        break;
                    }
                }
                if (!$isSite && !empty($part)) {
                    $keepParts[] = $part;
                } else {
                    break;
                }
            }

            if (!empty($keepParts)) {
                $newTitle = implode($sep, $keepParts);
                if (mb_strlen($newTitle, 'UTF-8') >= 2) {
                    $title = $newTitle;
                    break;
                }
            }
        }

        return $title;
    }

    protected function removePlatformSuffixes($title)
    {
        $suffixes = array_merge([
            '腾讯视频', '优酷', '爱奇艺', '哔哩哔哩', 'bilibili', '芒果TV',
            '搜狐视频', 'PPTV', '乐视视频', '芒果tv',
            '_腾讯视频', '-腾讯视频', '_优酷', '-优酷',
            '_爱奇艺', '-爱奇艺', '_哔哩哔哩', '-哔哩哔哩',
            '_bilibili', '-bilibili', '_芒果TV', '-芒果TV',
            '高清在线观看', '完整版视频在线观看', '免费在线观看',
            '全集高清视频', '全集高清', '在线观看',
            '高清完整版', '完整版', '高清', 'HD', 'BD',
            '电影', '电视剧', '动漫', '综艺', '纪录片',
            '最新', '全集', '正片', '预告', '高清视频', '视频',
        ], $this->defaultTitleKeywords);

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($suffixes as $suffix) {
                $len = mb_strlen($suffix, 'UTF-8');
                if ($len == 0) continue;
                if (mb_substr($title, -$len, null, 'UTF-8') === $suffix) {
                    $result = mb_substr($title, 0, mb_strlen($title, 'UTF-8') - $len, 'UTF-8');
                    $title = $this->mbTrim($result, " \t-_—|｜·");
                    $changed = true;
                }
            }
        }

        return $title;
    }

    protected function mbTrim($str, $charlist = " \t\n\r\0\x0B")
    {
        $chars = preg_quote($charlist, '/');
        $pattern = '/^[' . $chars . ']+|[' . $chars . ']+$/u';
        return preg_replace($pattern, '', $str);
    }
}
