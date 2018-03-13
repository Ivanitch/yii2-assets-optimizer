# yii2-assets-optimizer


Auto minify and combine css and js files with zero configuration.
===================================

Yii2 extension to minify and combine css and js files automatically. Created to be easy and work out of the box.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist vnxdev/yii2-assets-optimizer "^1.0"
```

or add

```
"vnxdev/yii2-assets-optimizer": "^1.0"
```


How to use
----------

- Add entry ``assetsOptimizer`` to bootstrap section
- Add config array ``assetsOptimizer`` to components section

```php
[
    'bootstrap'    => ['assetsOptimizer'],
    'components'    =>
    [
    //....
        'assetsOptimizer' =>
        [
            'class' => 'vnxdev\Yii2AssetsOptimizer\AssetsOptimizer',
            'enabled' => true
        ],
    //....
    ]
]
```

Config options
----------
```php
    'assetsOptimizer' =>
    [
        'class' => 'vnxdev\Yii2AssetsOptimizer\AssetsOptimizer',
        
        // Enable or disable the component
        'enabled' => true,
        
        // Time in seconds for reading each external asset file
        'readFileTimeout' => 2,
        
        // Combine all CSS files into a single file
        'cssFilesCombine' => true, 
        
        // Download all external CSS files
        'cssFilesRemoteEnable' => true,
        
        // Enable compression for CSS files
        'cssFilesCompress' => true,
        
        // Move down CSS files to the bottom of the page
        'cssFilesToBottom' => true,
        
        // Combine all JS files into a single file
        'jsFilesCombine' => true,
        
        // Download all external JS files
        'jsFilesRemoteEnable' => true,
        
        // Enable compression for JS files
        'jsFilesCompress' => true,
        
        // Remove all JS files inside AJAX responses
        'clearJsOnAjax' => true,
        
        // Remove all JS files inside PJAX responses
        'clearJsOnPjax' => true,
        
        // Skip already minified files
        'skipMinified' => true,
        
        // Pattern to detect minified files
        'skipMinifiedPattern' => '.min',
        
        // Whitelist app routes
        // If not empty, only there app routes will use this extension
        // If app route is present in whitelist and blacklist at the same time - blacklist has higher priority
        // Example config: extension will work only with SiteController and actionIndex.
        // Leave this option empty for extension to work everywhere
        'routesWhitelist' => [
            'site' => [
                'index'
            ]
        ],
        
        // Blacklist app routes
        // If not empty, these app routes will be excluded from using this extension
        // Example config: extension will NOT work with SiteController and actionIndex
        // Leave this option empty for extension to work everywhere        
        'routesBlacklist' => [
            'site' => [
                'index'
            ]
        ],
    ],
```