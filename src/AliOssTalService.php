<?php

namespace ArcherZdip\AliOssTal;

use OSS\OssClient;

class AliOssTalService
{

    /**
     * oss实例化
     * @var OssClient
     */
    public $ossClient;

    /**
     * 访问域名
     * @var string
     */
    public $endPoint;

    /**
     * 存储空间
     * @var string
     */
    public $bucket;


    public function __construct($accessKeyId, $accessKeySecret,$isInternal, $isCName=null)
    {
        $this->setEndPoint($isInternal);

        $this->ossClient = new OssClient(
            $accessKeyId,
            $accessKeySecret,
            $this->endPoint,
            $isCName
        );
    }

    /**
     * 设置访问域名（endpoint）
     *
     * @param $isInternal
     * @return $this
     */
    public function setEndPoint($isInternal)
    {
        $this->endPoint = $isInternal ? config('aliosstal.endpoint_internal') : config('aliosstal.endpoint');
        return $this;
    }

    /**
     * 切换存储空间
     *
     * @param $bucket
     * @return $this
     */
    public function on($bucket = null)
    {
        $this->bucket = is_null($bucket) ? config('aliosstal.bucket') : $bucket;

        return $this;
    }

    /**
     * 指定的文件名和路径的上传 Object
     *
     * @param $filePath 物理文件的地址
     * @param null $ossKey 所要上传 Object 的Key，可选
     * @param array $options 可包含但不限于以下 Key:
     *
     * @return array|null array('hash' => 'hashed data', 'key' => 'oss key');
     */
    public function uploadFile($filePath, $ossKey = null, array $options = [])
    {
        $attempts = array_pull($options, 'attempts', 3);
        $result = null;
        if (is_null($ossKey)) {
            $prefix = array_pull($options, 'prefix', 'assets');
            $eTag = strtolower(array_pull($options, 'eTag', md5_file($filePath)));
            $ossKey = join('/', array_filter([$prefix, substr($eTag, 0, 2), substr($eTag, 2, 2), $eTag]));
        }
        do {
            try {
                $result = $this->ossClient->uploadFile($this->bucket, $ossKey, $filePath, $options);
                if (is_array($result) && isset($result['etag']) && $result['etag']) {
                    $hash = $this->trimQuote($result['etag']);
                    $result = ['hash' => $hash, 'key' => $ossKey];
                    break;
                }
            } catch (\Throwable $ex) {
                $result = null;
            }
            usleep(5000);
            $attempts--;
        } while ($attempts > 0);

        return $result;
    }


    /**
     * 指定的文件名和内容的上传 Object
     *
     * @param string $content 文件内容
     * @param string $ossKey 所要上传 Object 的Key，可选
     * @param array $options 可包含但不限于以下 Key:
     * <li>Bucket(string, 可选) - OSS Bucket，默认为配置文件中的 bucket</li>
     * <li>attempts(integer，可选) - 上传重试次数，默认 3 次</li>
     * @return null|array array('hash' => 'hashed data', 'key' => 'oss key');
     */
    public function uploadContent($content, $ossKey = null, array $options = [])
    {
        $attempts = array_pull($options, 'attempts', 3);
        $result = null;
        if (is_null($ossKey)) {
            $prefix = array_pull($options, 'prefix', 'assets');
            $eTag = strtolower(array_pull($options, 'eTag', md5($content)));
            $ossKey = join('/', array_filter([$prefix, substr($eTag, 0, 2), substr($eTag, 2, 2), $eTag]));
        }
        do {
            try {
                $result = $this->ossClient->putObject($this->bucket, $ossKey, $content, $options);
                if (is_array($result) && isset($result['etag']) && $result['etag']) {
                    $hash = $this->trimQuote($result['etag']);
                    $result = ['hash' => $hash, 'key' => $ossKey, 'url' => static::getUrl($ossKey)];
                    break;
                }
            } catch (\Throwable $ex) {
                $result = null;
            }
            usleep(5000);
            $attempts--;
        } while ($attempts > 0);

        return $result;
    }

