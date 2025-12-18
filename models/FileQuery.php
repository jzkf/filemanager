<?php

namespace jzkf\filemanager\models;

/**
 * This is the ActiveQuery class for [[File]].
 *
 * @see File
 */
class FileQuery extends \yii\db\ActiveQuery
{
    /**
     * 只查询未删除的记录
     * @return $this
     */
    public function notDeleted()
    {
        return $this->andWhere(['deleted_at' => null]);
    }
    
    /**
     * 只查询已删除的记录
     * @return $this
     */
    public function deleted()
    {
        return $this->andWhere(['not', ['deleted_at' => null]]);
    }
    
    /**
     * 查询正常状态的记录
     * @return $this
     */
    public function active()
    {
        return $this->andWhere(['status' => File::STATUS_ACTIVE]);
    }
    
    /**
     * 查询公开的记录
     * @return $this
     */
    public function public()
    {
        return $this->andWhere(['privacy' => File::PRIVACY_PUBLIC]);
    }
    
    /**
     * 按分类查询
     * @param int|null $categoryId
     * @return $this
     */
    public function byCategory($categoryId)
    {
        if ($categoryId === null) {
            return $this->andWhere(['category_id' => null]);
        }
        return $this->andWhere(['category_id' => $categoryId]);
    }
    
    /**
     * 按存储类型查询
     * @param string $storage
     * @return $this
     */
    public function byStorage($storage)
    {
        return $this->andWhere(['storage' => $storage]);
    }
    
    /**
     * 按文件类型查询
     * @param string $mimeType MIME类型，支持通配符如 'image/*'
     * @return $this
     */
    public function byMimeType($mimeType)
    {
        if (strpos($mimeType, '*') !== false) {
            $prefix = rtrim($mimeType, '*');
            return $this->andWhere(['like', 'mime_type', $prefix . '%', false]);
        }
        return $this->andWhere(['mime_type' => $mimeType]);
    }
    
    /**
     * 按扩展名查询
     * @param string|array $extension
     * @return $this
     */
    public function byExtension($extension)
    {
        if (is_array($extension)) {
            return $this->andWhere(['in', 'extension', $extension]);
        }
        return $this->andWhere(['extension' => $extension]);
    }

    /**
     * {@inheritdoc}
     * @return File[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return File|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
