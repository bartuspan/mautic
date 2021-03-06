<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;
use Symfony\Component\Finder\Finder;

/**
 * Class AssetGenerationHelper
 */
class AssetGenerationHelper
{

    /**
     * @var MauticFactory
     */
    private $factory;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Generates and returns assets
     *
     * @param bool $forceRegeneration
     *
     * @return array
     */
    public function getAssets($forceRegeneration = false)
    {
        static $assets = array();

        if (empty($assets)) {
            $loadAll    = true;
            $env        = ($forceRegeneration) ? 'prod' : $this->factory->getEnvironment();
            $rootPath   = $this->factory->getSystemPath('root');
            $assetsPath = $this->factory->getSystemPath('assets');

            $assetsFullPath = "$rootPath/$assetsPath";
            if ($env == 'prod') {
                $loadAll = false; //by default, loading should not be required

                //check for libraries and app files and generate them if they don't exist if in prod environment
                $prodFiles = array(
                    'css/libraries.css',
                    'css/app.css',
                    'js/libraries.js',
                    'js/app.js'
                );

                foreach ($prodFiles as $file) {
                    if (!file_exists("$assetsFullPath/$file")) {
                        $loadAll = true; //it's missing so compile it
                        break;
                    }
                }
            }

            if ($loadAll || $forceRegeneration) {
                if ($env == 'prod') {
                    ini_set('max_execution_time', 300);

                    $inProgressFile = "$assetsFullPath/generation_in_progress.txt";

                    if (!$forceRegeneration) {
                        while (file_exists($inProgressFile)) {
                            //dummy loop to prevent conflicts if one process is actively regenerating assets
                        }
                    }
                    file_put_contents($inProgressFile, date('r'));
                }

                $modifiedLast = array();

                //get a list of all core asset files
                $bundles = $this->factory->getParameter('bundles');

                $fileTypes = array('css', 'js');
                foreach ($bundles as $bundle) {
                    foreach ($fileTypes as $ft) {
                        if (!isset($modifiedLast[$ft])) {
                            $modifiedLast[$ft] = array();
                        }
                        $dir = "{$bundle['directory']}/Assets/$ft";
                        if (file_exists($dir)) {
                            $modifiedLast[$ft] = array_merge($modifiedLast[$ft], $this->findAssets($dir, $ft, $env, $assets));
                        }
                    }
                }
                $modifiedLast = array_merge($modifiedLast, $this->findOverrides($env, $assets));

                //combine the files into their corresponding name and put in the root media folder
                if ($env == "prod") {
                    $checkPaths = array(
                        $assetsFullPath,
                        "$assetsFullPath/css",
                        "$assetsFullPath/js",
                    );
                    array_walk($checkPaths, function ($path) {
                        if (!file_exists($path)) {
                            mkdir($path);
                        }
                    });

                    $useMinify = class_exists('\Minify');

                    foreach ($assets as $type => $groups) {
                        foreach ($groups as $group => $files) {
                            $assetFile = "$assetsFullPath/$type/$group.$type";

                            //only refresh if a change has occurred
                            $modified = ($forceRegeneration || !file_exists($assetFile)) ? true : filemtime($assetFile) < $modifiedLast[$type][$group];

                            if ($modified) {
                                if (file_exists($assetFile)) {
                                    //delete it
                                    unlink($assetFile);
                                }

                                if ($type == 'css') {
                                    $out = fopen($assetFile, 'w');

                                    foreach ($files as $relPath => $details) {
                                        $cssRel = '../../' . dirname($relPath) . '/';
                                        if ($useMinify) {
                                            $content = \Minify::combine(array($details['fullPath']), array(
                                                'rewriteCssUris'  => false,
                                                'minifierOptions' => array(
                                                    'text/css' => array(
                                                        'currentDir'          => '',
                                                        'prependRelativePath' => $cssRel
                                                    )
                                                )
                                            ));
                                        } else {
                                            $content = file_get_contents($details['fullPath']);
                                            $search  = '#url\((?!\s*([\'"]?(((?:https?:)?//)|(?:data\:?:))))\s*([\'"])?#';
                                            $replace = "url($4{$cssRel}";
                                            $content = preg_replace($search, $replace, $content);
                                        }

                                        fwrite($out, $content);
                                    }

                                    fclose($out);
                                } else {
                                    array_walk($files, function (&$file) { $file = $file['fullPath']; });
                                    file_put_contents($assetFile, \Minify::combine($files));
                                }
                            }
                        }
                    }

                    unlink($inProgressFile);
                }
            }

            if ($env == 'prod') {
                //return prod generated assets
                $assets = array(
                    'css' => array(
                        "{$assetsPath}/css/libraries.css",
                        "{$assetsPath}/css/app.css"
                    ),
                    'js'  => array(
                        "{$assetsPath}/js/libraries.js",
                        "{$assetsPath}/js/app.js"
                    )
                );
            } else {
                foreach ($assets as $type => &$typeAssets) {
                    $typeAssets = array_keys($typeAssets);
                }
            }
        }

        return $assets;
    }

