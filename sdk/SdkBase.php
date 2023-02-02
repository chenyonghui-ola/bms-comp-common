<?php

namespace Imee\Comp\Common\Sdk;

use Imee\Comp\Common\Log\LoggerProxy;

class SdkBase
{
    const FORMAT_JSON = 'json';
    const FORMAT_TEXT = 'text';

    private $_respFormat;
    private $_apiTimeout;//请求超时时间设定
    private $_apiWarning;//超过该请求时间的请求，将被记录警告


    protected $_lastCode;
    protected $_lastError;

    protected $_allowCode = array(200);

    protected $_postContentType = 'application/x-www-form-urlencoded; charset=UTF-8';
    protected $_postJsonType = 'application/json; charset=UTF-8';

    public function __construct($format = self::FORMAT_JSON, $timeout = 5, $waning = 0.03)
    {
        $this->_respFormat = $format;
        $this->_apiTimeout = $timeout;
        $this->_apiWarning = $waning;
    }

    private $_https_cert;
    private $_https_key;

    public function setPem($cert, $key)
    {
        $this->_https_cert = $cert;
        $this->_https_key = $key;
    }

    public function setContentType($type)
    {
        $this->_postContentType = $type;
    }

    public function requestFromProxy($url, $is_post = false, $post = null, array $header = null, $format = null)
    {
        $proxyUrl = PROXY_URL . 'forward';
        $data = array(
            'url'     => urlencode($url),
            'is_post' => $is_post,
            'post'    => is_null($post) ? '' : $post,
            'header'  => is_null($header) ? '' : $header,
            'format'  => is_null($format) ? '' : $format,
        );
        $this->_apiTimeout = 10;
        $post = array('data' => base64_encode(json_encode($data)));
        LoggerProxy::instance()->warning("requestFromProxy::request\t" . json_encode($post));
        $resp = $this->httpRequest($proxyUrl, true, $post, null, self::FORMAT_JSON);
        LoggerProxy::instance()->warning("requestFromProxy::response\t" . json_encode($resp));
        if ($resp && ($resp['code'] == 200)) {
            $data = isset($resp['data']) ? $resp['data'] : null;
            return $data;
        }
        return null;
    }

    public function request($url, $is_post = false, $post = null, array $header = null, $format = null, $show_info = false)
    {
        return $this->httpRequest($url, $is_post, $post, $header, $format, null, false, $show_info);
    }

    //带有重试的http请求
    public function requestN($url, $is_post = false, $post = null, array $header = null, $format = null, $rN = 1)
    {
        $num = 0;
        while ($num <= $rN) {
            $num++;
            $r = $this->httpRequest($url, $is_post, $post, $header, $format);
            if (!is_null($r)) {
                return $r;
            }
        }
        return $r;
    }

    public function httpRequest($url, $is_post = false, $post = null, array $header = null, $format = null, $method = null, $json = false, $show_info = false)
    {
        if (is_null($format)) {
            $format = $this->_respFormat;
        }
        $this->_lastError = null;
        $begin = $this->microtimeFloat();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_apiTimeout);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($method != null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($is_post) {
            if (is_null($header)) {
                $header = array();
            }
            if (is_array($post)) {
                $post = ($json ? json_encode($post) : http_build_query($post));
            }
            $header[] = 'Content-Length: ' . strlen($post);
            $header[] = 'Content-Type: ' . ($json ? $this->_postJsonType : $this->_postContentType);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post ? $post : '');
            $header[] = 'Expect:';
        }

        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if ($this->_https_cert && $this->_https_key) {
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->_https_cert);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->_https_key);
        }


        $resp = curl_exec($ch);
        $info = curl_getinfo($ch);
        $this->_lastError = curl_error($ch);
        curl_close($ch);

        $used_time = sprintf("%0.4f", $this->microtimeFloat() - $begin);
        $path = $this->getQueryPath($url);
        LoggerProxy::instance()->warning("SdkHttpInfo::{$url}\t{$used_time}\t{$info['namelookup_time']}\t{$info['connect_time']}");
        if ($url == 'https://e.sm.cn/api/uploadConversions') {
            LoggerProxy::instance()->warning("SdkHttpSm::{$path}\t{$used_time}\t" . var_export($info, true));
        }
        $this->_lastCode = $info['http_code'];
        if (in_array($info['http_code'], $this->_allowCode)) {
            if ($format == self::FORMAT_JSON) {
                $json = @json_decode($resp, true);
                if ($json !== null) {
                    if ($used_time > $this->_apiWarning) {
                        LoggerProxy::instance()->warning("SdkHttpWarning::{$path}\t{$used_time}\t{$info['namelookup_time']}\t{$info['connect_time']}");
                    }
                    return $json;
                } else {
                    LoggerProxy::instance()->error("SdkJsonParseError::{$path}\t" . preg_replace("/\s/", "-^-", $resp));
                }
            } else {
                if ($used_time >= 0.2) {
                    LoggerProxy::instance()->warning("SdkHttpWarning::{$path}\t{$used_time}\t" . var_export($info, true));
                } elseif ($used_time > $this->_apiWarning) {
                    LoggerProxy::instance()->warning("SdkHttpWarning::{$path}\t{$used_time}\t{$info['namelookup_time']}\t{$info['connect_time']}");
                }
                return $resp;
            }
        } else {
            $SERVER_REQUEST_URI = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            LoggerProxy::instance()->error("SdkHttpError::{$SERVER_REQUEST_URI}\t{$url}\t{$path}\t{$info['http_code']}\t{$used_time}\t{$info['namelookup_time']}\t{$info['connect_time']}\t{$this->_lastError}");
        }
        return $show_info ? ['used_time' => $used_time, 'http_code' => $info['http_code'], 'connect_time' => $info['connect_time'], 'error' => $this->_lastError] : null;
    }

    public function getLastCode()
    {
        return $this->_lastCode;
    }

    public function getLastError()
    {
        return $this->_lastError;
    }

    protected function appendQuery($url, $vars)
    {
        return $url . (preg_match('/\?/', $url) ? '&' : '?') . $vars;
    }

    protected function getQueryPath($url)
    {
        $info = parse_url($url);
        return $info['scheme'] . '://' . $info['host'] . (isset($info['path']) ? $info['path'] : '/');
    }

    /**
     * POST 请求 根据header 头信息传递参数
     * @param $url
     * @param $data
     * @param array $header
     * @return bool|string
     */
    public function httpPost($url, $data, $header = array())
    {
        $data = http_build_query($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $header[] = 'Content-Type: application/x-www-form-urlencoded;';
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data ?? '');

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            return $error;
        }
        curl_close($ch);
        return $result;
    }

    public function httpGet($url, $data, $header = array())
    {
        $ch = curl_init();
        $data = http_build_query($data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            return $error;
        }
        curl_close($ch);
        return $result;
    }

    private function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
