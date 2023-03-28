<?php

declare(strict_types=1);

namespace DernierCri\Yii2Dynamicform;

use Symfony\Component\DomCrawler\Crawler;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\web\View;

class DynamicFormWidget extends \yii\base\Widget
{
    public const WIDGET_NAME = 'dynamicform';

    private array $_insertPositions = ['bottom', 'top'];
    public string $deleteButton;
    private string $encodedOptions;
    public array $formFields;
    public string $formId;
    private string $hashVar;
    public string $insertButton;
    public string $insertPosition = 'bottom';
    public int $limit = 999;
    public int $min = 1;
    public \yii\base\Model $model;
    private array $options;
    public string $widgetBody;
    public string $widgetContainer;
    public string $widgetItem;

    public function init(): void
    {
        parent::init();

        if (empty($this->widgetContainer) || 0 === preg_match('/^\w{1,}$/', $this->widgetContainer)) {
            throw new InvalidConfigException('Invalid configuration to property "widgetContainer". Allowed only alphanumeric characters plus underline: [A-Za-z0-9_]');
        }

        if (empty($this->widgetBody)) {
            throw new InvalidConfigException("The 'widgetBody' property must be set.");
        }

        if (empty($this->widgetItem)) {
            throw new InvalidConfigException("The 'widgetItem' property must be set.");
        }

        if (empty($this->model) || !$this->model instanceof \yii\base\Model) {
            throw new InvalidConfigException("The 'model' property must be set and must extend from '\\yii\\base\\Model'.");
        }

        if (empty($this->formId)) {
            throw new InvalidConfigException("The 'formId' property must be set.");
        }

        if (empty($this->insertPosition) || ! in_array($this->insertPosition, $this->_insertPositions)) {
            throw new InvalidConfigException("Invalid configuration to property 'insertPosition' (allowed values: 'bottom' or 'top')");
        }

        if (empty($this->formFields) || !is_array($this->formFields)) {
            throw new InvalidConfigException("The 'formFields' property must be set.");
        }

        $this->initOptions();
    }

    protected function initOptions(): void
    {
        $this->options = \array_merge($this->options ?? [], [
            'deleteButton' => $this->deleteButton,
            'fields' => [],
            'formId' => $this->formId,
            'insertButton' => $this->insertButton,
            'insertPosition' => $this->insertPosition,
            'limit' => $this->limit,
            'min' => $this->min,
            'widgetBody' => $this->widgetBody,
            'widgetContainer' => $this->widgetContainer,
            'widgetItem' => $this->widgetItem,
        ]);

        foreach ($this->formFields as $field) {
             $this->options['fields'][] = [
                'id' => Html::getInputId($this->model, sprintf('[{}]%s', $field)),
                'name' => Html::getInputName($this->model, sprintf('[{}]%s', $field))
            ];
        }

        ob_start();
        ob_implicit_flush(false);
    }

    protected function registerOptions(View $view): void
    {
        $view->registerJs(sprintf("var %s = %s;\n", $this->hashVar, $this->encodedOptions), View::POS_HEAD);
    }

    protected function hashOptions(): void
    {
        $this->encodedOptions = \json_encode($this->options);
        $this->hashVar = sprintf('%s_%s', self::WIDGET_NAME, \hash('crc32', $this->encodedOptions));
    }

    protected function getHashVarName(): string
    {
        if (\array_key_exists($this->widgetContainer, $config = \Yii::$app->params[self::WIDGET_NAME])) {
            return $config[$this->widgetContainer];
        }

        return $this->hashVar;
    }

    public function registerHashVarWidget(): bool
    {
        if (!isset(\Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer])) {
            \Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer] = $this->hashVar;

            return true;
        }

        return false;
    }

    public function registerAssets(View $view): void
    {
        DynamicFormAsset::register($view);

        $view->registerJs(<<<JS
/** add a click handler for the clone button */
jQuery("#$this->formId").on("click", "$this->insertButton", function(e) {
    e.preventDefault();

    jQuery("$this->widgetContainer").triggerHandler("beforeInsert", [jQuery(this)]);
    jQuery("$this->widgetContainer").yiiDynamicForm("addItem", $this->hashVar, e, jQuery(this));
});

/** add a click handler for the remove button */
jQuery("#$this->formId").on("click", "$this->deleteButton", function(e) {
    e.preventDefault();

    jQuery("$this->widgetContainer").yiiDynamicForm("deleteItem", $this->hashVar, e, jQuery(this));
});
JS, $view::POS_READY);

        $view->registerJs(<<<JS
jQuery("#$this->formId").yiiDynamicForm($this->hashVar);
JS, $view::POS_LOAD);
    }

    public function run(): void
    {
        $content = ob_get_clean();
        $crawler = new Crawler;
        $crawler->addHTMLContent($content, \Yii::$app->charset);
        $results = $crawler->filter($this->widgetItem);
        $document = new \DOMDocument('1.0', \Yii::$app->charset);
        $document->appendChild($document->importNode($results->first()->getNode(0), true));
        $this->options['template'] = \trim($document->saveHTML());

        if (isset($this->options['min']) && $this->options['min'] === 0 && $this->model->isNewRecord) {
            $content = $this->removeItems($content);
        }

        $this->hashOptions();
        \assert(($view = $this->getView()) instanceof View);
        $widgetRegistered = $this->registerHashVarWidget();
        $this->hashVar = $this->getHashVarName();

        if ($widgetRegistered) {
            $this->registerOptions($view);
            $this->registerAssets($view);
        }

        echo Html::tag('div', $content, ['class' => $this->widgetContainer, 'data-dynamicform' => $this->hashVar]);
    }

    private function removeItems(string $content): string
    {
        $crawler = new Crawler;
        $crawler->addHTMLContent($content, \Yii::$app->charset);
        $crawler->filter($this->widgetItem)->each(static function (Crawler $nodes) {
            foreach ($nodes as $node) {
                \assert($node instanceof \DOMElement);

                $node->parentNode->removeChild($node);
            }
        });

        return $crawler->html();
    }
}
