<?php

namespace jzkf\filemanager\controllers;

use jzkf\filemanager\models\File;
use jzkf\filemanager\models\form\UploadForm;
use jzkf\filemanager\models\search\FileSearch;
use jzkf\filemanager\services\FileService;
use Yii;
use yii\base\Exception;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * DefaultController implements the CRUD actions for File model.
 */
class DefaultController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    /**
     * @var FileService 文件服务
     */
    protected $fileService;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        $this->fileService = new FileService();
    }

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    public function rules()
    {
        return [['upload'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg, jpeg, gif, bmp'];
    }

    /**
     * Lists all File models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new FileSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $totalSize = File::find()->notDeleted()->sum('size') ?: 0;

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'totalSize' => $totalSize,
        ]);
    }

    /**
     * Displays a single File model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        
        // 权限检查
        if (!$model->canAccess()) {
            throw new \yii\web\ForbiddenHttpException('您没有权限访问此文件');
        }
        
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * 下载文件
     * @param int $id
     * @return \yii\console\Response
     * @throws NotFoundHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionDownload($id)
    {
        $model = $this->findModel($id);
        
        // 权限检查
        if (!$model->canAccess()) {
            throw new \yii\web\ForbiddenHttpException('您没有权限下载此文件');
        }
        
        // 检查文件是否存在
        if (!$model->fileExists()) {
            throw new NotFoundHttpException('文件不存在或已被删除');
        }
        
        return Yii::$app->response->sendFile(
            $model->getAbsolutePath(),
            $model->origin_name
        );
    }

    /**
     * Deletes an existing File model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        try {
            $model = $this->findModel($id);
            
            // 权限检查
            if (!$model->canDelete()) {
                throw new \yii\web\ForbiddenHttpException('您没有权限删除此文件');
            }
            
            $this->fileService->delete($id);
            Yii::$app->session->setFlash('success', '文件删除成功');
        } catch (NotFoundHttpException $e) {
            Yii::$app->session->setFlash('error', '文件不存在');
        } catch (\yii\web\ForbiddenHttpException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * 批量删除文件
     * 
     * @return array
     */
    public function actionBatchDelete()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            $ids = Yii::$app->request->post('ids', []);
            
            if (empty($ids) || !is_array($ids)) {
                throw new BadRequestHttpException('请选择要删除的文件');
            }
            
            $result = $this->fileService->batchDelete($ids);
            
            return [
                'success' => $result['failed'] == 0,
                'message' => sprintf(
                    '成功删除 %d 个文件%s',
                    $result['success'],
                    $result['failed'] > 0 ? '，失败 ' . $result['failed'] . ' 个' : ''
                ),
                'data' => $result,
            ];
            
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批量更新状态
     * 
     * @return array
     */
    public function actionBatchStatus()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            $ids = Yii::$app->request->post('ids', []);
            $status = Yii::$app->request->post('status');
            
            if (empty($ids) || !is_array($ids)) {
                throw new BadRequestHttpException('请选择要操作的文件');
            }
            
            if (!in_array($status, [0, 1])) {
                throw new BadRequestHttpException('无效的状态值');
            }
            
            $count = File::updateAll(['status' => $status], ['in', 'id', $ids]);
            
            // 清除缓存
            \jzkf\filemanager\models\search\FileSearch::clearCache();
            
            return [
                'success' => true,
                'message' => "成功更新 {$count} 个文件状态",
            ];
            
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 文件统计页面
     * 
     * @return string
     */
    public function actionStatistics()
    {
        $stats = $this->fileService->getStatistics();
        
        return $this->render('statistics', [
            'stats' => $stats,
        ]);
    }

    /**
     * Finds the File model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return File the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = File::find()->notDeleted()->andWhere(['id' => $id])->one()) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

    /**
     * 显示上传表单（用于Modal）
     * @return string
     */
    public function actionUploadForm()
    {
        $model = new UploadForm();
        return $this->renderAjax('upload', [
            'model' => $model,
        ]);
    }

    /**
     * 文件选择器（选择已有文件或上传新文件）
     * @return string
     */
    public function actionFilePicker()
    {
        $searchModel = new \jzkf\filemanager\models\search\FileSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->pagination->pageSize = 12; // 每页显示12个文件
        
        // 接收 accept 参数用于文件类型过滤
        $accept = Yii::$app->request->get('accept', 'image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar');
        
        // 如果指定了 accept，过滤文件列表
        if ($accept && $accept !== '*') {
            $this->filterFilesByAccept($dataProvider, $accept);
        }
        
        return $this->renderAjax('file-picker', [
            'dataProvider' => $dataProvider,
            'accept' => $accept,
        ]);
    }
    
    /**
     * 根据 accept 参数过滤文件
     * @param \yii\data\ActiveDataProvider $dataProvider
     * @param string $accept
     */
    protected function filterFilesByAccept($dataProvider, $accept)
    {
        // 解析 accept 参数
        $extensions = [];
        $mimeTypes = [];
        
        $parts = explode(',', $accept);
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (strpos($part, '/') !== false) {
                // MIME type (如 image/*, video/mp4)
                if (substr($part, -1) === '*') {
                    // 通配符，如 image/*
                    $type = substr($part, 0, -2);
                    $mimeTypes[] = $type;
                } else {
                    // 具体 MIME type
                    $mimeTypes[] = $part;
                }
            } elseif (strpos($part, '.') === 0) {
                // 扩展名，如 .pdf, .jpg
                $extensions[] = substr($part, 1);
            }
        }
        
        // 添加查询条件
        if (!empty($extensions) || !empty($mimeTypes)) {
            $query = $dataProvider->query;
            
            $conditions = ['or'];
            
            if (!empty($extensions)) {
                $conditions[] = ['in', 'extension', $extensions];
            }
            
            if (!empty($mimeTypes)) {
                foreach ($mimeTypes as $mimeType) {
                    $conditions[] = ['like', 'mime_type', $mimeType . '%', false];
                }
            }
            
            $query->andWhere($conditions);
        }
    }

    /**
     * 处理文件上传
     * @return array
     */
    public function actionUploadFile()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            if (!Yii::$app->request->isPost) {
                throw new BadRequestHttpException('请求方式错误');
            }
            
            $model = new UploadForm();
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            
            if (!$model->imageFile) {
                throw new BadRequestHttpException('请选择要上传的文件');
            }
            
            // 使用 Service 层处理上传
            $file = $this->fileService->upload($model->imageFile);
            
            return [
                'success' => true,
                'message' => isset($file['existing']) && $file['existing'] ? '文件已存在，已自动关联' : '文件上传成功！',
                'data' => [
                    'url' => $file['file_url'],
                    'name' => $file['file_name'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'width' => $file['width'] ?? 0,
                    'height' => $file['height'] ?? 0,
                    'existing' => $file['existing'] ?? false,
                ]
            ];
            
        } catch (BadRequestHttpException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Yii::error([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);
            
            return [
                'success' => false,
                'message' => YII_DEBUG ? $e->getMessage() : '文件上传失败，请联系管理员',
            ];
        }
    }

    /**
     * Ckeditor 图片上传.
     * @return array|bool
     */
    public function actionCkeUploadImage()
    {
        $errorMessage = '';
        Yii::$app->response->format = Response::FORMAT_JSON;
        $model = new UploadForm();
        if (Yii::$app->request->isPost) {

            // 兼容上传字段为file
            $upload = UploadedFile::getInstanceByName('file');
            if (!$upload) {
                $upload = UploadedFile::getInstanceByName('upload');
            }

            if ($upload->error !== UPLOAD_ERR_OK) {
                $max_upload = (int)(ini_get('upload_max_filesize'));
                $max_post = (int)(ini_get('post_max_size'));
                $memory_limit = (int)(ini_get('memory_limit'));
                $upload_mb = min($max_upload, $max_post, $memory_limit);

                // Handle the error based on the error code
                switch ($upload->error) {
                    case UPLOAD_ERR_INI_SIZE:
                        $errorMessage = '上传文件超过了系统设置的最大上传（' . $upload_mb . 'm）.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMessage = '上传的文件超出了 HTML 表单中指定的 MAX_FILE_SIZE 指令.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMessage = '上传的文件仅部分上传.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errorMessage = '未上传任何文件.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $errorMessage = '缺少临时文件夹.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $errorMessage = '无法将文件写入磁盘.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $errorMessage = '系统停止了文件上传.';
                        break;
                    default:
                        $errorMessage = '未知上传错误.';
                        break;
                }

                // Output or log the error message
                Yii::error($errorMessage);
                return ['uploaded' => 0, 'error' => $errorMessage];
            }

            $model->imageFile = $upload;

            // 文件上传成功
            if ($file = $model->upload()) {
                return [
                    'uploaded' => 1,
                    'fileName' => $model->imageFile->name,
                    'url' => frontend_url() . $file['file_path'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'error' => '上传成功！'
                ];
            }
        }
        return [
            'uploaded' => 0,
            'fileName' => $model->imageFile->name,
            'url' => '',
            'error' => $errorMessage ?? '上传错误，请联系网站管理员！'
        ];
    }

    /**
     * 图片上传
     * @return array|false
     */
    public function actionWebUploader()
    {
        $data = $this->actionCkeUploadImage();

        return [
            'code' => !$data['uploaded'],
            'url' => $data['url'],
            'attachment' => $data['url'],
            'msg' => $data['error'],
        ];
    }

    /**
     * TinyMCE图片上传.
     * @return array
     */
    public function actionTinymceUpload()
    {
        $data = $this->actionCkeUploadImage();

        return [
            'location' => $data['url'],
        ];
    }

    /**
     * dm file uploader 上传.
     * @return array|bool|string[]
     * @throws Exception
     */
    public function actionDmFileUploader()
    {
        $data = $this->actionCkeUploadImage();

        if ($data['uploaded'] == 1) {
            return [
                'status' => 'ok',
                'path' => $data['url'],
                'type' => $data['type'],
                'size' => $data['size'],
                "message" => Yii::t('app', 'Upload Successfully!')
            ];
        }

        return [
            'status' => 'error',
            "message" => $data['error']
        ];
    }

    /**
     * UEditor 图片上传.
     * @return array
     */
    public function actionUeditorUpload()
    {
        $data = $this->actionCkeUploadImage();
        
        return [
            'uploaded' => $data['uploaded'],
            'fileName' => $data['fileName'],
            'url' => $data['url'],
            'error' => $data['error'],
        ];
    }
}
