<?php

namespace jzkf\filemanager\services;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Aws\S3\S3Client;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Flysystem 服务类
 * 
 * 提供统一的文件系统抽象层，支持多种存储驱动
 */
class FlysystemService extends Component
{
    /**
     * @var array 存储驱动配置
     */
    public $storageConfig = [];
    
    /**
     * @var array Filesystem 实例缓存
     */
    private $_filesystems = [];
    
    /**
     * @var FinfoMimeTypeDetector MIME 类型检测器
     */
    private $_mimeDetector;
    
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        
        // 从配置文件加载存储配置
        if (empty($this->storageConfig)) {
            $this->storageConfig = Yii::$app->params['filemanager']['storage'] ?? [];
        }
        
        // 初始化 MIME 类型检测器
        $this->_mimeDetector = new FinfoMimeTypeDetector();
    }
    
    /**
     * 获取 Filesystem 实例
     * 
     * @param string|null $storage 存储驱动名称，null 使用默认驱动
     * @return Filesystem
     * @throws InvalidConfigException
     */
    public function getFilesystem($storage = null)
    {
        if ($storage === null) {
            $storage = $this->storageConfig['default'] ?? 'local';
        }
        
        // 从缓存返回已创建的实例
        if (isset($this->_filesystems[$storage])) {
            return $this->_filesystems[$storage];
        }
        
        // 创建适配器
        $adapter = $this->createAdapter($storage);
        
        // 创建 Filesystem 实例
        $filesystem = new Filesystem($adapter, [
            'visibility' => 'public',
        ]);
        
        // 缓存实例
        $this->_filesystems[$storage] = $filesystem;
        
        return $filesystem;
    }
    
    /**
     * 创建存储适配器
     * 
     * @param string $storage 存储驱动名称
     * @return FilesystemAdapter
     * @throws InvalidConfigException
     */
    protected function createAdapter($storage)
    {
        $drivers = $this->storageConfig['drivers'] ?? [];
        
        if (!isset($drivers[$storage])) {
            throw new InvalidConfigException("存储驱动 '{$storage}' 未配置");
        }
        
        $config = $drivers[$storage];
        
        switch ($storage) {
            case 'local':
                return $this->createLocalAdapter($config);
            
            case 's3':
            case 'aws':
            case 'aliyun_oss':
            case 'qcloud_cos':
                return $this->createS3Adapter($config);
            
            default:
                throw new InvalidConfigException("不支持的存储驱动: {$storage}");
        }
    }
    
    /**
     * 创建本地存储适配器
     * 
     * @param array $config
     * @return LocalFilesystemAdapter
     */
    protected function createLocalAdapter($config)
    {
        $basePath = Yii::getAlias($config['basePath'] ?? '@web/uploads');
        
        // 确保目录存在
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        
        return new LocalFilesystemAdapter($basePath);
    }
    
    /**
     * 创建 S3 兼容存储适配器
     * 
     * 支持 AWS S3、阿里云 OSS、腾讯云 COS 等
     * 
     * @param array $config
     * @return AwsS3V3Adapter
     * @throws InvalidConfigException
     */
    protected function createS3Adapter($config)
    {
        if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
            throw new InvalidConfigException("S3 配置不完整，需要 key、secret 和 bucket");
        }
        
        $clientConfig = [
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'region' => $config['region'] ?? 'us-east-1',
            'version' => $config['version'] ?? 'latest',
        ];
        
        // 自定义端点（用于阿里云 OSS、腾讯云 COS 等）
        if (!empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
            $clientConfig['use_path_style_endpoint'] = true;
        }
        
        $client = new S3Client($clientConfig);
        
        return new AwsS3V3Adapter(
            $client,
            $config['bucket'],
            $config['prefix'] ?? ''
        );
    }
    
    /**
     * 上传文件
     * 
     * @param string $source 源文件路径（本地临时文件）
     * @param string $destination 目标路径（相对路径）
     * @param string|null $storage 存储驱动
     * @param array $options 额外选项
     * @return array 文件信息
     * @throws \Exception
     */
    public function upload($source, $destination, $storage = null, $options = [])
    {
        $filesystem = $this->getFilesystem($storage);
        
        // 读取文件内容
        $stream = fopen($source, 'r');
        
        if ($stream === false) {
            throw new \Exception("无法读取源文件: {$source}");
        }
        
        try {
            // 上传文件
            $filesystem->writeStream($destination, $stream, [
                'visibility' => $options['visibility'] ?? 'public',
            ]);
            
            // 关闭流
            if (is_resource($stream)) {
                fclose($stream);
            }
            
            // 获取文件信息
            $size = $filesystem->fileSize($destination);
            $mimeType = $filesystem->mimeType($destination);
            
            return [
                'path' => $destination,
                'size' => $size,
                'mime_type' => $mimeType,
                'storage' => $storage ?? $this->storageConfig['default'] ?? 'local',
            ];
        } catch (\Exception $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw $e;
        }
    }
    
    /**
     * 读取文件内容
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return string 文件内容
     * @throws \Exception
     */
    public function read($path, $storage = null)
    {
        $filesystem = $this->getFilesystem($storage);
        return $filesystem->read($path);
    }
    
    /**
     * 读取文件流
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return resource
     * @throws \Exception
     */
    public function readStream($path, $storage = null)
    {
        $filesystem = $this->getFilesystem($storage);
        return $filesystem->readStream($path);
    }
    
    /**
     * 删除文件
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return bool
     */
    public function delete($path, $storage = null)
    {
        try {
            $filesystem = $this->getFilesystem($storage);
            $filesystem->delete($path);
            return true;
        } catch (\Exception $e) {
            Yii::error("删除文件失败: {$path}, 错误: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
    
    /**
     * 检查文件是否存在
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return bool
     */
    public function exists($path, $storage = null)
    {
        try {
            $filesystem = $this->getFilesystem($storage);
            return $filesystem->fileExists($path);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取文件大小
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return int 文件大小（字节）
     */
    public function size($path, $storage = null)
    {
        $filesystem = $this->getFilesystem($storage);
        return $filesystem->fileSize($path);
    }
    
    /**
     * 获取文件 MIME 类型
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return string MIME 类型
     */
    public function mimeType($path, $storage = null)
    {
        $filesystem = $this->getFilesystem($storage);
        return $filesystem->mimeType($path);
    }
    
    /**
     * 复制文件
     * 
     * @param string $source 源路径
     * @param string $destination 目标路径
     * @param string|null $storage 存储驱动
     * @return bool
     */
    public function copy($source, $destination, $storage = null)
    {
        try {
            $filesystem = $this->getFilesystem($storage);
            $filesystem->copy($source, $destination);
            return true;
        } catch (\Exception $e) {
            Yii::error("复制文件失败: {$source} -> {$destination}, 错误: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
    
    /**
     * 移动文件
     * 
     * @param string $source 源路径
     * @param string $destination 目标路径
     * @param string|null $storage 存储驱动
     * @return bool
     */
    public function move($source, $destination, $storage = null)
    {
        try {
            $filesystem = $this->getFilesystem($storage);
            $filesystem->move($source, $destination);
            return true;
        } catch (\Exception $e) {
            Yii::error("移动文件失败: {$source} -> {$destination}, 错误: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
    
    /**
     * 获取文件的公开 URL
     * 
     * @param string $path 文件路径
     * @param string|null $storage 存储驱动
     * @return string URL
     */
    public function url($path, $storage = null)
    {
        if ($storage === null) {
            $storage = $this->storageConfig['default'] ?? 'local';
        }
        
        $config = $this->storageConfig['drivers'][$storage] ?? [];
        
        // 本地存储
        if ($storage === 'local') {
            $baseUrl = $config['baseUrl'] ?? '';
            if (empty($baseUrl)) {
                $baseUrl = frontend_url();
            }
            
            if (!str_starts_with($baseUrl, 'http://') && !str_starts_with($baseUrl, 'https://')) {
                $baseUrl = '';
            }
            
            if (!empty($baseUrl) && !str_ends_with($baseUrl, '/')) {
                $baseUrl .= '/';
            }
            
            return $baseUrl . ltrim($path, '/');
        }
        
        // S3 兼容存储
        if (isset($config['cdnDomain']) && !empty($config['cdnDomain'])) {
            $domain = $config['cdnDomain'];
            if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
                $domain = 'https://' . $domain;
            }
            
            if (!str_ends_with($domain, '/')) {
                $domain .= '/';
            }
            
            return $domain . ltrim($path, '/');
        }
        
        // 默认使用 endpoint
        if (!empty($config['endpoint'])) {
            $endpoint = $config['endpoint'];
            if (!str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
                $endpoint = 'https://' . $endpoint;
            }
            
            $bucket = $config['bucket'] ?? '';
            $prefix = $config['prefix'] ?? '';
            
            return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($prefix . '/' . $path, '/');
        }
        
        return $path;
    }
    
    /**
     * 列出目录下的所有文件
     * 
     * @param string $directory 目录路径
     * @param bool $recursive 是否递归
     * @param string|null $storage 存储驱动
     * @return array
     */
    public function listContents($directory = '', $recursive = false, $storage = null)
    {
        $filesystem = $this->getFilesystem($storage);
        $contents = $filesystem->listContents($directory, $recursive);
        
        $files = [];
        foreach ($contents as $item) {
            $data = [
                'type' => $item->type(),
                'path' => $item->path(),
            ];
            
            // FileAttributes 特有属性
            if ($item instanceof \League\Flysystem\FileAttributes) {
                $data['size'] = $item->fileSize();
                $data['last_modified'] = $item->lastModified();
                if (method_exists($item, 'mimeType')) {
                    $data['mime_type'] = $item->mimeType();
                }
            }
            
            // 通用属性
            if (method_exists($item, 'visibility')) {
                $data['visibility'] = $item->visibility();
            }
            
            $files[] = $data;
        }
        
        return $files;
    }
}

