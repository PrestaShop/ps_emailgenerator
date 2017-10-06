<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Translation\TranslatorComponent as Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Finder\Finder;

require_once dirname(__FILE__).'/vendor/cssin/cssin.php';
require_once dirname(__FILE__).'/vendor/cssin/vendor/simple_html_dom/simple_html_dom.php';
require_once dirname(__FILE__).'/vendor/html_to_text/Html2Text.php';

global $LOCALE;
$LOCALE = 'en-US';
// Function to put the translations in the templates
function t($str)
{
    global $LOCALE;

    return Ps_EmailGenerator::$translator->trans($str, array(), 'Emails.Body', $LOCALE);
}

class Ps_EmailGenerator extends Module
{
    protected static $_rtl_langs = array('fa', 'ar', 'he', 'ur', 'ug', 'ku');
    protected static $_lang_default_font = array(
        'fa' => 'Tahoma',
        'ar' => 'Tahoma'
    );

    public static $translator = null;

    public function __construct()
    {
        $this->name = 'ps_emailgenerator';
        $this->version = '1.0';
        $this->author = 'PrestaShop';
        $this->bootstrap = true;

        $this->displayName = 'Email Generator';
        $this->description = 'Generate HTML and TXT emails for PrestaShop from php templates.';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        parent::__construct();

        self::$translator = $this->getTranslator();
    }

