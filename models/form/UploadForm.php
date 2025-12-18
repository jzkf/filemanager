<?php

namespace jzkf\filemanager\models\form;

use jzkf\filemanager\models\File;
use jzkf\filemanager\services\FlysystemService;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

class UploadForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * @var FlysystemService Flysystem 服务实例
     */
    public $flysystem;

    /**
     * @var string 存储驱动
     */
    public $storage;

    /**
     * 安全处理文件名
     * 移除路径遍历字符和特殊字符
     * 
     * @param string $filename 原始文件名
     * @return string 安全的文件名
     */
    protected function sanitizeFilename($filename)
    {
        // 获取配置的黑名单字符
        $blacklist = \Yii::$app->params['filemanager']['security']['fileNameBlacklist'] ?? ['..', '/', '\\', "\0", '<', '>', ':', '"', '|', '?', '*'];

        // 移除黑名单字符
        $filename = str_replace($blacklist, '', $filename);

        // 移除多个空格
        $filename = preg_replace('/\s+/', ' ', $filename);

        // 移除首尾空格
        $filename = trim($filename);

        // 限制文件名长度（防止过长）
        if (mb_strlen($filename) > 200) {
            $info = pathinfo($filename);
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
            $name = mb_substr($info['filename'], 0, 200 - mb_strlen($ext));
            $filename = $name . $ext;
        }

        return $filename;
    }

    public function rules()
    {
        // 从配置文件获取设置
        $config = \Yii::$app->params['filemanager']['upload'] ?? [];
        $maxSize = $config['maxSize'] ?? 10 * 1024 * 1024; // 默认 10MB
        $allowedExtensions = $config['allowedExtensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];

        return [
            [
                ['imageFile'],
                'file',
                'skipOnEmpty' => false,
                'extensions' => $allowedExtensions,
                'maxSize' => $maxSize,
                'checkExtensionByMimeType' => true, // 严格验证：通过 MIME 类型检查扩展名
                'wrongExtension' => '不允许的文件扩展名',
                'tooBig' => '文件大小不能超过 ' . \Yii::$app->formatter->asShortSize($maxSize),
            ],
        ];
    }

    /**
     * 初始化
     */
    public function init()
    {
        parent::init();

        // 初始化 Flysystem 服务
        if ($this->flysystem === null) {
            $this->flysystem = new FlysystemService();
        }

        // 获取默认存储驱动
        if ($this->storage === null) {
            $this->storage = \Yii::$app->params['filemanager']['storage']['default'] ?? 'local';
        }
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        // 验证
        if (!$this->validate()) {
            return false;
        }

        // 从配置获取图片处理参数
        $imageConfig = \Yii::$app->params['filemanager']['upload']['image'] ?? [];
        $maxDimensions = $imageConfig['maxDimensions'] ?? [9999, 9999];
        $thumbnails = $imageConfig['thumbnails'] ?? [
            'large' => [720, 540],
        ];

        $max_width = $maxDimensions[0];
        $max_height = $maxDimensions[1];

        // 使用配置的缩略图尺寸
        $thumbnail_width = $thumbnails['large'][0] ?? 720;
        $thumbnail_height = $thumbnails['large'][1] ?? 540;

        $extension = $this->imageFile->extension;
        
        $uuid = $this->generateUuid();
        
        $path = '/uploads/' . date('Y/md/');

        // 创建临时目录用于处理图片
        $temp_path = Yii::getAlias('@runtime/uploads/' . date('Y/md/'));
        if (!is_dir($temp_path)) {
            try {
                FileHelper::createDirectory($temp_path);
            } catch (Exception $e) {
                Yii::error($e->getMessage());
            }
        }

        $file_name = $uuid . '.' . $extension;

        // 相对路径（用于存储）
        $relative_name = ltrim($path . $file_name, '/');

        // 临时文件路径
        $temp_file = $temp_path . $file_name;

        try {
            // 安全验证：检查真实的 MIME 类型
            $securityConfig = \Yii::$app->params['filemanager']['security'] ?? [];
            if ($securityConfig['checkMimeType'] ?? true) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $this->imageFile->tempName);
                finfo_close($finfo);

                $allowedMimeTypes = $securityConfig['allowedMimeTypes'] ?? [];
                if (!empty($allowedMimeTypes) && !in_array($mimeType, $allowedMimeTypes)) {
                    $this->addError('imageFile', '不允许的文件类型：' . $mimeType);
                    return false;
                }
            }

            // 计算文件 MD5 和 SHA1（用于去重）
            $md5 = md5_file($this->imageFile->tempName);
            $sha1 = sha1_file($this->imageFile->tempName);

            // 检查文件是否已存在（去重功能）
            $enableDeduplication = \Yii::$app->params['filemanager']['features']['enableDeduplication'] ?? false;
            if ($enableDeduplication) {
                // 优先使用 SHA1 查找（更安全），如果没有则使用 MD5
                $existingFile = File::find()
                    ->notDeleted()
                    ->andWhere(['or', ['sha1' => $sha1], ['md5' => $md5]])
                    ->one();
                if ($existingFile) {
                    // 文件已存在，增加引用计数
                    $existingFile->updateCounters(['usage_count' => 1]);

                    return [
                        'file_url' => $existingFile->url,
                        'file_path' => $existingFile->path,
                        'file_name' => $existingFile->origin_name,
                        'size' => $existingFile->size,
                        'type' => $existingFile->mime_type,
                        'width' => $existingFile->width,
                        'height' => $existingFile->height,
                        'existing' => true, // 标记为已存在的文件
                    ];
                }
            }

            // 如果是图片，则获取图片宽高
            $width = $height = 0;
            $is_image = false;
            if (in_array($this->imageFile->extension, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'])) {
                list($width, $height) = getimagesize($this->imageFile->tempName);
                $is_image = true;
            }

            // 先保存到临时文件
            $this->imageFile->saveAs($temp_file);

            if ($is_image) {
                // 调整图片最大宽度，同时保持纵横比例
                if ($width > $max_width) {
                    Image::resize($temp_file, $max_width, $max_height)->save();
                }

                // 生成多尺寸缩略图
                $enableThumbnail = \Yii::$app->params['filemanager']['features']['enableThumbnail'] ?? true;
                if ($enableThumbnail && !empty($thumbnails)) {
                    foreach ($thumbnails as $size => $dimensions) {
                        $thumbName = $temp_path . $uuid . '_' . $size . '.' . $extension;
                        Image::thumbnail($temp_file, $dimensions[0], $dimensions[1])
                            ->save($thumbName, ['quality' => $imageConfig['quality'] ?? 85]);

                        // 上传缩略图到存储
                        $thumbRelativeName = ltrim($path . $uuid . '_' . $size . '.' . $extension, '/');
                        $this->flysystem->upload($thumbName, $thumbRelativeName, $this->storage);

                        // 删除临时缩略图
                        @unlink($thumbName);
                    }
                }

                // 向后兼容：生成 _thumbnail 版本（large 尺寸）
                $thumbnail_temp_name = $temp_path . $uuid . '_thumbnail.' . $extension;
                Image::thumbnail($temp_file, $thumbnail_width, $thumbnail_height)->save($thumbnail_temp_name);

                // 上传缩略图到存储
                $thumbRelativeName = ltrim($path . $uuid . '_thumbnail.' . $extension, '/');
                $this->flysystem->upload($thumbnail_temp_name, $thumbRelativeName, $this->storage);
                @unlink($thumbnail_temp_name);

                // 生成 WebP 格式（如果启用且支持）
                $enableWebp = \Yii::$app->params['filemanager']['features']['enableWebp'] ?? false;
                if ($enableWebp && function_exists('imagewebp')) {
                    $webpQuality = $imageConfig['webpQuality'] ?? 80;

                    // 原图 WebP
                    $webpName = $temp_path . $uuid . '.webp';
                    $this->convertToWebp($temp_file, $webpName, $webpQuality);
                    $webpRelativeName = ltrim($path . $uuid . '.webp', '/');
                    $this->flysystem->upload($webpName, $webpRelativeName, $this->storage);
                    @unlink($webpName);

                    // 缩略图 WebP
                    if ($enableThumbnail && !empty($thumbnails)) {
                        foreach ($thumbnails as $size => $dimensions) {
                            $thumbName = $temp_path . $uuid . '_' . $size . '.' . $extension;
                            $webpThumbName = $temp_path . $uuid . '_' . $size . '.webp';

                            // 重新生成临时缩略图用于转换
                            Image::thumbnail($temp_file, $dimensions[0], $dimensions[1])
                                ->save($thumbName, ['quality' => $imageConfig['quality'] ?? 85]);

                            if (file_exists($thumbName)) {
                                $this->convertToWebp($thumbName, $webpThumbName, $webpQuality);
                                $webpThumbRelativeName = ltrim($path . $uuid . '_' . $size . '.webp', '/');
                                $this->flysystem->upload($webpThumbName, $webpThumbRelativeName, $this->storage);
                                @unlink($thumbName);
                                @unlink($webpThumbName);
                            }
                        }
                    }
                }
            }

            // 上传原图到存储
            $uploadResult = $this->flysystem->upload($temp_file, $relative_name, $this->storage);

            // 删除临时文件
            @unlink($temp_file);

            $ip = get_client_ip();

            // 安全处理文件名
            $safeOriginName = $this->sanitizeFilename($this->imageFile->name);

            // 获取完整 URL
            $full_url = $this->flysystem->url($relative_name, $this->storage);

            // 解析 base_url
            $base_url = '';
            $urlParts = parse_url($full_url);
            if (isset($urlParts['scheme']) && isset($urlParts['host'])) {
                $base_url = $urlParts['scheme'] . '://' . $urlParts['host'];
                if (isset($urlParts['port'])) {
                    $base_url .= ':' . $urlParts['port'];
                }
                $base_url .= '/';
            }

            $fm = new File();
            $fm->unique_id = $uuid;
            $fm->origin_name = $safeOriginName;
            $fm->object_name = $relative_name; // 存储对象名（含随机路径）
            $fm->mime_type = $uploadResult['mime_type'] ?? $this->imageFile->type;
            $fm->size = $uploadResult['size'] ?? $this->imageFile->size;
            $fm->storage = $this->storage;
            $fm->base_url = $base_url;
            $fm->path = $relative_name;
            $fm->url = $full_url;
            $fm->extension = $this->imageFile->getExtension();
            $fm->width = $width;
            $fm->height = $height;
            $fm->upload_ip = $ip[0] ?? '';
            $fm->md5 = $md5; // 保存 MD5 用于去重
            $fm->sha1 = $sha1; // 保存 SHA1 用于去重（更安全）
            $fm->usage_count = 1; // 初始引用计数为 1
            $fm->status = File::STATUS_ACTIVE; // 状态：正常
            $fm->privacy = File::PRIVACY_PUBLIC; // 默认公开
            $fm->save(false);

            return [
                'file_url' => $fm->url,
                'file_path' => $fm->path,
                'width' => $fm->width,
                'height' => $fm->height,
                'size' => $fm->size,
                'type' => $fm->mime_type,
                'file_name' => $fm->origin_name,
            ];
        } catch (Exception $exception) {
            Yii::error($exception->getMessage());

            // 清理临时文件
            if (isset($temp_file) && file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }

        return false;
    }

    /**
     * 转换图片为 WebP 格式
     * 
     * @param string $source 源文件路径
     * @param string $destination 目标文件路径
     * @param int $quality 质量（0-100）
     * @return bool
     */
    protected function convertToWebp($source, $destination, $quality = 80)
    {
        if (!file_exists($source)) {
            return false;
        }

        try {
            // 根据图片类型创建图像资源
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = @imagecreatefromjpeg($source);
                    break;
                case 'png':
                    $image = @imagecreatefrompng($source);
                    break;
                case 'gif':
                    $image = @imagecreatefromgif($source);
                    break;
                case 'bmp':
                    $image = @imagecreatefrombmp($source);
                    break;
                default:
                    return false;
            }

            if ($image === false) {
                return false;
            }

            // 转换为 WebP
            $result = imagewebp($image, $destination, $quality);

            // 释放内存
            imagedestroy($image);

            return $result;
        } catch (\Exception $e) {
            Yii::error('WebP 转换失败: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * 生成 UUID
     * @return string
     */
    protected function generateUuid()
    {
        if (function_exists('uuid')) {
            return uuid();
        }

        // 如果存在 Ramsey\Uuid\Uuid 类，则使用它生成 UUID
        if (class_exists('\Ramsey\Uuid\Uuid')) {
            return \Ramsey\Uuid\Uuid::uuid4()->toString();
        }

        // 如果存在 Ulid\Ulid 类，则使用它生成 UUID
        if (class_exists('\Ulid\Ulid')) {
            return \Ulid\Ulid::generate(true);
        }

        return uniqid();
    }
}
