<?php

namespace OSS;

use Imee\Comp\Common\Log\LoggerProxy;
use Phalcon\Http\Request\File;
use OSS\Core\OssException;
use Config\ConfigAliyunOss;

class OssUpload
{
    private $_endpoint = 'oss-ap-southeast-1-internal.aliyuncs.com';
    private $_endpoint_v2 = 'oss-cn-hangzhou-internal.aliyuncs.com';
    private $_bucket;
    private $_lastError = null;

    private $_options = array(
        'checkmd5' => true,
    );

    const IMAGE = 'partying';
    const DOWNLOAD = 'ee-download';
    const LIVE = 'marco-live';
    const XS_IMAGE = 'partying';
    const EMR = 'xs-emr-data';
    const PT_CN_PROXY = 'partying-cn-proxy';

    const AccessKeyId = ConfigAliyunOss::OverseaAccessKeyId;
    const AccessKeySecret = ConfigAliyunOss::OverseaAccessKeySecret;

    const DownloadAccessKeyId = ConfigAliyunOss::DownloadAccessKeyId;
    const DownloadAccessKeySecret = ConfigAliyunOss::DownloadAccessKeySecret;

    const EmrAccessKeyId = ConfigAliyunOss::EmrAccessKeyId;
    const EmrAccessKeySecret = ConfigAliyunOss::EmrAccessKeySecret;

    // 海外KTV partying-voice
    const BUCKET_DEV = 'bb-admin-test';
    const PT_VOICE = 'partying_voice';
//	const PT_VOICE_END_POINT = 'http://oss-ap-southeast-1.aliyuncs.com';
    const PT_VOICE_END_POINT = 'http://partying-voice.oss-ap-southeast-1.aliyuncs.com/';

    public function __construct($bucket = OssUpload::IMAGE, $forceEndpoint = false)
    {
        $this->_bucket = $bucket;

        if ($bucket == OssUpload::PT_CN_PROXY) {
            $this->_endpoint = 'oss-cn-shenzhen.aliyuncs.com';
        }

        if ($bucket == OssUpload::PT_VOICE) {
            $this->_endpoint = self::PT_VOICE_END_POINT;
        }

        if (ENV == 'dev') {
            // 测试环境
            $bucket = self::BUCKET_DEV;
            $this->_bucket = $bucket;
            $this->_endpoint = 'oss-cn-hangzhou.aliyuncs.com';
        }

        if ($this->_bucket == self::EMR) {
            $this->_endpoint = $this->_endpoint_v2;
        }

        $this->_options[OssClient::OSS_HEADERS] = array(
            'Cache-Control' => 'max-age=31536000',
        );
    }

    //根据前缀匹配，删除对应的缩略缓存
    //不要试图使用此功能删除大量文件
    public static function removeCacheFromProxy($prefix)
    {
        if (!preg_match("/^[0-9]{6}\/[0-9]{2}\//i", $prefix)) return false;
        //先删除原图
        $client = new OssUpload('partying');
        $client->delete($prefix);

        //删除缓存
        $client = new OssUpload('partying-proxy');
        for ($i = 0; $i < 5; $i++) {
            $res = $client->listObjects(array(
                'prefix' => $prefix,
            ));
            if ($res === false) {
                usleep(1000 * 100);
                continue;
            }
            foreach ($res->getObjectList() as $val) {
                $client->delete($val->getKey());
            }
            break;
        }
        return true;
    }

