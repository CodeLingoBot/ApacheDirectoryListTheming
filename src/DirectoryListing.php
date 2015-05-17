<?php

namespace Potherca\Apache\Modules\AutoIndex;

use League\CommonMark\CommonMarkConverter;

class DirectoryListing
{

    private $aAssets = ['css' => [], 'js' => []];

    private $sReadmeHtml = '';
    private $sThumbnailHtml = '';
    private $sReadme = '';

    public function footer()
    {
        $sContent = '';

        $this->init();

        $sReadme = $this->sReadme;

        $sContent .= <<<HTML
            </div><!-- .panel-body -->
        </div><!-- .main-content -->
    </div><!-- .container -->

    ${sReadme}

    <footer class="footer">
        <div class="container">
            ${_SERVER['SERVER_SIGNATURE']}
        </div>
    </footer>
HTML;

        foreach ($this->aAssets['js'] as $sJavascript) {
            $sContent .= <<<HTML
        <script src="/Directory_Listing_Theme/${sJavascript}" type="text/javascript"></script>
HTML;
        }
        $sContent .= <<<HTML
</body>
</html>
HTML;
        return $sContent;
    }

    public function header()
    {
        $sContent = '';

        $this->init();

        $sIndex = $this->buildTitle();
        $sIndexHtml = $this->buildBreadcrumbHtml();
        $sReadmeHtml = $this->sReadmeHtml;
        $sThumbnailHtml = $this->sThumbnailHtml;

        $sContent .= <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Index of ${sIndex}</title>
HTML;
        foreach ($this->aAssets['css'] as $sStylesheet) {
            $sContent .= <<<HTML
        <link rel="stylesheet" href="/Directory_Listing_Theme/${sStylesheet}" />
HTML;
        }

        $sContent .= <<<HTML
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
</head>

<body>
    <div class="container">
        <div class="header clearfix">
            <h1 class="text-muted">
                <span>Directory index</span>
            </h1>
            <ol class="breadcrumb">
                ${sIndexHtml}
            </ol>
        </div><!-- .header -->
HTML;
        if ($sReadmeHtml) {
            $sContent .= <<<HTML
        <div class="page readme | jumbotron" style="max-height: 24em; overflow: auto;">
            ${sReadmeHtml}
        </div><!-- .readme -->
HTML;
        }

        $sContent .= <<<HTML
        ${sThumbnailHtml}

        <div class="page main-content | container panel panel-primary">
            <div class="panel-body">
                <label>
                    Filter by name:
                    <input id="filter" />
                </label>
<!--
            </div>.panel-body
        </div>.main-content
    </div>.container
</body>
</html>
-->

HTML;

        return $sContent;
    }


    private $aBootswatchThemes = [
        'Cosmo',
        'Cyborg',
        'Darkly',
        'Flatly',
        'Journal',
        'Kingboard',
        'Lumen',
        'Paper',
        'React',
        'Readable',
        'Sandstone',
        'Simplex',
        'Slate',
        'Spacelab',
        'Superhero',
        'United',
        'Yeti',
        'Zerif',
    ];

    private $aConfig = [
        "theme" => "default",
        "readmePrefixes" => ["readme", "README", "ReadMe"],
        "readmeExtensions" => [".html", ".md", ".txt"],
        "assets" => []
    ];

    private $bUseBootstrap = false;

    private $sConfigFile = 'config.json';

