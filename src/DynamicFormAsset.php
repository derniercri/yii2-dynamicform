<?php

declare(strict_types=1);

namespace DernierCri\Yii2Dynamicform;

class DynamicFormAsset extends \yii\web\AssetBundle
{
    public $depends = [
        'yii\web\JqueryAsset',
        'yii\widgets\ActiveFormAsset'
    ];

    public function init(): void
    {
        $this->sourcePath = $path = \sprintf('%s/assets', __DIR__);

        foreach (\glob(\sprintf('%s/*.js', $path)) as $file) {
            $this->js = array_merge($this->js, [\basename($file)]);
        }

        parent::init();
    }
}