    public function modifyMetaForObject($object)
    {
        $copyOptions = array(
            OssClient::OSS_HEADERS => array(
                'Cache-Control' => 'max-age=31536000',
                'Content-Type'  => 'image/gif',
            ),
        );
        $client = $this->client();
        if (!$client) return false;
        try {
            $client->copyObject($this->_bucket, $object, $this->_bucket, $object, $copyOptions);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        return true;
    }

    public function copyObject($from, $to)
    {
        $copyOptions = array();
        $client = $this->client();
        if (!$client) return false;
        try {
            $client->copyObject($this->_bucket, $from, $this->_bucket, $to, $copyOptions);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        return true;
    }

    public function putObject($object, $content)
    {
        $options = array();
        $client = $this->client();
        if (!$client) return false;
        try {
            $client->putObject($this->_bucket, $object, $content, $options);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        return true;
    }


    public function downloadToLocal($key, $localFile)
    {
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localFile,
        );
        $client = $this->client();
        if (!$client) return false;
        try {
            $client->getObject($this->_bucket, $key, $options);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        return true;
    }

    //删除oss里的某张图片
    public function delete($key)
    {
        $client = $this->client();
        $options = array();
        if (!$client) return false;
        try {
            $client->deleteObject($this->_bucket, $key, $options);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        return true;
    }

    public function listObjects($options = NULL)
    {
        $client = $this->client();
        if (!$client) return false;
        try {
            return $client->listObjects($this->_bucket, $options);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            file_put_contents('/tmp/oss_error.log', "date:" . date('Y-m-d H:i:s', time()) . "err:" . $e->getFile() . $e->getLine() . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function setOptions(array $options)
    {
        $this->_options = $options;
    }

    public function getError()
    {
        return $this->_lastError;
    }

    public function moveFile($localFile, $ext = null, $addIfExist = true)
    {
        $this->_lastError = null;
        if (is_null($ext)) $ext = $this->getExtension($localFile);
        if (is_null($ext)) {
            $this->_lastError = '文件扩展名不能为空';
            return false;
        }
        $md5 = @md5_file($localFile);
//		$rec = \ImeeImage::findFirstByMd5($md5);
//		if($rec){
//			if($addIfExist){
//				return $rec->path;
//			}else{
//				$this->_lastError = '当前文件已经存在';
//				return false;
//			}
//		}else{
        $dir = date('Ym/d/');
        $prefix = ip2long($_SERVER['SERVER_ADDR']);
        $remoteName = $dir . uniqid($prefix, true) . ($ext ? ('.' . $ext) : '');
        if (false === $this->moveFileTo($localFile, $remoteName)) {
            $this->_lastError = '文件向远程服务器存储失败';
            return false;
        }
//			$rec = new \ImeeImage();
//			$rec->md5 = $md5;
//			$rec->path = $remoteName;
//			$rec->dateline = time();
        try {
//				$rec->save();
            return $remoteName;
        } catch (\Exception $e) {
            $this->_lastError = '数据库记录失败，请重试';
            return false;
        }
//		}
    }

    public function moveFileMarco($localFile, $ext = null)
    {
        $this->_lastError = null;
        if (is_null($ext)) $ext = $this->getExtension($localFile);
        if (is_null($ext)) {
            $this->_lastError = '文件扩展名不能为空';
            return false;
        }
        $dir = date('Ym/d/');
        $remoteName = $dir . uniqid('', true) . ($ext ? ('.' . $ext) : '');
        if (false === $this->moveFileTo($localFile, $remoteName)) {
            $this->_lastError = '文件向远程服务器存储失败';
            return false;
        }
        return $remoteName;
    }

    private function getExtension($name)
    {
        $index = strrpos($name, '.');
        if ($index === false) return null;
        return substr($name, $index + 1);
    }

    /*
    * $remoteName 访问请求名，不带域名，不以 / 开始
    */
    public function moveHttpFileTo(File $file, $remoteName)
    {
        return $this->moveFileTo($file->getTempName(), $remoteName);
    }

    /*
    * $localFile 本地的绝对路径
    * $remoteName 访问请求名，不带域名，不以 / 开始
    */
    public function moveFileTo($localFile, $remoteName)
    {
        $client = $this->client();
        if (!$client) return false;
        try {
            // $res = $client->uploadFile($this->_bucket, $remoteName, $localFile, $this->_options);
            $res = $client->multiuploadFile($this->_bucket, $remoteName, $localFile, $this->_options);
        } catch (OssException $e) {
            print_r($e->getMessage());
            exit;
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage() . "::" . $this->_bucket . "::" . $remoteName . "::" . $localFile);
            return false;
        }
        return true;
    }

    public function client()
    {
        $AccessKeyId = self::AccessKeyId;
        $AccessKeySecret = self::AccessKeySecret;
        switch ($this->_bucket) {
            case self::DOWNLOAD:
                $AccessKeyId = self::DownloadAccessKeyId;
                $AccessKeySecret = self::DownloadAccessKeySecret;
                break;
            case self::EMR:
                $AccessKeyId = self::EmrAccessKeyId;
                $AccessKeySecret = self::EmrAccessKeySecret;
                break;
        }

        try {
            $ossClient = new OssClient($AccessKeyId, $AccessKeySecret, $this->_endpoint, false);
        } catch (OssException $e) {
            printf(__FUNCTION__ . "creating OssClient instance: FAILED\n");
            printf($e->getMessage() . "\n");
            file_put_contents('/tmp/oss_error.log', "date:" . date('Y-m-d H:i:s', time()) . "err:" . $e->getMessage() . "\n", FILE_APPEND);
            return null;
        }
        return $ossClient;
    }

    public function moveObject($from, $to)
    {
        $copyOptions = [];
        $client = $this->client();
        if (!$client) return false;
        try {
            $client->copyObject($this->_bucket, $from, $this->_bucket, $to, $copyOptions);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
            return false;
        }
        try {
            $client->deleteObject($this->_bucket, $from);
        } catch (OssException $e) {
            LoggerProxy::instance()->error($e->getFile() . $e->getLine() . $e->getMessage());
        }
        return true;
    }
}