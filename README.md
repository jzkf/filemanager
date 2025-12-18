# FileManager 文件管理模块

集中管理系统中所有上传的文件，提供统一的文件上传、存储、检索和管理功能。

## 🌟 核心功能

### ✅ 已实现功能

- **文件上传**
  - 支持图片、文档、视频、音频等多种格式
  - 图片自动压缩和缩略图生成
  - 拖拽上传支持
  - 实时上传进度显示
  - 多文件批量上传

- **文件选择器**
  - 统一的 Modal 界面
  - 选择已有文件或上传新文件
  - AJAX 翻页和搜索
  - 文件类型过滤
  - 网格化展示

- **FileUploadWidget**
  - 可复用的上传组件
  - 支持 ActiveForm 集成
  - 两种显示模式（按钮/内联）
  - 事件驱动更新
  - 文件预览功能

- **文件管理**
  - 文件列表查看
  - 文件详情查看
  - 文件删除功能
  - 按类型/时间筛选
  - 存储空间统计

### 🚧 规划中功能

- 文件分类管理
- ~~文件去重~~（已实现）
- 批量操作
- ~~云存储支持~~（已实现）
- 图片编辑
- 文件版本管理

## 📁 目录结构

安装后，包位于 `vendor/jzkf/filemanager/`：

```
vendor/jzkf/filemanager/
├── config/
│   └── filemanager.php          # 模块配置文件
├── controllers/
│   └── DefaultController.php    # 控制器
├── docs/
│   ├── PRD.md                   # 产品需求文档
│   ├── FileUploadWidget使用说明.md
│   ├── 文件选择器说明.md
│   ├── 模块优化建议.md
│   └── 优化实施指南.md
├── messages/
│   └── zh-CN/
│       └── filemanager.php      # 中文翻译
├── migrations/
│   ├── M251205030145CreateMediaCategoriesTable.php
│   └── M251205032620CreateMediaFilesTable.php
├── models/
│   ├── File.php                 # 文件模型
│   ├── FileQuery.php            # 查询类
│   ├── form/
│   │   └── UploadForm.php       # 上传表单
│   └── search/
│       └── FileSearch.php       # 搜索模型
├── services/
│   ├── FileService.php          # 业务服务层
│   └── FlysystemService.php     # Flysystem 服务
├── views/
│   └── default/
│       ├── index.php            # 文件列表
│       ├── view.php             # 文件详情
│       ├── upload.php           # 上传表单
│       └── file-picker.php      # 文件选择器
├── widgets/
│   └── FileUploadWidget.php    # 文件上传组件
├── FileManagerModule.php        # 模块定义
└── README.md                    # 本文件
```

## 🚀 快速开始

### 1. 安装

通过 Composer 安装：

```bash
composer require jzkf/filemanager
```

### 2. 配置模块

在应用配置文件中注册模块（例如 `backend/config/main.php` 或 `common/config/main.php`）：

```php
'modules' => [
    'filemanager' => [
        'class' => 'jzkf\filemanager\FileManagerModule',
        // 可选：自定义存储配置
        'storage' => [
            'default' => 'local',
            'drivers' => [
                'local' => [
                    'basePath' => '@web/uploads',
                    'baseUrl' => '',
                ],
            ],
        ],
    ],
],
```

### 3. 配置文件

复制配置文件到项目配置目录（可选，如需自定义配置）：

```bash
# 复制默认配置文件
cp vendor/jzkf/filemanager/config/filemanager.php common/config/filemanager.php
```

然后在配置文件中自定义设置：

```php
// common/config/filemanager.php
return [
    'upload' => [
        'maxSize' => 10 * 1024 * 1024,  // 10MB
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', ...],
    ],
    'storage' => [
        'default' => env('FILE_STORAGE_DRIVER', 'local'),
        'drivers' => [
            // 存储驱动配置...
        ],
    ],
];
```

### 4. 运行迁移

```bash
# 创建数据表和索引
./yii migrate --migrationPath=@vendor/jzkf/filemanager/migrations
```

或者使用别名（需要在配置中注册别名）：

```php
// 在配置文件中注册别名
Yii::setAlias('@jzkf/filemanager', '@vendor/jzkf/filemanager');
```

然后使用：

```bash
./yii migrate --migrationPath=@jzkf/filemanager/migrations
```

### 5. 配置路由

在应用配置中配置路由（例如 `backend/config/main.php`）：

```php
'components' => [
    'urlManager' => [
        'rules' => [
            // 文件管理路由
            'filemanager/<controller>/<action>' => 'filemanager/<controller>/<action>',
        ],
    ],
],
```

### 6. 配置权限

在 Casbin 或 RBAC 中配置文件管理权限，或配置公共路由：

```php
'publicRoutes' => [
    '/filemanager/default/upload-form',
    '/filemanager/default/upload-file',
    '/filemanager/default/file-picker',
],
```

## 💡 使用示例

### 在表单中使用文件上传

