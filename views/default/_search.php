<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/** @var yii\web\View $this */
/** @var jzkf\filemanager\models\search\File $model */
/** @var yii\bootstrap5\ActiveForm $form */
?>

<div class="file-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'category_id') ?>

    <?= $form->field($model, 'unique_id') ?>

    <?= $form->field($model, 'storage') ?>

    <?php // echo $form->field($model, 'origin_name') ?>

    <?php // echo $form->field($model, 'base_url') ?>

    <?php // echo $form->field($model, 'path') ?>

    <?php // echo $form->field($model, 'url') ?>

    <?php // echo $form->field($model, 'mime_type') ?>

    <?php // echo $form->field($model, 'size') ?>

    <?php // echo $form->field($model, 'extension') ?>

    <?php // echo $form->field($model, 'width') ?>

    <?php // echo $form->field($model, 'height') ?>

    <?php // echo $form->field($model, 'upload_ip') ?>

    <?php // echo $form->field($model, 'created_at') ?>

    <?php // echo $form->field($model, 'created_by') ?>

    <div class="form-group">
        <?= Html::submitButton(\Yii::t('app', 'Search'), ['class' => 'btn btn-sm btn-primary']) ?>
        <?= Html::resetButton(\Yii::t('app', 'Reset'), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