    /**
     * Finds directory assets
     *
     * @param string $dir
     * @param string $ext
     * @param string $env
     * @param array  $assets
     *
     * @return array
     */
    protected function findAssets($dir, $ext, $env, &$assets)
    {
        $rootPath    = $this->factory->getSystemPath('root') . '/';
        $directories = new Finder();
        $directories->directories()->exclude('*less')->depth('0')->ignoreDotFiles(true)->in($dir);

        $modifiedLast = array();

        if (count($directories)) {
            foreach ($directories as $directory) {
                $files         = new Finder();
                $thisDirectory = str_replace('\\', '/', $directory->getRealPath());
                $files->files()->depth('0')->name('*.' . $ext)->in($thisDirectory);

                $sort = function (\SplFileInfo $a, \SplFileInfo $b) {
                    return strnatcmp($a->getRealpath(), $b->getRealpath());
                };
                $files->sort($sort);

                $group = $directory->getBasename();

                foreach ($files as $file) {
                    $fullPath = $file->getPathname();
                    $relPath  = str_replace($rootPath, '', $file->getPathname());
                    if (strpos($relPath, '/') === 0) {
                        $relPath = substr($relPath, 1);
                    }

                    $details = array(
                        'fullPath'  => $fullPath,
                        'relPath'   => $relPath
                    );

                    if ($env == 'prod') {
                        $lastModified = filemtime($fullPath);
                        if (!isset($modifiedLast[$group]) || $lastModified > $modifiedLast[$group]) {
                            $modifiedLast[$group] = $lastModified;
                        }
                        $assets[$ext][$group][$relPath] = $details;
                    } else {
                        $assets[$ext][$relPath] = $details;
                    }
                }
                unset($files);
            }
        }

        unset($directories);
        $files = new Finder();
        $files->files()->depth('0')->ignoreDotFiles(true)->name('*.' . $ext)->in($dir);

        $sort = function (\SplFileInfo $a, \SplFileInfo $b) {
            return strnatcmp($a->getRealpath(), $b->getRealpath());
        };
        $files->sort($sort);

        foreach ($files as $file) {
            $fullPath = $file->getPathname();
            $relPath  = str_replace($rootPath, '', $fullPath);

            $details = array(
                'fullPath'  => $fullPath,
                'relPath'   => $relPath
            );

            if ($env == 'prod') {
                $lastModified = filemtime($fullPath);
                if (!isset($modifiedLast['app']) || $lastModified > $modifiedLast['app']) {
                    $modifiedLast['app'] = $lastModified;
                }
                $assets[$ext]['app'][$relPath] = $details;
            } else {
                $assets[$ext][$relPath] = $details;
            }
        }
        unset($files);

        return $modifiedLast;
    }

    /**
     * Find asset overrides in the template
     *
     * @param $env
     * @param $assets
     *
     * @return array
     */
    protected function findOverrides($env, &$assets)
    {
        $rootPath      = $this->factory->getSystemPath('root');
        $currentTheme  = $this->factory->getSystemPath('currentTheme');
        $modifiedLast  = array();
        $types         = array('css', 'js');
        $overrideFiles = array(
            "libraries" => "libraries_custom",
            "app"       => "app_custom"
        );

        foreach ($types as $ext) {
            foreach ($overrideFiles as $group => $of) {
                if (file_exists("$rootPath/$currentTheme/$ext/$of.$ext")) {
                    $fullPath = "$rootPath/$currentTheme/$ext/$of.$ext";
                    $relPath  = "$currentTheme/$ext/$of.$ext";

                    $details = array(
                        'fullPath'  => $fullPath,
                        'relPath'   => $relPath
                    );

                    if ($env == 'prod') {
                        $lastModified = filemtime($fullPath);
                        if (!isset($modifiedLast[$ext][$group]) || $lastModified > $modifiedLast[$ext][$group]) {
                            $modifiedLast[$ext][$group] = $lastModified;
                        }
                        $assets[$ext][$group][$relPath] = $details;
                    } else {
                        $assets[$ext][$relPath] = $details;
                    }
                }
            }
        }

        return $modifiedLast;
    }
}
