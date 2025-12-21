<?php

namespace jzkf\filemanager;

use jzkf\filemanager\assets\Assets;

/**
 * file module definition class
 */
class FileManagerModule extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'jzkf\filemanager\controllers';

    /**
     * 布局
     * @var string
     */
    public $layout = '@app/views/layouts/main';

    /**
     * 上传配置 - 最大文件大小（字节）
     * @var int
     */
    public $uploadMaxSize = 10 * 1024 * 1024; // 10MB

    /**
     * 上传配置 - 允许的文件扩展名
     * @var array
     */
    public $uploadAllowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',  // 图片
        'mp4', 'avi', 'mov', 'wmv', 'flv',                   // 视频
        'mp3', 'wav', 'ogg', 'flac',                         // 音频
        'pdf',                                                // PDF
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',        // Office
        'zip', 'rar', '7z', 'tar', 'gz',                    // 压缩包
        'txt', 'md', 'csv', 'json', 'xml',                  // 文本
    ];

    /**
     * 上传配置 - 图片处理 - 原图最大尺寸
     * @var array [width, height]
     */
    public $uploadImageMaxDimensions = [1920, 1920];

    /**
     * 上传配置 - 图片处理 - 缩略图尺寸
     * @var array
     */
    public $uploadImageThumbnails = [
        'small' => [150, 150],   // 列表缩略图
        'medium' => [400, 400],  // 预览
        'large' => [720, 540],   // 详情页
    ];

    /**
     * 上传配置 - 图片处理 - 图片质量
     * @var int
     */
    public $uploadImageQuality = 85;

    /**
     * 上传配置 - 图片处理 - 是否生成 WebP 格式
     * @var bool
     */
    public $uploadImageEnableWebp = true;

    /**
     * 上传配置 - 图片处理 - WebP 质量
     * @var int
     */
    public $uploadImageWebpQuality = 80;

    /**
     * 存储配置 - 默认存储驱动
     * @var string
     */
    public $storageDefault = 'local';

    /**
     * 存储配置 - 存储驱动配置
     * 可在实例化模块时手动修改存储配置
     * 格式: [
     *     'local' => [...],
     *     's3' => [...],
     *     ...
     * ]
     * @var array|null
     */
    public $storageDrivers = null;

    /**
     * 安全配置 - 是否检查 MIME 类型
     * @var bool
     */
    public $securityCheckMimeType = true;

    /**
     * 安全配置 - 允许的 MIME 类型
     * @var array
     */
    public $securityAllowedMimeTypes = [
        // 图片
        'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml',
        
        // 视频
        'video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv',
        
        // 音频
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac',
        
        // 文档
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        
        // 压缩包
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip',
        
        // 文本
        'text/plain',
        'text/csv',
        'application/json',
        'application/xml',
    ];

    /**
     * 安全配置 - 文件名黑名单（不允许的字符）
     * @var array
     */
    public $securityFileNameBlacklist = ['..', '/', '\\', "\0", '<', '>', ':', '"', '|', '?', '*'];

    /**
     * 功能开关 - 文件去重
     * @var bool
     */
    public $featuresEnableDeduplication = true;

    /**
     * 功能开关 - 图片压缩
     * @var bool
     */
    public $featuresEnableCompression = true;

    /**
     * 功能开关 - 生成缩略图
     * @var bool
     */
    public $featuresEnableThumbnail = true;

    /**
     * 功能开关 - 生成 WebP
     * @var bool
     */
    public $featuresEnableWebp = true;

    /**
     * 功能开关 - 水印（未实现）
     * @var bool
     */
    public $featuresEnableWatermark = false;

    /**
     * 功能开关 - 版本管理（未实现）
     * @var bool
     */
    public $featuresEnableVersioning = false;

    /**
     * 分页配置 - 列表页每页数量
     * @var int
     */
    public $paginationPageSize = 20;

    /**
     * 分页配置 - 选择器每页数量
     * @var int
     */
    public $paginationPickerPageSize = 12;

    /**
     * 清理配置 - 自动清理
     * @var bool
     */
    public $cleanupEnableAutoCleanup = true;

    /**
     * 清理配置 - 未使用文件保留天数
     * @var int
     */
    public $cleanupUnusedFileDays = 30;

    /**
     * 清理配置 - 软删除文件保留天数
     * @var int
     */
    public $cleanupDeletedFileDays = 7;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        Assets::register(\Yii::$app->view);

        // 初始化默认存储驱动配置（如果未设置）
        if (empty($this->storageDrivers)) {
            $this->storageDrivers = $this->getDefaultStorageDrivers();
        }

        // 如果 storageDefault 使用了环境变量，需要处理
        if (function_exists('env')) {
            $envDriver = env('FILE_STORAGE_DRIVER');
            if ($envDriver !== null && $envDriver !== false) {
                $this->storageDefault = $envDriver;
            }
        }

        // 组装配置数组并设置到 params
        $config = [
            'upload' => [
                'maxSize' => $this->uploadMaxSize,
                'allowedExtensions' => $this->uploadAllowedExtensions,
                'image' => [
                    'maxDimensions' => $this->uploadImageMaxDimensions,
                    'thumbnails' => $this->uploadImageThumbnails,
                    'quality' => $this->uploadImageQuality,
                    'enableWebp' => $this->uploadImageEnableWebp,
                    'webpQuality' => $this->uploadImageWebpQuality,
                ],
            ],
            'storage' => [
                'default' => $this->storageDefault,
                'drivers' => $this->storageDrivers,
            ],
            'security' => [
                'checkMimeType' => $this->securityCheckMimeType,
                'allowedMimeTypes' => $this->securityAllowedMimeTypes,
                'fileNameBlacklist' => $this->securityFileNameBlacklist,
            ],
            'features' => [
                'enableDeduplication' => $this->featuresEnableDeduplication,
                'enableCompression' => $this->featuresEnableCompression,
                'enableThumbnail' => $this->featuresEnableThumbnail,
                'enableWebp' => $this->featuresEnableWebp,
                'enableWatermark' => $this->featuresEnableWatermark,
                'enableVersioning' => $this->featuresEnableVersioning,
            ],
            'pagination' => [
                'pageSize' => $this->paginationPageSize,
                'pickerPageSize' => $this->paginationPickerPageSize,
            ],
            'cleanup' => [
                'enableAutoCleanup' => $this->cleanupEnableAutoCleanup,
                'unusedFileDays' => $this->cleanupUnusedFileDays,
                'deletedFileDays' => $this->cleanupDeletedFileDays,
            ],
        ];

        \Yii::$app->params['filemanager'] = $config;

        // custom initialization code goes here
        if (!isset(\Yii::$app->i18n->translations['filemanager'])) {
            \Yii::$app->i18n->translations['filemanager'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => \Yii::$app->sourceLanguage,
                'basePath' => '@jzkf/filemanager/messages',
            ];
        }
    }

    /**
     * 获取默认存储驱动配置
     * @return array
     */
    protected function getDefaultStorageDrivers()
    {
        $getEnv = function($key, $default = null) {
            if (function_exists('env')) {
                $value = env($key);
                return $value !== null && $value !== false ? $value : $default;
            }
            return $default;
        };
        
        return [
            // 本地存储
            'local' => [
                'basePath' => '@frontend/web',
                'baseUrl' => '',
            ],
            
            // AWS S3
            's3' => [
                'key' => $getEnv('AWS_ACCESS_KEY_ID'),
                'secret' => $getEnv('AWS_SECRET_ACCESS_KEY'),
                'region' => $getEnv('AWS_DEFAULT_REGION', 'us-east-1'),
                'bucket' => $getEnv('AWS_BUCKET'),
                'prefix' => $getEnv('AWS_PREFIX', ''),
                'cdnDomain' => $getEnv('AWS_CDN_DOMAIN'),
            ],
            
            // 阿里云 OSS（兼容 S3 协议）
            'aliyun_oss' => [
                'key' => $getEnv('ALIYUN_OSS_ACCESS_KEY_ID'),
                'secret' => $getEnv('ALIYUN_OSS_ACCESS_KEY_SECRET'),
                'region' => $getEnv('ALIYUN_OSS_REGION', 'oss-cn-chengdu'),
                'bucket' => $getEnv('ALIYUN_OSS_BUCKET'),
                'endpoint' => $getEnv('ALIYUN_OSS_ENDPOINT', 'https://oss-cn-chengdu.aliyuncs.com'),
                'prefix' => $getEnv('ALIYUN_OSS_PREFIX', ''),
                'cdnDomain' => $getEnv('ALIYUN_OSS_CDN_DOMAIN'),
            ],
            
            // 腾讯云 COS（兼容 S3 协议）
            'qcloud_cos' => [
                'key' => $getEnv('QCLOUD_COS_ACCESS_KEY_ID'),
                'secret' => $getEnv('QCLOUD_COS_SECRET_ACCESS_KEY'),
                'region' => $getEnv('QCLOUD_COS_REGION', 'ap-chengdu'),
                'bucket' => $getEnv('QCLOUD_COS_BUCKET'),
                'endpoint' => $getEnv('QCLOUD_COS_ENDPOINT'),
                'prefix' => $getEnv('QCLOUD_COS_PREFIX', ''),
                'cdnDomain' => $getEnv('QCLOUD_COS_CDN_DOMAIN'),
            ],
        ];
    }
}
