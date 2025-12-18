<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var jzkf\filemanager\models\File $model */

$this->title = $model->origin_name;
$this->params['breadcrumbs'][] = ['label' => \Yii::t('filemanager', 'Files'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="file-view card">

    <div class="card-header">
        <h5 class="card-title"><i class="fas fa-eye"></i> <?= Html::encode($this->title) ?></h5>
        <div class="card-tools">
            <?= Html::a(\Yii::t('filemanager', 'Delete'), ['delete', 'id' => $model->id], [
                'class' => 'btn btn-sm btn-danger',
                'data' => [
                    'confirm' => \Yii::t('filemanager', 'Are you sure you want to delete this item?'),
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <div class="card-body">

        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'id',
                'category_id',
                'unique_id',
                'storage',
                'origin_name',
                'base_url:url',
                'path',
                [
                    'attribute' => 'url',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::img($model->url, ['class' => 'img-fluid']);
                    }
                ],
                'mime_type',
                [
                    'attribute' => 'size',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return format_bytes($model->size);
                    }
                ],
                'extension',
                'width',
                'height',
                'upload_ip',
                'created_at:datetime',
                'created_by',
            ],
        ]) ?>

    </div>
</div>