    /**
     * 获取远端地址的图片 保存到本地 并上传
     * @param string $url
     * @return null|array
     */
    public function uploadRemoteFile(string $url)
    {
        $client = new HttpClient([
            'http_errors' => false,
            'verify' => false,
        ]);
        $result = null;
        $fn = tempnam(sys_get_temp_dir(), 'php_oss_');
        $r = fopen($fn, 'w');
        try {
            $client->request('GET', $url, ['sink' => $r]);
            $result = $this->uploadFile($fn);
        } catch (\Throwable $ex) {
            //
        } finally {
            @fclose($r);
            @unlink($fn);
        }

        return $result;
    }

    /**
     * 指定上传类 `UploadedFile` 的上传 Object
     *
     * @param UploadedFile $file
     * @param array $options 可包含但不限于以下 Key:
     * <li>Bucket(string, 可选) - OSS Bucket，默认为配置文件中的 bucket</li>
     * <li>attempts(integer，可选) - 上传重试次数，默认 3 次</li>
     * <li>prefix(string，可选) - 路径前缀，默认 products</li>
     * <li>eTag(string，可选) 当前文件对象文件的 md5 值，用于校验文件上传正确，默认当前文件内容的 md5 值</li>
     *
     * @return null|array array('hash' => 'hashed data', 'key' => 'oss key');
     */
    public function uploadFileObject(UploadedFile $file, array $options = [])
    {
        $prefix = array_pull($options, 'prefix', 'assets');
        $eTag = strtolower(array_pull($options, 'eTag', md5_file($file->getPathname())));
        $ext = $file->getClientOriginalExtension();

        if ($ext) {
            $ext = '.' . $ext;
        }

        $ossKey = join('/', array_filter([$prefix, substr($eTag, 0, 2), substr($eTag, 2, 2), $eTag . $ext]));

        if (!isset($options['ContentType'])) {
            $options['ContentType'] = $file->getClientMimeType();
        }

        return $this->uploadFile($file->getPathname(), $ossKey, $options);
    }

    /**
     * 生成预签名URL
     *
     * @param $ossKey 所要上传 Object 的 Key
     * @param array $options 可包含但不限于以下 Key:
     * <li>Bucket(string, 可选) - OSS Bucket，默认为配置文件中的 bucket</li>
     * @return string
     * @throws \OSS\Core\OssException
     */
    public function getUrl($ossKey, array $options = [])
    {
        $timeout = array_pull($options, 'timeout', 86400);

        if ($filename = array_pull($options, 'saveAs')) {
            $options['response-content-disposition'] = 'attachment;filename="' . $filename . '";filename*=utf-8\'\'' . rawurlencode($filename);
        }

        return $this->ossClient->signUrl($this->bucket, $this->urlencode(ltrim($ossKey, '/')), $timeout, OssClient::OSS_HTTP_GET, $options);
    }

    /**
     * 生成公用URL
     *
     * @param $ossKey
     * @return mixed
     * @throws \OSS\Core\OssException
     */
    public function getPublicUrl($ossKey)
    {
        $url = $this->getUrl($ossKey);

        return explode('?', $url)[0];
    }

    /**
     * 获取域名
     *
     * @param null $bucket
     * @return mixed
     */
    public function getDomain($bucket = null)
    {
        $bucket = $bucket ?: config('oss.bucket');

        $url = config('oss.endpoint');

        if (mb_strpos('://', $url) === false) {
            $url = 'http://' . $url;
        }

        return str_replace('://', '://' . $bucket . '.', $url);
    }

    private function trimQuote($str)
    {
        return strtolower(trim($str, '"\''));
    }

    private function urlencode($path)
    {
        return str_replace('%2F', '/', rawurlencode($path));
    }

}