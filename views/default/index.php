<?php

use yii\jui\DatePicker;
use jzkf\filemanager\models\File;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var jzkf\filemanager\models\search\FileSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var $totalSize    integer */

$this->title = \Yii::t('filemanager', 'Files');
$this->params['breadcrumbs'][] = $this->title;

// 添加上传按钮到页面工具栏
$this->params['headerToolbar'][] = Html::button('<i class="ti ti-upload me-2"></i>' . Yii::t('filemanager', 'Upload'), [
    'class' => 'btn btn-sm btn-primary',
    'id' => 'btn-upload-file',
    'data-bs-toggle' => 'modal',
    'data-bs-target' => '#filemanagerModal',
]);
?>
<?= $this->render('_modal') ?>
<div class="file-index">

    <div class="row">
        <div class="col-md-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white p-3 rounded"><i class="ti ti-files"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">文件总数</div>
                            <div class="text-secondary"><?php echo $dataProvider->totalCount ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white p-3 rounded">
                                <i class="ti ti-database"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">已使用空间</div>
                            <div class="text-secondary"><?php echo Yii::$app->formatter->asShortSize($totalSize, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'layout' => '{items}{summary}{pager}',
            'tableOptions' => ['class' => 'table align-middle mt-3'],
            'columns' => [
                'storage:raw',
                [
                    'attribute' => 'origin_name',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::a($model->origin_name, $model->base_url . $model->path, ['target' => '_blank']);
                    }
                ],
                [
                    'attribute' => 'path',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::tag('div', Html::img($model->base_url . $model->path, ['class' => 'img-preview']), ['class' => 'img-preview-wrap']);
                    }
                ],
                'mime_type',
                [
                    'attribute' => 'size',
                    'format' => 'raw',
                    'value' => function ($model) {
                        // $wh = '<br>W:' . $model->width . 'px H:' . $model->height . 'px';
                        return format_bytes($model->size);
                    }
                ],
                'extension',
                'upload_ip',
                [
                    'attribute' => 'created_at',
                    'format' => 'datetime',
                    'filter' => DatePicker::widget([
                        'model' => $searchModel,
                        'attribute' => 'created_at',
                        'options' => [
                            'class' => 'form-control',
                            'placeholder' => '选择日期',
                            'autocomplete' => 'off',
                        ],
                        'clientOptions' => [
                            'changeMonth' => true,
                            'changeYear' => true,
                            'yearRange' => '-10:+0',
                            'showButtonPanel' => true,
                            'closeText' => '确定',
                            'currentText' => '今天',
                            'dateFormat' => 'yy-mm-dd',
                            'monthNames' => ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
                            'dayNames' => ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'],
                            'dayNamesMin' => ['日', '一', '二', '三', '四', '五', '六'],
                        ],
                    ]),
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'template' => '<div class="d-flex shrink-0 items-center gap-3 justify-end">{view}{delete}</div>',
                    'contentOptions' => [],
                    'urlCreator' => function ($action, File $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    }
                ],
            ],
        ]); ?>
    </div>

</div>

<?php
$this->registerCss('
.img-preview-wrap { }
.img-preview { width: 10rem; overflow:hidden; aspect-ratio: 4/3; object-fit: cover; }
');

// 注册上传按钮的JavaScript
$uploadUrl = Url::to(['/filemanager/default/upload-form']);
$this->registerJs(
    <<<JS
    $('#btn-upload-file').on('click', function() {
        var modal = $('#filemanagerModal');
        modal.find('.modal-dialog').removeClass('modal-xl');
        modal.find('.modal-title').text('上传文件');
        modal.find('.modal-body').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
        modal.find('.modal-footer').addClass('d-none');
        
        $.get('$uploadUrl', function(data) {
            modal.find('.modal-body').html(data);
        });
    });
JS
);

?>