```php
<?php
use yii\widgets\ActiveForm;
use jzkf\filemanager\widgets\FileUploadWidget;

$form = ActiveForm::begin();
?>

<!-- 上传图片 -->
<?= $form->field($model, 'cover_image')->widget(FileUploadWidget::class, [
    'btnLabel' => '选择封面图',
    'accept' => 'image/*',
]) ?>

<!-- 上传文档 -->
<?= $form->field($model, 'document')->widget(FileUploadWidget::class, [
    'btnLabel' => '选择文档',
    'accept' => '.pdf,.doc,.docx',
]) ?>

<?php ActiveForm::end(); ?>
```

### 使用 Service 层

```php
use jzkf\filemanager\services\FileService;
use yii\web\UploadedFile;

$service = new FileService();

// 上传文件
$uploadedFile = UploadedFile::getInstance($model, 'file');
$result = $service->upload($uploadedFile);

// 删除文件
$service->delete($fileId);

// 获取统计
$stats = $service->getStatistics();
```

## 🔧 配置选项

### 上传配置

在项目配置文件中（如 `common/config/filemanager.php`）配置：

```php
return [
    'upload' => [
        'maxSize' => 10 * 1024 * 1024,  // 10MB
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'],
        'image' => [
            'maxDimensions' => [1920, 1920],
            'thumbnails' => [
                'large' => [720, 540],
                'medium' => [480, 360],
                'small' => [240, 180],
            ],
            'quality' => 85,
            'webpQuality' => 80,
        ],
    ],
    'security' => [
        'checkMimeType' => true,
        'allowedMimeTypes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'fileNameBlacklist' => ['..', '/', '\\', "\0", '<', '>', ':', '"', '|', '?', '*'],
    ],
    'features' => [
        'enableDeduplication' => true,  // 启用文件去重
        'enableThumbnail' => true,     // 启用缩略图生成
        'enableWebp' => false,         // 启用 WebP 格式转换
    ],
];
```

### 存储配置

支持本地存储、AWS S3、阿里云 OSS、腾讯云 COS 等多种存储驱动。

```php
'storage' => [
    'default' => env('FILE_STORAGE_DRIVER', 'local'),
    'drivers' => [
        'local' => [
            'basePath' => '@web/uploads',
            'baseUrl' => '',  // 留空则使用 frontend_url()
        ],
        's3' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'cdnDomain' => env('AWS_CDN_DOMAIN'),
        ],
        'aliyun_oss' => [
            'key' => env('ALIYUN_OSS_ACCESS_KEY_ID'),
            'secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET'),
            'region' => env('ALIYUN_OSS_REGION', 'oss-cn-chengdu'),
            'bucket' => env('ALIYUN_OSS_BUCKET'),
            'endpoint' => env('ALIYUN_OSS_ENDPOINT'),
            'cdnDomain' => env('ALIYUN_OSS_CDN_DOMAIN'),
        ],
        'qcloud_cos' => [
            'key' => env('QCLOUD_COS_ACCESS_KEY_ID'),
            'secret' => env('QCLOUD_COS_SECRET_ACCESS_KEY'),
            'region' => env('QCLOUD_COS_REGION', 'ap-chengdu'),
            'bucket' => env('QCLOUD_COS_BUCKET'),
            'endpoint' => env('QCLOUD_COS_ENDPOINT'),
            'cdnDomain' => env('QCLOUD_COS_CDN_DOMAIN'),
        ],
    ],
],
```

详细配置请参考 [Flysystem 集成文档](./docs/FlysystemIntegration.md)。

## 📊 数据库表结构

### media_files 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT | 主键 |
| category_id | INT | 分类ID |
| unique_id | VARCHAR(255) | UUID |
| storage | VARCHAR(255) | 存储类型 |
| origin_name | VARCHAR(255) | 原始文件名 |
| base_url | VARCHAR(1024) | 基础URL |
| path | VARCHAR(1024) | 保存路径 |
| url | TEXT | 访问URL |
| mime_type | VARCHAR(255) | MIME类型 |
| size | INT UNSIGNED | 文件大小 |
| extension | VARCHAR(255) | 扩展名 |
| width | INT UNSIGNED | 图片宽度 |
| height | INT UNSIGNED | 图片高度 |
| upload_ip | VARCHAR(45) | 上传IP |
| md5 | VARCHAR(32) | MD5校验 |
| usage_count | INT UNSIGNED | 引用次数 |
| status | TINYINT | 状态 |
| created_at | INT UNSIGNED | 创建时间 |
| created_by | INT UNSIGNED | 创建者 |
| updated_at | INT UNSIGNED | 更新时间 |
| deleted_at | INT UNSIGNED | 删除时间 |

## 🎯 路由说明

模块注册为 `'filemanager'`，路由如下：

- 文件列表：`/filemanager/default/index`
- 文件详情：`/filemanager/default/view?id=1`
- 文件删除：`/filemanager/default/delete?id=1`
- 上传表单：`/filemanager/default/upload-form`
- 上传处理：`/filemanager/default/upload-file`
- 文件选择器：`/filemanager/default/file-picker`
- 文件统计：`/filemanager/default/statistics`

