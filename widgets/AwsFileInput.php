<?php

namespace micetm\aws\fileinput\widgets;

use yii\helpers\Inflector;
use yii\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

class AwsFileInput extends \kartik\file\FileInput
{
    /** @var string AWS component name */
    public $awsComponent = 'aws';

    /** @var string model unique key */
    public $uniqueKey = 'id';

    /** @var array Custom filename parts */
    public $fileNameParts = [];

    /** @var string with path to folder in S3 bucket with trailing slash */
    public $folder = '';

    public $autoOrientImage = false;
    
    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->options['multiple']) {
            throw new InvalidConfigException('Widget ' . __CLASS__ . ' - supports only single file uploading');
        }
        /** @var \Aws\S3\PostObjectV4 $awsPostObject */
        $awsPostObject = \Yii::$app->get($this->awsComponent)->createPostObjectV4($this->getUniqueFileName());
        $this->options = ArrayHelper::merge(
            [
                'hiddenOptions' => [
                    'value' => $this->model->{$this->attribute} ?? '',
                ],
            ],
            $this->options
        );
        $this->defaultPluginOptions = ArrayHelper::merge(
            [
                'dropZoneEnabled' => false,
            ],
            $this->defaultPluginOptions
        );
        $this->pluginOptions = ArrayHelper::merge(
            [
                'autoOrientImage' => $this->autoOrientImage,
                'uploadAsync' => true,
                'initialPreview' => $this->model->{$this->attribute} ?? '',
                'initialCaption'=> $this->model->{$this->attribute} ?? '',
                'initialPreviewAsData' => true,
                'uploadUrl' => $awsPostObject->getFormAttributes()['action'],
                'uploadExtraData' => $awsPostObject->getFormInputs(),
                'showUpload' => false,
                'showPreview' => true,
                'removeClass' => 'btn btn-danger',
                'layoutTemplates' => [
                    'footer' => '',
                    'preview' => join(',', [
                        '<div class="file-preview {class}">' .
                        '<div class="file-preview-thumbnails"></div>' .
                        '<div class="clearfix"></div>' .
                        '<div class="file-preview-status text-center text-success"></div>' .
                        '<div class="kv-fileinput-error"></div>' .
                        '</div>'
                    ]),
                ],
            ],
            $this->pluginOptions
        );
        $this->pluginEvents = ArrayHelper::merge(
            [
                'fileselect' => "function(event, numFiles, label) {
                    $(this).fileinput('upload');
                }",
                'filepreupload' => "function (event, data, previewId, index) {
                    var file = data.files[0],
                        extension = file.name.substr((~-file.name.lastIndexOf('.') >>> 0) + 1);
                        
                    if (data.hasOwnProperty('form')) {
                        var fileName = data.form.get('key');
                        var form = data.form;
                    } else if (data.hasOwnProperty('formData')) {
                        var fileName = data.formData.get('key');
                        var form = data.formData;
                    } else {
                        return;
                    }
                    form.delete(this.name);
                    form.delete('file_id');
                    form.delete('key');
                    form.set('content-type', file.type);
                    form.set('key', fileName + extension);
                    form.set('file', file);
                }",
                'fileuploaded' => "function (event, data, index) {
                    var file = data.files[0],
                        extension = file.name.substr((~-file.name.lastIndexOf('.') >>> 0) + 1),
                        form = $('form#" . $this->field->form->id . "'),
                        filePath = '" . $awsPostObject->getFormAttributes()['action'] . "/' + data.extra.key + extension;
                    form.find('input[type=hidden][name=\"' + this.name + '\"]').val(filePath);
                    form.find('.file-caption-name').attr('title', '').val(filePath);
                    
                    data.filePath = filePath;
                    $(document).trigger({
                        type: 'awsFileInput.afterFileUpload',
                        widgetData: data
                    });
                }",
                'filecleared' => "function (event, id, index) {
                    $('form#" . $this->field->form->id . "').find('input[type=hidden][name=\"' + this.name + '\"]').val('');
                }",
            ],
            $this->pluginEvents
        );
        $this->getView()->registerJs("$('form#" . $this->field->form->id ."').on('beforeSubmit', function () {
            if ($(this).find('.has-error').length) {
                return false;
            }
            return true;
        });");
        parent::init();
    }

    /**
     * Returns unique filename
     * @return string
     * @throws InvalidConfigException
     */
    protected function getUniqueFileName()
    {
        $formName = Inflector::camel2id($this->model->formName());
        if ($this->fileNameParts) {
            return $filename = $this->folder . $formName . '-' . join('-', $this->fileNameParts) . '-' . uniqid();
        }
        return $this->folder . join('-', [$formName, $this->model->{$this->uniqueKey}, $this->field->attribute, uniqid()]);
    }
}
