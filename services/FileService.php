<?php

namespace jzkf\filemanager\services;

use jzkf\filemanager\models\File;
use jzkf\filemanager\models\form\UploadForm;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * 文件服务类
 * 
 * 将文件相关业务逻辑从 Controller 和 Model 中抽离，
 * 提供统一的文件管理服务。
 */
class FileService
{
    /**
     * @var FlysystemService Flysystem 服务实例
     */
    protected $flysystem;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->flysystem = new FlysystemService();
    }
    /**
     * 上传文件
     * 
     * @param UploadedFile $uploadedFile 上传的文件
     * @param array $options 上传选项
     * @return array 文件信息
     * @throws \yii\base\Exception
     */
    public function upload($uploadedFile, $options = [])
    {
        if (!$uploadedFile) {
            throw new \yii\base\Exception('未选择文件');
        }
        
        // 验证文件类型
        $this->validateFileType($uploadedFile);
        
        // 检查文件是否已存在（去重）
        if (isset($options['enableDeduplication']) && $options['enableDeduplication']) {
            $md5 = md5_file($uploadedFile->tempName);
            $sha1 = sha1_file($uploadedFile->tempName);
            
            // 优先使用 SHA1 查找（更安全），如果没有则使用 MD5
            $existingFile = $this->findBySha1($sha1) ?: $this->findByMd5($md5);
            
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
                    'existing' => true,
                ];
            }
        }
        
        // 获取存储驱动
        $storage = $options['storage'] ?? null;
        
        // 上传新文件（使用 Flysystem）
        $model = new UploadForm([
            'flysystem' => $this->flysystem,
            'storage' => $storage,
        ]);
        $model->imageFile = $uploadedFile;
        
        $file = $model->upload();
        
        if (!$file) {
            throw new \yii\base\Exception('文件上传失败：' . implode(', ', $model->getFirstErrors()));
        }
        
        return $file;
    }
    
    /**
     * 删除文件（软删除）
     * 
     * @param int $fileId 文件ID
     * @param bool $force 是否强制删除（忽略引用检查）
     * @return bool
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     */
    public function delete($fileId, $force = false)
    {
        $file = File::find()->notDeleted()->andWhere(['id' => $fileId])->one();
        
        if (!$file) {
            throw new NotFoundHttpException('文件不存在');
        }
        
        // 检查引用
        if (!$force && $this->getUsageCount($file) > 0) {
            throw new \yii\base\Exception('文件正在使用中，无法删除');
        }
        
        // 执行软删除
        return $file->softDelete();
    }
    
    /**
     * 硬删除文件（物理删除）
     * 
     * @param int $fileId 文件ID
     * @return bool
     * @throws NotFoundHttpException
     */
    public function hardDelete($fileId)
    {
        $file = File::findOne($fileId);
        
        if (!$file) {
            throw new NotFoundHttpException('文件不存在');
        }
        
        // 删除物理文件
        $this->deletePhysicalFile($file);
        
        // 硬删除数据库记录
        return $file->delete() !== false;
    }
    
    /**
     * 批量删除文件
     * 
     * @param array $ids 文件ID数组
     * @return array ['success' => count, 'failed' => count, 'errors' => array]
     */
    public function batchDelete($ids)
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        foreach ($ids as $id) {
            try {
                $this->delete($id);
                $result['success']++;
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'id' => $id,
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 获取文件统计信息
     * 
     * @return array
     */
    public function getStatistics()
    {
        return [
            'total_count' => File::find()->notDeleted()->count(),
            'total_size' => File::find()->notDeleted()->sum('size') ?: 0,
            'by_type' => File::find()
                ->notDeleted()
                ->select(['extension', 'COUNT(*) as count', 'SUM(size) as total_size'])
                ->groupBy('extension')
                ->orderBy(['count' => SORT_DESC])
                ->asArray()
                ->all(),
            'by_month' => File::find()
                ->notDeleted()
                ->select([
                    'DATE_FORMAT(created_at, "%Y-%m") as month',
                    'COUNT(*) as count',
                    'SUM(size) as total_size'
                ])
                ->groupBy('month')
                ->orderBy('month DESC')
                ->limit(12)
                ->asArray()
                ->all(),
            'top_uploaders' => File::find()
                ->notDeleted()
                ->select(['created_by', 'COUNT(*) as count'])
                ->groupBy('created_by')
                ->orderBy(['count' => SORT_DESC])
                ->limit(10)
                ->asArray()
                ->all(),
        ];
    }
    
    /**
     * 获取文件引用次数
     * 
     * @param File $file
     * @return int
     */
    protected function getUsageCount($file)
    {
        $count = 0;
        
        // 检查在 CMS 文章中的引用
        if (class_exists('\modules\cms\models\Post')) {
            $count += \modules\cms\models\Post::find()
                ->where(['like', 'content', $file->path])
                ->orWhere(['thumbnail' => $file->path])
                ->count();
        }
        
        // 可以添加更多模块的检查
        // if (class_exists('\modules\product\models\Product')) {
        //     $count += \modules\product\models\Product::find()
        //         ->where(['image' => $file->path])
        //         ->count();
        // }
        
        return $count;
    }
    
    /**
     * 删除物理文件
     * 
     * @param File $file
     */
    protected function deletePhysicalFile($file)
    {
        // 使用 Flysystem 删除文件
        try {
            $this->flysystem->delete($file->path, $file->storage);
            
            // 删除缩略图
            $baseName = pathinfo($file->path, PATHINFO_FILENAME);
            $extension = $file->extension;
            $directory = dirname($file->path);
            
            $sizes = ['small', 'medium', 'large', 'thumbnail'];
            foreach ($sizes as $size) {
                $thumbPath = $directory . '/' . $baseName . '_' . $size . '.' . $extension;
                if ($this->flysystem->exists($thumbPath, $file->storage)) {
                    $this->flysystem->delete($thumbPath, $file->storage);
                }
                
                // 删除 WebP 版本
                $webpPath = $directory . '/' . $baseName . '_' . $size . '.webp';
                if ($this->flysystem->exists($webpPath, $file->storage)) {
                    $this->flysystem->delete($webpPath, $file->storage);
                }
            }
            
            // 删除原图 WebP 版本
            $webpOriginal = $directory . '/' . $baseName . '.webp';
            if ($this->flysystem->exists($webpOriginal, $file->storage)) {
                $this->flysystem->delete($webpOriginal, $file->storage);
            }
        } catch (\Exception $e) {
            Yii::error("删除物理文件失败: {$file->path}, 错误: {$e->getMessage()}", __METHOD__);
        }
    }
    
    /**
     * 根据 MD5 查找文件
     * 
     * @param string $md5
     * @return File|null
     */
    protected function findByMd5($md5)
    {
        return File::find()->notDeleted()->where(['md5' => $md5])->one();
    }
    
    /**
     * 根据 SHA1 查找文件
     * 
     * @param string $sha1
     * @return File|null
     */
    protected function findBySha1($sha1)
    {
        return File::find()->notDeleted()->where(['sha1' => $sha1])->one();
    }
    
    /**
     * 验证文件类型
     * 
     * @param UploadedFile $file
     * @throws \yii\base\Exception
     */
    protected function validateFileType($file)
    {
        // 获取配置的允许类型
        $allowedMimeTypes = Yii::$app->params['filemanager']['security']['allowedMimeTypes'] ?? [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar-compressed',
        ];
        
        // 使用 finfo 获取真实的 MIME 类型
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file->tempName);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                throw new \yii\base\Exception('不允许的文件类型：' . $mimeType);
            }
        }
        
        // 扩展名白名单
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
        if (!in_array(strtolower($file->extension), $allowedExtensions)) {
            throw new \yii\base\Exception('不允许的文件扩展名：' . $file->extension);
        }
    }
    
    /**
     * 清理未使用的文件
     * 
     * @param int $days 多少天前的文件
     * @return int 清理的文件数量
     */
    public function cleanUnusedFiles($days = 30)
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $files = File::find()
            ->notDeleted()
            ->where(['usage_count' => 0])
            ->andWhere(['<', 'created_at', $date])
            ->all();
        
        $count = 0;
        foreach ($files as $file) {
            if ($this->delete($file->id, true)) {
                $count++;
            }
        }
        
        return $count;
    }
}

