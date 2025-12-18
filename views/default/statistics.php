<?php

use yii\helpers\Html;
use common\models\User;

/** @var yii\web\View $this */
/** @var array $stats */

$this->title = '文件统计';
$this->params['breadcrumbs'][] = ['label' => \Yii::t('filemanager', 'Files'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="file-statistics">
    <!-- 概览卡片 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="ti ti-files"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">文件总数</div>
                            <div class="text-secondary h2 mb-0"><?= number_format($stats['total_count']) ?></div>
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
                            <span class="bg-success text-white avatar">
                                <i class="ti ti-database"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">总存储空间</div>
                            <div class="text-secondary h2 mb-0"><?= Yii::$app->formatter->asShortSize($stats['total_size'] ?? 0) ?></div>
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
                            <span class="bg-warning text-white avatar">
                                <i class="ti ti-photo"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">图片文件</div>
                            <div class="text-secondary h2 mb-0">
                                <?php
                                $imageCount = 0;
                                if (isset($stats['by_mime']) && is_array($stats['by_mime'])) {
                                    foreach ($stats['by_mime'] as $item) {
                                        if (isset($item['type']) && $item['type'] == 'image') {
                                            $imageCount = $item['count'];
                                            break;
                                        }
                                    }
                                }
                                echo number_format($imageCount);
                                ?>
                            </div>
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
                            <span class="bg-info text-white avatar">
                                <i class="ti ti-file-text"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">文档文件</div>
                            <div class="text-secondary h2 mb-0">
                                <?php
                                $docCount = 0;
                                if (isset($stats['by_mime']) && is_array($stats['by_mime'])) {
                                    foreach ($stats['by_mime'] as $item) {
                                        if (isset($item['type']) && $item['type'] == 'application') {
                                            $docCount = $item['count'];
                                            break;
                                        }
                                    }
                                }
                                echo number_format($docCount);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 文件类型分布 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">按文件类型统计</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>扩展名</th>
                                    <th class="text-end">数量</th>
                                    <th class="text-end">总大小</th>
                                    <th class="text-end">占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($stats['by_type']) && is_array($stats['by_type'])): ?>
                                    <?php foreach ($stats['by_type'] as $item): ?>
                                    <tr>
                                        <td>
                                            <span class="badge text-bg-secondary"><?= Html::encode($item['extension'] ?? '') ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format($item['count'] ?? 0) ?></td>
                                        <td class="text-end"><?= Yii::$app->formatter->asShortSize($item['total_size'] ?? 0) ?></td>
                                        <td class="text-end">
                                            <?php if (isset($stats['total_count']) && $stats['total_count'] > 0): ?>
                                                <div class="progress" style="width: 80px; height: 8px;">
                                                    <div class="progress-bar" style="width: <?= (($item['count'] ?? 0) / $stats['total_count'] * 100) ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">暂无数据</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MIME 类型分布 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">按MIME类型统计</h3>
                </div>
                <div class="card-body">
                    <div id="mime-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- 月度上传趋势 -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">月度上传趋势</h3>
                </div>
                <div class="card-body">
                    <div id="month-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <!-- Top 上传者 -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top 上传者</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>用户</th>
                                    <th class="text-end">文件数</th>
                                    <th class="text-end">大小</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($stats['top_uploaders']) && is_array($stats['top_uploaders'])): ?>
                                    <?php foreach ($stats['top_uploaders'] as $uploader): ?>
                                        <?php
                                        $user = isset($uploader['created_by']) ? User::findOne($uploader['created_by']) : null;
                                        ?>
                                        <tr>
                                            <td><?= $user ? Html::encode($user->username) : '未知' ?></td>
                                            <td class="text-end"><?= number_format($uploader['count'] ?? 0) ?></td>
                                            <td class="text-end"><?= Yii::$app->formatter->asShortSize($uploader['total_size'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">暂无数据</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 注册 ECharts 图表
$mimeData = json_encode($stats['by_mime'] ?? []);
$monthData = json_encode($stats['by_month'] ?? []);

$this->registerJs(<<<JS
// MIME 类型饼图
var mimeChart = echarts.init(document.getElementById('mime-chart'));
var mimeData = $mimeData;
var mimeOption = {
    tooltip: {
        trigger: 'item',
        formatter: '{b}: {c} ({d}%)'
    },
    series: [{
        type: 'pie',
        radius: '70%',
        data: mimeData.map(function(item) {
            return {
                value: item.count,
                name: item.type
            };
        }),
        emphasis: {
            itemStyle: {
                shadowBlur: 10,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.5)'
            }
        }
    }]
};
mimeChart.setOption(mimeOption);

// 月度趋势图
var monthChart = echarts.init(document.getElementById('month-chart'));
var monthData = $monthData;
var monthOption = {
    tooltip: {
        trigger: 'axis'
    },
    xAxis: {
        type: 'category',
        data: monthData.map(function(item) { return item.month || ''; })
    },
    yAxis: [
        {
            type: 'value',
            name: '数量'
        },
        {
            type: 'value',
            name: '大小(MB)',
            axisLabel: {
                formatter: function(value) {
                    return (value / 1024 / 1024).toFixed(2);
                }
            }
        }
    ],
    series: [
        {
            name: '文件数量',
            type: 'bar',
            data: monthData.map(function(item) { return item.count || 0; })
        },
        {
            name: '文件大小',
            type: 'line',
            yAxisIndex: 1,
            data: monthData.map(function(item) { return item.total_size || 0; })
        }
    ]
};
monthChart.setOption(monthOption);

// 响应式
window.addEventListener('resize', function() {
    mimeChart.resize();
    monthChart.resize();
});
JS
);
?>