    public function install()
    {
        return parent::install() && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab() && parent::uninstall();
    }

    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = "AdminEmailGenerator";
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = "AdminEmailGenerator";
        }
        $tab->id_parent = -1;
        $tab->module = $this->name;
        return $tab->add();
    }

    public function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminEmailGenerator');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        } else {
            return false;
        }
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminEmailGenerator'));
    }

    public static function humanizeString($str)
    {
        return implode(' ', array_map('ucfirst',  preg_split('/[_\-]/', $str)));
    }

    public static function relativePath($path)
    {
        return substr($path, strlen(dirname(__FILE__))+1);
    }

    public static function listEmailTemplates()
    {
        static $templates = null;

        if ($templates !== null) {
            return $templates;
        }

        $templates = array('core' => array(), 'modules' => array());

        if (is_dir(dirname(__FILE__).'/templates/core')) {
            foreach (scandir(dirname(__FILE__).'/templates/core') as $entry) {
                $path = dirname(__FILE__).'/templates/core/'.$entry;

                if (preg_match('/\.php$/', $entry)) {
                    $templates['core'][] = array(
                        'path' => self::relativePath($path),
                        'name' => self::humanizeString(basename($entry, '.php'))
                    );
                }
            }
        }

        if (is_dir(dirname(__FILE__).'/templates/modules')) {
            foreach (scandir(dirname(__FILE__).'/templates/modules') as $module) {
                $dir = dirname(__FILE__).'/templates/modules/'.$module;

                if (!preg_match('/^\./', $module) && is_dir($dir)) {
                    $templates['modules'][$module] = array();

                    foreach (scandir($dir) as $entry) {
                        $path = $dir.'/'.$entry;
                        if (preg_match('/\.php$/', $entry)) {
                            $templates['modules'][$module][] = array(
                                'path' => self::relativePath($path),
                                'name' => self::humanizeString(basename($entry, '.php'))
                            );
                        }
                    }
                }
            }
        }

        return $templates;
    }

    public function quote($str)
    {
        return '\''.str_replace("\n", '', preg_replace('/\\\*\'/', '\\\'', $str)).'\'';
    }

    public function postGenerateAction()
    {
        $templates = self::listEmailTemplates();

        foreach (Language::getLanguages() as $l) {
            $language = $l['iso_code'];

            foreach ($templates['core'] as $file) {
                $target_path = _PS_ROOT_DIR_.'/mails/'.$language.'/'.basename($file['path'], '.php');
                $this->generateEmail($file['path'], $target_path, $language);
            }
            foreach ($templates['modules'] as $module => $files) {
                foreach ($files as $file) {
                    $target_path = _PS_MODULE_DIR_.$module.'/mails/'.$language.'/'.basename($file['path'], '.php');
                    $this->generateEmail($file['path'], $target_path, $language);
                }
            }
        }

        $this->template = 'index';
        return $this->getIndexAction();
    }

    public function textify($html)
    {
        $html      = str_get_html($html);
        foreach ($html->find("[data-html-only='1'],html-only") as $kill) {
            $kill->outertext = "";
        }
        $converter = new Html2Text((string)$html);

        $converter->search[]  = "#</p>#";
        $converter->replace[] = "\n";

        $txt = $converter->get_text();

        $txt = preg_replace('/^\s+/m', "\n", $txt);
        $txt = preg_replace_callback('/\{\w+\}/', function ($m) {
                return strtolower($m[0]);
        }, $txt);

        // Html2Text will treat links as relative to the current host. We don't want that!
        // (because of links like <a href='{shop_url}'></a>)
        if (!empty($_SERVER['HTTP_HOST'])) {
            $txt = preg_replace('#\w+://'.preg_quote($_SERVER['HTTP_HOST']).'/?#i', '', $txt);
        }

        return $txt;
    }

    public function getCSS($url)
    {
        $webRoot = Tools::getShopDomain(true).__PS_BASE_URI__;
        if (strpos($url, $webRoot) === 0) {
            $path = _PS_ROOT_DIR_.'/'.substr($url, strlen($webRoot));
            if (!file_exists($path)) {
                throw new Exception('Could not find CSS file: '.$path);
            }

            return file_get_contents($path);
        } else {
            throw Exception('Dont\'t know how to get CSS: '.$url);
        }
    }

    public function generateEmail($template, $locale)
    {
        if (!preg_match('#^templates/(core/[^/]+|modules/[^\./]+/[^/]+)$#', $template)) {
            throw new Exception('NAH, wrong template name.');
        }

        @ini_set('display_errors', 'on');
        static $cssin;

        if (!$cssin) {
            $cssin = new CSSIN();

            $cssin->setCSSGetter(array($this, 'getCSS'));
        }

        global $LOCALE;
        $LOCALE = $locale;
        $iso = preg_replace('/-.*/','',$locale);

        $emailPublicWebRoot = Tools::getShopDomain(true).__PS_BASE_URI__.'modules/ps_emailgenerator/templates/';
        $emailLangIsRTL = in_array($iso, self::$_rtl_langs); // see header.php
        $emailDefaultFont = '';
        if (array_key_exists($iso, self::$_lang_default_font)) {
            $emailDefaultFont = (self::$_lang_default_font[$iso]).',';
        }

        if (dirname($template) !== 'templates/core') {
            set_include_path(dirname(__FILE__).'/templates/core:'.get_include_path());
        }

        ob_start();

        if (!empty($_GET['cheat_logo'])) {
            echo "<style>img[src='{shop_logo}']{content: url('{$emailPublicWebRoot}logo.jpg');}</style>";
        }

        include dirname(__FILE__).'/'.$template;
        $raw_html = ob_get_clean();

        $output_basename = $this->getBaseOutputName($template, $locale);
        if ($output_basename === false) {
            throw new Exception('Template name is invalid.');
        }

        $html_for_html = str_get_html($raw_html, true, true, DEFAULT_TARGET_CHARSET, false, DEFAULT_BR_TEXT, DEFAULT_SPAN_TEXT);
        foreach ($html_for_html->find("[data-text-only='1']") as $kill) {
            $kill->outertext = "";
        }
        foreach ($html_for_html->find("html-only") as $node) {
            $node->outertext = $node->innertext;
        }

        $html_for_html = (string)$html_for_html;

        $html = $cssin->inlineCSS(null, $html_for_html);
        $text = $this->textify($raw_html);

        $write = array(
            $output_basename.'.txt' => $text,
            $output_basename.'.html' => $html
        );

        foreach ($write as $path => $data) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0777, true)) {
                    throw new Exception('Could not create directory to write email to.');
                }
            }
            if (!@file_put_contents($path, $data)) {
                throw new Exception('Could not write email file: '.$path);
            }
        }
        return array('html' => $html, 'text' => $text);
    }

    public function generateAllEmail($locale = null)
    {
        $errors = array();

        foreach ($this->getTemplatesToBuild($locale) as $tplToBuild) {
            try {
                $this->generateEmail($tplToBuild['template'], $tplToBuild['languageCode']);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return empty($errors) ? true : $errors;
    }

    public static function unquote($string)
    {
        return preg_replace(array('/(?:^[\'"]|[\'"]$)/', '/\\\+([\'"])/'), array('', '\1'), $string);
    }

    public static function recListPSPHPFiles($dir)
    {
        foreach (array(_PS_CACHE_DIR_, _PS_TOOL_DIR_) as $skip) {
            if (preg_replace('#/$#', '', $skip) === preg_replace('#/$#', '', $dir)) {
                return array();
            }
        }

        $paths = array();

        if (is_dir($dir)) {
            foreach (scandir($dir) as $entry) {
                if (!preg_match('/^\./', basename($entry))) {
                    $path = $dir.'/'.$entry;
                    if (is_dir($path)) {
                        $paths = array_merge($paths, self::recListPSPHPFiles($path));
                    } elseif (preg_match('/\.php$/', $entry)) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }

    public function isValidTemplatePath($template)
    {
        return preg_match('#^templates/(?:core|modules/[^/]+)/[^/]+\.php$#', $template)
            && file_exists(dirname(__FILE__).'/'.$template);
    }

    public function getBaseOutputName($template, $languageCode)
    {
        $m = array();
        $baseDir = _PS_MODULE_DIR_.'ps_emailgenerator/dumps/'.$languageCode.'/';
        if (preg_match('#^templates/core/[^/]+\.php$#', $template)) {
            return $baseDir.'core/'.basename($template, '.php');
        } elseif (preg_match('#^templates/modules/([^/]+)/(?:[^/]+)\.php$#', $template, $m)) {
            return $baseDir.'modules/'.$m[1].'/'.basename($template, '.php');
        } else {
            return false;
        }
    }

    public function isValidTranslationFilePath($path)
    {
        $absPath = _PS_ROOT_DIR_.'/'.$path;
        $path = substr($absPath, strlen(_PS_ROOT_DIR_)+1);
        return
            preg_match('#^(?:mails/[a-z]{2}/lang\.php|modules/ps_emailgenerator/templates_translations/[a-z]{2}/lang_content\.php)$#', $path)
            ? $absPath
            : false;
    }

    public function getLocalesToTranslateTo($locale)
    {
        $languages = array();

        if (!is_null($locale)) {
            $languages[] = array('locale' => $locale);
        } else {
            $path = _PS_ROOT_DIR_.'/app/Resources/translations/';
            foreach (scandir($path) as $lc) {
                if (!preg_match('/^(\.|default)/', $lc) && is_dir($path.$lc)) {
                    $languages[] = array('locale' => $lc);
                }
            }
        }

        return $languages;
    }

    public function getTemplatesToBuild($locale)
    {
        $templates = Ps_EmailGenerator::listEmailTemplates();
        $toBuild = array();

        foreach ($this->getLocalesToTranslateTo($locale) as $lang) {
            foreach ($templates['core'] as $tpl) {
                if (!preg_match('/^header/', basename($tpl['path'])) && !preg_match('/^footer/', basename($tpl['path']))) {
                    $toBuild[] = array(
                        'languageCode' => $lang['locale'],
                        'template' => $tpl['path']
                    );
                }
            }
            foreach ($templates['modules'] as $mod) {
                foreach ($mod as $tpl) {
                    $toBuild[] = array(
                        'languageCode' => $lang['locale'],
                        'template' => $tpl['path']
                    );
                }
            }
        }

        return $toBuild;
    }

    public function getTranslator()
    {
        global $LOCALE;

        $translator = new Translator($LOCALE, null, _PS_CACHE_DIR_, false);
        $translator->addLoader('xlf', new XliffFileLoader);

        $locations = array(_PS_ROOT_DIR_.'/app/Resources/translations');

        $finder = Finder::create()
            ->files()
            ->filter(function (\SplFileInfo $file) {
                return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
            })
            ->in($locations)
        ;

        foreach ($finder as $file) {
            list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);

            $translator->addResource($format, $file, $locale, $domain);
        }

        return $translator;
    }
}
