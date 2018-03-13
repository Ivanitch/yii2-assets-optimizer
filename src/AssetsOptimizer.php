<?php
/**
 * @author Ivan Siianytsia <vnxdev@gmail.com>
 * @copyright (c) 2018 vnxdev
 */

namespace vnxdev\Yii2AssetsOptimizer;

use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Response;
use yii\web\View;
use \yii\web\Application;
use MatthiasMullie\Minify;

/**
 * Class AssetsOptimizer
 * @package vnxdev\Yii2AssetsOptimizer
 */
class AssetsOptimizer extends Component implements BootstrapInterface
{
    /**
     * Enable or disable the component
     * @var bool
     */
    public $enabled = true;

    /**
     * Time in seconds for reading each asset file
     * @var int
     */
    public $readFileTimeout = 2;

    /**
     * Combine all CSS files into a single file
     * @var bool
     */
    public $cssFilesCombine = true;

    /**
     * Download all external CSS files
     * @var bool
     */
    public $cssFilesRemoteEnable = false;

    /**
     * Enable compression for CSS files
     * @var bool
     */
    public $cssFilesCompress = true;

    /**
     * Move down css files to the bottom of the page
     * @var bool
     */
    public $cssFilesToBottom = false;

    /**
     * Combine all JS files into a single file
     * @var bool
     */
    public $jsFilesCombine = true;

    /**
     * Download all external JS files
     * @var bool
     */
    public $jsFilesRemoteEnable = false;

    /**
     * Enable compression for JS files
     * @var bool
     */
    public $jsFilesCompress = true;

    /**
     * Remove all JS files inside AJAX responses
     * @var bool
     */
    public $clearJsOnAjax = true;

    /**
     * Remove all JS files inside PJAX responses
     * @var bool
     */
    public $clearJsOnPjax = true;

    /**
     * Skip already minified files
     * @var bool
     */
    public $skipMinified = true;

    /**
     * Pattern to detect minified files
     * @var string
     */
    public $skipMinifiedPattern = '.min';

    /**
     * @var string
     */
    protected $_webroot = '@webroot';

    /**
     * Whitelist app routes
     * If not empty, only there app routes will use this extension
     * If app route is present in whitelist and blacklist at the same time - blacklist has higher priority
     * @var array
     */
    public $routesWhitelist;

    /**
     * Blacklist app routes
     * If not empty, these app routes will be excluded from using this extension
     * @var array
     */
    public $routesBlacklist;

    /**
     * @return bool|string
     */
    public function getWebroot()
    {
        return \Yii::getAlias($this->_webroot);
    }

