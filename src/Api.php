<?php

namespace kkboxMusic;

use GuzzleHttp\Client;
use kkboxMusic\Storage;
use Symfony\Component\DomCrawler\Crawler;

class Api
{

    protected $_client;

    protected $_clientId;
    protected $_clientSecret;

    private $_errMsg = '';

    public function __construct($clientId, $clientSecret, $proxy = '')
    {
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;

        $config = [];
        if ($proxy) {
            $config['proxy'] = $proxy;
        }
        $this->_client = new Client($config);
    }

    /**
     * oauth2 token
     * @return string
     */
    private function _getToken()
    {
        $dbName = 'accessToken';
        $result = Storage::init()->get($dbName, $this->_clientId);
        // 是否已存在且未过期
        if ($result && !empty($result['token'])) {
            if (isset($result['expires']) && $result['expires'] > time()) {
                return $result['token'];
            }
            // refresh token
            $params = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $result['token']
            ];
        } else {
            $params = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->_clientId,
                'client_secret' => $this->_clientSecret
            ];
        }

        $url = 'https://account.kkbox.com/oauth2/token';
        $option = ['form_params' => $params];

        $result = $this->_sendRequest('post', $url, $option, false);
        if ($result === false) {
            return $this->_error();
        }

        $token = $result['access_token'];
        $expires = $result['expires_in'] + time();
        Storage::init()->set($dbName, $this->_clientId, ['token' => $token, 'expires' => $expires]);
        return $token;
    }

    /**
     * 搜索
     * @param string $keyword
     * @param string $type 可选 track,album,artist,playlist
     * @param integer $page 页数，默认1
     * @param integer $pageSize 每页条数，默认15，最大50
     * @return array
     */
    public function search($keyword, $type = '', $page = 1, $pageSize = 15)
    {
        $page > 0 || $page = 1;
        $pageSize > 0 || $pageSize = 15;
        $url = 'https://api.kkbox.com/v1.1/search';
        $param = [
            'q' => $keyword,
            'type' => $type ?: 'track,album,artist,playlist',
            'territory' => 'HK', // 可选 HK,JP,MY,SG,TW
            'offset' => ($page - 1) * $pageSize,
            'limit' => $pageSize
        ];
        $url .= '?' . http_build_query($param);

        $result = $this->_sendRequest('get', $url);
        if ($result === false) {
            return $this->_error();
        }
        return $this->_success($result);
    }

    /**
     * 搜索歌手
     * @param string $keyword 歌手名
     * @return array
     */
    public function searchSinger($keyword)
    {
        return $this->search($keyword, 'artist', 1, 50);
    }

    /**
     * 搜索歌曲
     * @param string $keyword 歌曲名
     * @param integer $page 页数，默认1
     * @param integer $pageSize 每页条数，默认15
     * @return array
     */
    public function searchSongs($keyword, $page = 1, $pageSize = 15)
    {
        return $this->search($keyword, 'track', $page, $pageSize);
    }

    /**
     * 获取歌手信息
     * @param string $singerId 歌手ID
     * @return array
     */
    public function getSingerInfo($singerId)
    {
        $url = 'https://api.kkbox.com/v1.1/artists/' . $singerId . '?territory=HK';
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        // 粉絲數
        try {
            $url = $data['url'];
            $response = $this->_client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            $converter = new \Symfony\Component\CssSelector\CssSelectorConverter($html);
            $xpath = $converter->toXPath('body > div.masthead > div.container > div.row > div.col-8 > div.artist-info > div.artist-statistic > ul > li');
            $text = $crawler->filterXPath($xpath)->last()->text();
            $text = str_replace('粉絲：', '', $text);
            $data['followers'] = trim($text);
        } catch (\Exception $e) {
            $data['followers'] = 0;
        }
        return $this->_success($data);
    }

    /**
     * 获取歌手专辑
     * @param string $singerId 歌手ID
     * @return array
     */
    public function getSingerAlbums($singerId)
    {
        $url = 'https://api.kkbox.com/v1.1/artists/' . $singerId . '/albums?territory=HK&limit=500'; // limit最大500

        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        unset($data['paging']);
        foreach ($data['data'] as &$val) {
            unset($val['artist']);
        }
        return $this->_success($data);
    }

    /**
     * 获取歌手热门歌曲
     * @param string $singerId 歌手ID
     * @return array
     */
    public function getSingerTopSongs($singerId)
    {
        $url = 'https://api.kkbox.com/v1.1/artists/' . $singerId . '/top-tracks?territory=HK&limit=500'; // limit最大500
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }
        
        unset($data['paging']);
        return $this->_success($data);
    }

    /**
     * 获取专辑歌曲
     * @param string $albumId 专辑ID
     * @return array
     */
    public function getAlbumSongs($albumId)
    {
        $url = 'https://api.kkbox.com/v1.1/albums/' . $albumId . '/tracks?territory=HK&limit=500'; // limit最大500
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        unset($data['paging']);
        return $this->_success($data);
    }

    /**
     * 获取歌曲信息
     * @param mixed $songIds 歌曲ID
     * @return array
     */
    public function getSongs($songIds)
    {
        if (is_array($songIds)) {
            $songIds = implode(',', $songIds);
        }
        $url = 'https://api.kkbox.com/v1.1/tracks?ids=' . $songIds . '&territory=HK';
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        isset($data['data']) && $data = $data['data'];
        return $this->_success($data);
    }

    /**
     * 获取榜单列表
     * @return array
     */
    public function getCharts()
    {
        $url = 'https://api.kkbox.com/v1.1/charts?territory=HK';
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        unset($data['paging']);
        return $this->_success($data);
    }

    /**
     * 获取榜单歌曲
     * @param string $chartId 榜单ID
     * @return array
     */
    public function getChartSongs($chartId)
    {
        $url = 'https://api.kkbox.com/v1.1/charts/' . $chartId . '/tracks?territory=HK&limit=500';
        
        $data = $this->_sendRequest('get', $url);
        if ($data === false) {
            return $this->_error();
        }

        unset($data['paging']);
        return $this->_success($data);
    }

    /**
     * 获取歌手所在榜单
     * @param string $singerIds 歌手ID
     * @return array
     */
    public function getSingerCharts($singerIds)
    {
        if (!is_array($singerIds)) {
            $singerIds = explode(',', $singerIds);
        }
        $singerIds = array_flip($singerIds);
        $charts = $this->getCharts()['data'];
        if (!$charts) {
            return $this->_error('no charts found.');
        }
        $data = [];
        foreach ($charts['data'] as $chart) {
            $list = $this->getChartSongs($chart['id'])['data'];
            if (!$list) {
                continue;
            }
            $songs = [];
            foreach ($list['data'] as $key => $song) {
                $singer = $song['album']['artist'];
                if (isset($singerIds[$singer['id']])) {
                    $song['rank'] = $key + 1;
                    $songs[] = $song;
                }
            }
            if ($songs) {
                $data[] = [
                    'chart' => $chart,
                    'songs' => $songs
                ];
            }
        }
        return $this->_success($data);
    }

    private function _sendRequest($method, $url, $option = [], $auth = true)
    {
        if ($auth) {
            $option['headers']['authorization'] = 'Bearer '. $this->_getToken();
        }
        try {
            if ($method == 'post') {
                $response = $this->_client->post($url, $option);
            } else {
                $response = $this->_client->get($url, $option);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->_error(__METHOD__. ', client error: [' . $response->getStatusCode().']'. $e->getMessage(), false);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            return $this->_error(__METHOD__. ', server error: [' . $response->getStatusCode().']'. $e->getMessage(), false);
        } catch (\Exception $e) {
            return $this->_error(__METHOD__. ', other error: [' . $response->getStatusCode().']'. $e->getMessage(), false);
        }
        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }


    private function _success($data = [])
    {
        return ['ret' => true, 'data' => $data, 'msg' => ''];
    }

    private function _error($msg = '', $isArray = true)
    {
        if ($isArray) {
            return ['ret' => false, 'data' => null, 'msg' => $msg ?: $this->_errMsg];
        }

        $this->_errMsg = '';
        return false;
    }
}