## 🔐 权限配置

在应用配置中配置公共路由（如 `backend/config/main.php`）：

```php
'publicRoutes' => [
    '/filemanager/default/upload-form',
    '/filemanager/default/upload-file',
    '/filemanager/default/file-picker',
],
```

或者使用 RBAC 配置权限：

```php
// 在权限管理中添加
$auth = Yii::$app->authManager;
$fileManager = $auth->createPermission('filemanager');
$fileManager->description = '文件管理权限';
$auth->add($fileManager);
```

## 📖 相关文档

| 文档 | 说明 |
|------|------|
| [PRD.md](./docs/PRD.md) | 产品需求文档 |
| [FileUploadWidget使用说明.md](./docs/FileUploadWidget使用说明.md) | Widget 详细使用文档 |
| [文件选择器说明.md](./docs/文件选择器说明.md) | 文件选择器功能说明 |
| [FlysystemIntegration.md](./docs/FlysystemIntegration.md) | Flysystem 集成使用文档 |
| [模块优化建议.md](./docs/模块优化建议.md) | 全面的优化分析 |
| [优化实施指南.md](./docs/优化实施指南.md) | 分步骤实施指南 |

## 🐛 故障排除

### 安装问题

**问题：找不到类 `jzkf\filemanager\FileManagerModule`**

```bash
# 解决方案：重新生成自动加载文件
composer dump-autoload
```

**问题：迁移路径找不到**

```php
// 在配置文件中注册别名
Yii::setAlias('@jzkf/filemanager', '@vendor/jzkf/filemanager');
```

### 上传失败

1. **检查目录权限**
   ```bash
   chmod -R 755 storage/uploads
   chown -R www-data:www-data storage/uploads
   ```

2. **检查 PHP 配置**
   ```bash
   php -i | grep upload_max_filesize
   php -i | grep post_max_size
   ```
   确保 `upload_max_filesize` 和 `post_max_size` 足够大

3. **查看错误日志**
   ```bash
   tail -f runtime/logs/app.log
   ```

### 图片不显示

1. **检查 URL 配置**
   ```php
   // 确保 frontend_url() 返回正确的域名
   echo frontend_url();
   ```

2. **检查文件路径**
   ```php
   // 检查文件是否存在
   $file = \jzkf\filemanager\models\File::findOne($id);
   echo $file->getAbsolutePath();
   file_exists($file->getAbsolutePath());
   ```

3. **检查存储配置**
   - 本地存储：检查 `basePath` 和 `baseUrl` 配置
   - 云存储：检查 CDN 域名和存储桶权限

### 性能问题

1. **执行数据库迁移添加索引**
   ```bash
   ./yii migrate --migrationPath=@vendor/jzkf/filemanager/migrations
   ```

2. **启用缓存**
   ```php
   // 在配置中启用缓存
   'components' => [
       'cache' => [
           'class' => 'yii\caching\FileCache',
       ],
   ],
   ```

3. **使用 CDN**
   - 配置云存储的 CDN 域名
   - 使用 `cdnDomain` 配置项

## 📝 更新日志

### v2.2.0 (2025-01-XX)
- ✅ 发布为 Composer 公共包
- ✅ 命名空间更新为 `jzkf\filemanager`
- ✅ 优化安装和配置文档
- ✅ 完善使用说明

### v2.1.0 (2025-01-11)
- ✅ 集成 league/flysystem 文件系统抽象层
- ✅ 支持多种存储驱动（本地、AWS S3、阿里云 OSS、腾讯云 COS）
- ✅ 统一的文件操作接口
- ✅ 支持 CDN 加速
- ✅ 文件去重功能增强
- ✅ 向后兼容原有上传流程

### v2.0.0 (2025-01-08)
- ✅ 实现统一文件选择器
- ✅ AJAX 翻页和搜索
- ✅ 文件类型过滤
- ✅ 全局 Toast 提示
- ✅ Service 层架构
- ✅ 性能优化建议
- ✅ 安全增强方案

### v1.0.0 (2023-08-19)
- ✅ 基础文件上传功能
- ✅ 文件列表和详情
- ✅ ElFinder 集成
- ✅ 编辑器上传集成

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

### 开发规范

- 遵循 PSR-12 编码标准
- 添加必要的注释
- 编写单元测试
- 更新相关文档

## 📄 许可证

本模块遵循项目整体许可证。

## 👥 维护者

- FileManager 开发团队

## 🔗 相关链接

- [GitHub 仓库](https://github.com/jzkf/filemanager)
- [Packagist 包](https://packagist.org/packages/jzkf/filemanager)
- [Yii2 官方文档](https://www.yiiframework.com/doc/guide/2.0/zh-cn)
- [Flysystem 文档](https://flysystem.thephpleague.com/)

## 📦 安装

```bash
composer require jzkf/filemanager
```

## ⚙️ 系统要求

- PHP >= 8.1.0
- Yii2 >= 2.0.45
- MySQL 5.7+ 或 MariaDB 10.2+