    /**
     * @param $path
     * @return $this
     */
    public function setWebroot($path)
    {
        $this->_webroot = $path;
        return $this;
    }

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        if ($app instanceof Application) {
            $app->view->on(View::EVENT_END_PAGE, function (Event $e) use ($app) {
                /**
                 * @var $view View
                 */
                $view = $e->sender;
                $context = $view->context;
                $isController = isset($context->id) && isset($context->module) && isset($context->action);

                if ($isController && !empty($this->routesBlacklist)) {
                    if ((array_key_exists($context->id, $this->routesBlacklist)
                            && empty($this->routesBlacklist[$context->id]))
                        || (array_key_exists($context->id, $this->routesBlacklist)
                            && in_array($context->action->id, $this->routesBlacklist[$context->id]))) {
                        return true;
                    }
                }

                if ($isController && !empty($this->routesWhitelist)) {
                    if (!array_key_exists($context->id, $this->routesWhitelist)
                        || (array_key_exists($context->id, $this->routesWhitelist)
                            && !in_array($context->action->id, $this->routesWhitelist[$context->id]))) {
                        return true;
                    }
                }

                if (($app->request->isPjax && $this->clearJsOnPjax)
                    || ($app->request->isAjax && $this->clearJsOnAjax)) {
                    $app->view->jsFiles = null;
                    return true;
                }

                if ($this->enabled
                    && $view instanceof View
                    && $app->response->format == Response::FORMAT_HTML) {
                    $this->optimize($view);
                }
            });
        }
    }


    /**
     * @return string
     */
    public function getSettingsHash()
    {
        return serialize((array) $this);
    }

    /**
     * Read file contents
     * @param $file
     * @return string
     */
    public function fileGetContents($file)
    {
        if (function_exists('curl_version')) {
            return $this->getFileCurl($file);
        } else {
            return $this->getFilePhp($file);
        }
    }

    /**
     * @param $filePath
     * @return string
     * @throws \Exception
     */
    public function readLocalFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File does not exist '{$filePath}'");
        }

        $file = fopen($filePath, "r");
        if (!$file) {
            throw new \Exception("Unable to open file: '{$filePath}'");
        }

        $content = fread($file, filesize($filePath));
        fclose($file);

        return $content;
    }

    /**
     * Read external file via CURL
     * @param $file
     * @return string
     */
    protected function getFileCurl($file)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $file);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->readFileTimeout);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    /**
     * Read external file via PHP
     * @param $file
     * @return string
     */
    protected function getFilePhp($file)
    {
        return file_get_contents($file);
    }

    /**
     * @param View $view
     */
    protected function optimize(View $view)
    {
        if (!empty($view->jsFiles) && $this->jsFilesCombine) {
            foreach ($view->jsFiles as $pos => $files) {
                if (!empty($files)) {
                    $view->jsFiles[$pos] = $this->optimizeJs($files);
                }
            }
        }

        if (!empty($view->cssFiles) && $this->cssFilesCombine) {
            $view->cssFiles = $this->optimizeCss($view->cssFiles);
        }

        if (!empty($view->cssFiles) && $this->cssFilesToBottom) {
            if (ArrayHelper::getValue($view->jsFiles, View::POS_END)) {
                $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->cssFiles, $view->jsFiles[View::POS_END]);
            } else {
                $view->jsFiles[View::POS_END] = $view->cssFiles;
            }

            $view->cssFiles = [];
        }
    }

    /**
     * Optimize JS files
     * @param array $files
     * @return array
     */
    protected function optimizeJs($files = [])
    {
        $fileName =  md5(implode(array_keys($files)) . $this->getSettingsHash()) . '.js';
        $publicUrl = \Yii::$app->assetManager->baseUrl . '/js-compress/' . $fileName;
        $rootDir = \Yii::$app->assetManager->basePath . '/js-compress';
        $rootUrl = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl)) {
            $resultFiles = [];

            if (!$this->jsFilesRemoteEnable) {
                foreach ($files as $fileCode => $fileTag) {
                    if (!Url::isRelative($fileCode)) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl);

            return $resultFiles;
        }

        try {
            $resultContent = [];
            $resultFiles = [];

            foreach ($files as $fileCode => $fileTag) {
                if (Url::isRelative($fileCode)) {
                    if ($pos = strpos($fileCode, "?")) {
                        $fileCode = substr($fileCode, 0, $pos);
                    }

                    $fileCode = $this->webroot . $fileCode;
                    $contentFile = $this->readLocalFile($fileCode);

                    if ($this->jsFilesCompress) {
                        if ($this->skipMinified && strpos($fileCode, $this->skipMinifiedPattern) === false) {
                            $minifier = new Minify\JS();
                            $minifier->add(trim($contentFile));
                            $contentFile = $minifier->minify();
                        }
                    }

                    $resultContent[] = $contentFile;
                } else {
                    if ($this->jsFilesRemoteEnable) {
                        $contentFile = $this->fileGetContents($fileCode);
                        $resultContent[] = trim($contentFile);
                    } else {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error(__METHOD__ . ": " . $e->getMessage(), static::class);
            return $files;
        }

        if ($resultContent) {
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            $content = implode(";\n", $resultContent);

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }

        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl);

            return $resultFiles;
        } else {
            return $files;
        }
    }

    /**
     * Optimize CSS files
     * @param array $files
     * @return array
     */
    protected function optimizeCss($files = [])
    {
        $fileName =  md5(implode(array_keys($files)) . $this->getSettingsHash()) . '.css';
        $publicUrl = \Yii::$app->assetManager->baseUrl . '/css-compress/' . $fileName;
        $rootDir = \Yii::$app->assetManager->basePath . '/css-compress';
        $rootUrl = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl)) {
            $resultFiles = [];

            if (!$this->cssFilesRemoteEnable) {
                foreach ($files as $fileCode => $fileTag) {
                    if (!Url::isRelative($fileCode)) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl);

            return $resultFiles;
        }

        try {
            $resultContent  = [];
            $resultFiles = [];

            foreach ($files as $fileCode => $fileTag) {
                if (Url::isRelative($fileCode)) {
                    $fileCodeLocal = $fileCode;
                    if ($pos = strpos($fileCode, "?")) {
                        $fileCodeLocal = substr($fileCodeLocal, 0, $pos);
                    }

                    $fileCodeLocal = $this->webroot . $fileCodeLocal;
                    $contentTmp = trim($this->readLocalFile($fileCodeLocal));

                    $fileCodeTmp = explode("/", $fileCode);
                    unset($fileCodeTmp[count($fileCodeTmp) - 1]);

                    if ($this->cssFilesCompress) {
                        if ($this->skipMinified && strpos($fileCode, $this->skipMinifiedPattern) === false) {
                            $minifier = new Minify\CSS();
                            $minifier->add($contentTmp);
                            $contentTmp = $minifier->minify();
                        }
                    }

                    $resultContent[] = $contentTmp;
                } else {
                    if ($this->cssFilesRemoteEnable) {
                        $resultContent[] = $this->fileGetContents($fileCode);
                    } else {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error(__METHOD__ . ": " . $e->getMessage(), static::class);
            return $files;
        }

        if ($resultContent) {
            $content = implode("\n", $resultContent);
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }

        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl);
            return $resultFiles;
        } else {
            return $files;
        }
    }
}
