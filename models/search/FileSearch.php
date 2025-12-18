<?php

namespace jzkf\filemanager\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use jzkf\filemanager\models\File as FileModel;

/**
 * File represents the model behind the search form of `jzkf\filemanager\models\File`.
 */
class FileSearch extends FileModel
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'category_id', 'size', 'width', 'height', 'bitrate', 'privacy', 'status', 'sort_order', 'view_count', 'download_count', 'usage_count', 'created_by', 'updated_by'], 'integer'],
            [['duration'], 'number'],
            [['unique_id', 'storage', 'origin_name', 'object_name', 'base_url', 'path', 'url', 'mime_type', 'extension', 'cover_url', 'alt', 'title', 'tags', 'md5', 'sha1', 'upload_ip'], 'safe'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param bool $useCache 是否使用缓存
     *
     * @return ActiveDataProvider
     */
    public function search($params, $useCache = true)
    {
        // 生成缓存键
        $cacheKey = 'filemanager:search:' . md5(serialize($params) . \Yii::$app->user->id);
        
        // 如果启用缓存，尝试从缓存获取
        if ($useCache && \Yii::$app->cache) {
            $cachedData = \Yii::$app->cache->get($cacheKey);
            if ($cachedData !== false) {
                return $cachedData;
            }
        }
        
        $query = FileModel::find()->notDeleted(); // 默认只查询未删除的记录

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC,
                ]
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'category_id' => $this->category_id,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'bitrate' => $this->bitrate,
            'privacy' => $this->privacy,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
            'usage_count' => $this->usage_count,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ]);
        
        // 日期范围过滤
        if (!empty($this->created_at)) {
            if (is_array($this->created_at)) {
                if (isset($this->created_at[0]) && isset($this->created_at[1])) {
                    $query->andFilterWhere(['between', 'created_at', $this->created_at[0], $this->created_at[1]]);
                }
            } else {
                $query->andFilterWhere(['like', 'created_at', $this->created_at]);
            }
        }

        $query->andFilterWhere(['like', 'unique_id', trim($this->unique_id)])
            ->andFilterWhere(['like', 'storage', trim($this->storage)])
            ->andFilterWhere(['like', 'origin_name', trim($this->origin_name)])
            ->andFilterWhere(['like', 'object_name', trim($this->object_name)])
            ->andFilterWhere(['like', 'base_url', trim($this->base_url)])
            ->andFilterWhere(['like', 'path', trim($this->path)])
            ->andFilterWhere(['like', 'url', trim($this->url)])
            ->andFilterWhere(['like', 'mime_type', trim($this->mime_type)])
            ->andFilterWhere(['like', 'extension', trim($this->extension)])
            ->andFilterWhere(['like', 'cover_url', trim($this->cover_url)])
            ->andFilterWhere(['like', 'alt', trim($this->alt)])
            ->andFilterWhere(['like', 'title', trim($this->title)])
            ->andFilterWhere(['like', 'tags', trim($this->tags)])
            ->andFilterWhere(['like', 'md5', trim($this->md5)])
            ->andFilterWhere(['like', 'sha1', trim($this->sha1)])
            ->andFilterWhere(['like', 'upload_ip', trim($this->upload_ip)]);

        // 缓存结果（5分钟）
        if ($useCache && \Yii::$app->cache) {
            \Yii::$app->cache->set($cacheKey, $dataProvider, 300);
        }

        return $dataProvider;
    }
    
    /**
     * 清除搜索缓存
     */
    public static function clearCache()
    {
        if (\Yii::$app->cache) {
            \Yii::$app->cache->flush();
        }
    }
}