    private function init()
    {
        if (is_file($this->sConfigFile)) {

            $this->bUseBootstrap = true;

            if (!is_readable($this->sConfigFile)) {
                throw new \Exception("Could not read configuration file");
            } else {
                $this->aConfig = array_merge(
                    $this->aConfig,
                    json_decode(file_get_contents($this->sConfigFile), true)
                );
            }
        }

        if (isset($_GET['theme'])
            && in_array(ucfirst($_GET['theme']), $this->aBootswatchThemes)
        ) {
            $this->bUseBootstrap = true;
            $sThemeDir = 'vendor/bower-asset/bootswatch/' . $_GET['theme'] . '/';
        } elseif ($this->bUseBootstrap === true
            && is_dir($this->getRootDirectory() . '/vendor/bower-asset/bootswatch/' . $this->aConfig['theme'])
        ) {
            $sThemeDir = 'vendor/bower-asset/bootswatch/' . $this->aConfig['theme'] . '/';
        } elseif (is_dir($this->getRootDirectory() . '/themes/' . $this->aConfig['theme'])) {
            $sThemeDir = 'themes/' . $this->aConfig['theme'] . '/';
        } else {
            throw new \Exception('Could not find theme directory "' . $this->aConfig['theme'] . '"');
        }

        $this->aAssets = [
            'css' => [
                $this->getAssetPath('table.css', $sThemeDir),
                $this->getAssetPath('thumbnails.css', $sThemeDir),
            ],
            'js' => [
                'vendor/bower-asset/jquery/dist/jquery.js',
                $this->getAssetPath('functions.js', $sThemeDir),
            ],
        ];

        if ($this->bUseBootstrap === false || ($this->bUseBootstrap === true && $this->aConfig['theme'] !== 'default')) {
            array_unshift(
                $this->aAssets['css'],
                $this->getAssetPath('bootstrap.css', $sThemeDir)
            );
        }

        if ($this->bUseBootstrap === true) {
            array_unshift(
                $this->aAssets['css'],
                'vendor/bower-asset/bootstrap/dist/css/bootstrap.min.css',
                'vendor/bower-asset/bootstrap/dist/css/bootstrap-theme.min.css'
            );
        }

        $this->aAssets = array_merge_recursive($this->aAssets,
            $this->aConfig['assets']);

        if (isset($_SERVER['WEB_ROOT'])) {
            $sRoot = $_SERVER['WEB_ROOT'];
        } elseif (is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $sRoot = $_SERVER['DOCUMENT_ROOT'];
        } else {
            $sRoot = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
        }

        $sCurrentWebDir = $_SERVER['REQUEST_URI'];
        $sCurrentRealDir = urldecode($sRoot . $sCurrentWebDir);

        if (strpos($sCurrentRealDir, '?') !== false) {
            $sCurrentRealDir = substr($sCurrentRealDir, 0,
                strpos($sCurrentRealDir, '?'));
        }#if

        foreach ($this->aConfig['readmePrefixes'] as $t_sPrefix) {
            foreach ($this->aConfig['readmeExtensions'] as $t_sExtension) {
                $sReadMeFileName = $t_sPrefix . $t_sExtension;
                $sReadMeFilePath = $sCurrentRealDir . urldecode($sReadMeFileName);

                if (file_exists($sReadMeFilePath)) {
                    $sReadmeContent = file_get_contents($sReadMeFilePath);
                    if ($t_sExtension === '.md') {
                        $converter = new CommonMarkConverter();
                        $this->sReadmeHtml = $converter->convertToHtml($sReadmeContent);
                    } elseif ($t_sExtension === '.txt') {
                        $this->sReadmeHtml = '<div style="white-space: pre-wrap;">' . $sReadmeContent . '</div>';
                    } else {
                        $this->sReadmeHtml = $sReadmeContent;
                    }#if

                    break;
                }#if
            }#foreach
        }#foreach

        /* Sort out extension filter and thumbnail for images/pdf/etc. */
        $aExtensions = array();
        $aImages = array();
        foreach (scandir($sCurrentRealDir) as $t_sFileName) {
            if (!is_dir($sCurrentRealDir . $t_sFileName)
                AND strrpos($t_sFileName, '.') !== false
            ) {
                $sExtension = substr($t_sFileName, strrpos($t_sFileName, '.'));
                $sExtension = strtolower($sExtension);

                $aExtensions[$sExtension] = substr($sExtension, 1);

                if (in_array(
                    $sExtension
                    , array(
                        '.bmp'
                    ,
                        '.eps'
                    ,
                        '.gif'
                    ,
                        '.ico'
                    ,
                        '.jpg'
                    ,
                        '.png'
                    ,
                        '.ps'
                    ,
                        '.pdf'
                    ,
                        '.psd'
                    ,
                        '.svg'
                    ,
                        '.tiff'
                    )
                )
                ) {
                    $aImages[$sCurrentWebDir . $t_sFileName] = substr($sExtension,
                        1);
                }#if
            }#if
        }#foreach
        natcasesort($aExtensions);

        if (!empty($aImages)) {
            $this->sThumbnailHtml .= '<ul class="thumbnails polaroids">';
            foreach ($aImages as $t_sImage => $t_sExtension) {
                $this->sThumbnailHtml .= '<li class="' . $t_sExtension . '"><a href="' . $t_sImage . '" title="' . basename($t_sImage) . '"><img src="/Directory_Listing_Theme/thumbnail.php?file=' . urlencode($t_sImage) . '" /></a>';
            }#foreach
            $this->sThumbnailHtml .= '</ul>';
        }#if

        /******************************************************************************/
        $this->sReadme = '';
        $this->aConfig['readmeExtensions'] = array('.html', '.md', '.txt');
        foreach ($this->aConfig['readmeExtensions'] as $t_sExtension) {
            $sReadMeFileName = 'readme-footer' . $t_sExtension;
            $sReadMeFilePath = urldecode($_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'] . $sReadMeFileName);

            if (file_exists($sReadMeFilePath)) {
                $sReadmeContent = file_get_contents($sReadMeFilePath);
                if ($t_sExtension === '.md') {
                    $converter = new CommonMarkConverter();
                    $sReadmeContent = $converter->convertToHtml($sReadmeContent);
                } elseif ($t_sExtension === '.txt') {
                    $sReadmeContent = '<pre>' . $sReadmeContent . '</pre>';
                }
                $this->sReadme = '<div class="page">' . $sReadmeContent . '</div>';
                break;
            }#if
        }#foreach
    }

    private function getAssetPath($p_sFile, $sThemeDir)
    {

        $aParts = explode('.', $p_sFile);
        array_splice($aParts, -1, 0, array('min'));

        if (is_file($this->getRootDirectory() . '/' . $sThemeDir . $p_sFile)) {
            $sPath = $sThemeDir . $p_sFile;
        } elseif (is_file($this->getRootDirectory() . '/' . $sThemeDir . implode('.', $aParts))) {
            $sPath = $sThemeDir . implode('.', $aParts);
        } elseif (is_file($this->getRootDirectory() . '/' . '/themes/default/' . $p_sFile)) {
            $sPath = '/themes/default/' . $p_sFile;
        } else {
            throw new \Exception('Could not find asset "' . $p_sFile . '"');
        }

        return $sPath;
    }

    private function getRootDirectory()
    {
        return realpath(__DIR__ . '/../');
    }

    /**
     * @return string
     */
    private function buildBreadcrumbHtml()
    {
        /* Set Title */
        $sUrl = urldecode($_SERVER['REQUEST_URI']);
        if (strpos($sUrl, '?') !== false) {
            $sUrl = substr($sUrl, 0, strpos($sUrl, '?'));
        }#if

        $sIndexHtml = '<li><a href="http://' . $_SERVER['SERVER_NAME'] . '">' . $_SERVER['SERVER_NAME'] . '</a></li>';

        if ($_SERVER['REQUEST_URI'] !== '/') {
            $aParts = explode('/', trim($sUrl, '/'));
            $iCount = count($aParts) - 1;
            $sUrl = 'http://' . $_SERVER['SERVER_NAME'];

            foreach ($aParts as $t_iIndex => $t_sPart) {
                if (!empty($t_sPart)) {

                    $sUrl .= '/' . urlencode($t_sPart);
                    $sIndexHtml .= '<li><a';
                    if ($t_iIndex === $iCount) {
                        $sIndexHtml .= ' class="active"';
                    } else {
                        $sIndexHtml .= ' class="text-muted"';
                    }
                    $sIndexHtml .= ' href="' . $sUrl . '">' . $t_sPart . '</a></li>';
                }#if
            }#foreach
        }#if

        return $sIndexHtml;
    }

    /**
     * @return string
     */
    private function buildTitle()
    {
        $sUrl = urldecode($_SERVER['REQUEST_URI']);
        if (strpos($sUrl, '?') !== false) {
            $sUrl = substr($sUrl, 0, strpos($sUrl, '?'));
        }#if

        return $sUrl;
    }

}
/*EOF*/
