<?php

/**
 * FileManager 模块配置文件
 */
return [
    // 上传配置
    'upload' => [
        // 最大文件大小（字节）
        'maxSize' => 10 * 1024 * 1024, // 10MB
        
        // 允许的文件扩展名
        'allowedExtensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',  // 图片
            'mp4', 'avi', 'mov', 'wmv', 'flv',                   // 视频
            'mp3', 'wav', 'ogg', 'flac',                         // 音频
            'pdf',                                                // PDF
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',        // Office
            'zip', 'rar', '7z', 'tar', 'gz',                    // 压缩包
            'txt', 'md', 'csv', 'json', 'xml',                  // 文本
        ],
        
        // 图片处理配置
        'image' => [
            // 原图最大尺寸
            'maxDimensions' => [1920, 1920],
            
            // 缩略图尺寸
            'thumbnails' => [
                'small' => [150, 150],   // 列表缩略图
                'medium' => [400, 400],  // 预览
                'large' => [720, 540],   // 详情页
            ],
            
            // 图片质量
            'quality' => 85,
            
            // 是否生成 WebP 格式
            'enableWebp' => true,
            'webpQuality' => 80,
        ],
    ],
    
    // 存储配置
    'storage' => [
        // 默认存储驱动: local, s3, aliyun_oss, qcloud_cos
        'default' => env('FILE_STORAGE_DRIVER', 'local'),
        
        // 存储驱动配置
        'drivers' => [
            // 本地存储
            'local' => [
                'basePath' => '@web',
                'baseUrl' => '',
            ],
            
            // AWS S3
            's3' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'bucket' => env('AWS_BUCKET'),
                'prefix' => env('AWS_PREFIX', ''),
                'cdnDomain' => env('AWS_CDN_DOMAIN'),
            ],
            
            // 阿里云 OSS（兼容 S3 协议）
            'aliyun_oss' => [
                'key' => env('ALIYUN_OSS_ACCESS_KEY_ID'),
                'secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET'),
                'region' => env('ALIYUN_OSS_REGION', 'oss-cn-chengdu'),
                'bucket' => env('ALIYUN_OSS_BUCKET'),
                'endpoint' => env('ALIYUN_OSS_ENDPOINT', 'https://oss-cn-chengdu.aliyuncs.com'),
                'prefix' => env('ALIYUN_OSS_PREFIX', ''),
                'cdnDomain' => env('ALIYUN_OSS_CDN_DOMAIN'),
            ],
            
            // 腾讯云 COS（兼容 S3 协议）
            'qcloud_cos' => [
                'key' => env('QCLOUD_COS_ACCESS_KEY_ID'),
                'secret' => env('QCLOUD_COS_SECRET_ACCESS_KEY'),
                'region' => env('QCLOUD_COS_REGION', 'ap-chengdu'),
                'bucket' => env('QCLOUD_COS_BUCKET'),
                'endpoint' => env('QCLOUD_COS_ENDPOINT'),
                'prefix' => env('QCLOUD_COS_PREFIX', ''),
                'cdnDomain' => env('QCLOUD_COS_CDN_DOMAIN'),
            ],
        ],
    ],
    
    // 安全配置
    'security' => [
        // 是否检查 MIME 类型
        'checkMimeType' => true,
        
        // 允许的 MIME 类型
        'allowedMimeTypes' => [
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
        ],
        
        // 文件名黑名单（不允许的字符）
        'fileNameBlacklist' => ['..', '/', '\\', "\0", '<', '>', ':', '"', '|', '?', '*'],
    ],
    
    // 功能开关
    'features' => [
        'enableDeduplication' => true,      // 文件去重
        'enableCompression' => true,        // 图片压缩
        'enableThumbnail' => true,          // 生成缩略图
        'enableWebp' => true,               // 生成 WebP
        'enableWatermark' => false,         // 水印（未实现）
        'enableVersioning' => false,        // 版本管理（未实现）
    ],
    
    // 分页配置
    'pagination' => [
        'pageSize' => 20,           // 列表页每页数量
        'pickerPageSize' => 12,     // 选择器每页数量
    ],
    
    // 清理配置
    'cleanup' => [
        'enableAutoCleanup' => true,        // 自动清理
        'unusedFileDays' => 30,             // 未使用文件保留天数
        'deletedFileDays' => 7,             // 软删除文件保留天数
    ],
];

