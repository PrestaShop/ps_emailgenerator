<?php

class AdminEmailGeneratorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;

        $this->template = 'index.tpl';

        if ($action = Tools::getValue('action')) {
            $action = basename($action);
        } else {
            $action = 'index';
        }

        $this->action = $action;
        $this->template = $action.'.tpl';

        parent::__construct();
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminHome'));
        }

        $this->context->smarty->assign('emailgenerator', $this->context->link->getAdminLink('AdminEmailGenerator'));
    }

    public function setMedia()
    {
        $this->addCSS(_PS_MODULE_DIR_.'ps_emailgenerator/css/emailgenerator.css');
        parent::setMedia();
    }

    public function processIndex()
    {
        $this->addJS(_PS_MODULE_DIR_.'ps_emailgenerator/js/tree.js');

        $templates = Ps_EmailGenerator::listEmailTemplates();

        $params = array(
            'templates' => $templates,
            'toBuild' => Tools::jsonEncode($this->module->getTemplatesToBuild())
        );
        $this->context->smarty->assign($params);
    }

    public function processPreview()
    {
        $template = Tools::getValue('template');
        $languageCode = Tools::getValue('languageCode');
        $generated = $this->module->generateEmail($template, $languageCode);

        die($generated['html']);
    }

    public function ajaxProcessGenerateEmail()
    {
        $res = array('success' => false, 'error_message' => 'Something wrong happened, sorry!');

        $languageCode = Tools::getValue('languageCode');
        $template = Tools::getValue('template');

        try {
            $this->module->generateEmail($template, $languageCode);
            $res['success'] = true;
            unset($res['error_message']);
        } catch (Exception $e) {
            $res['error_message'] = $e->getMessage();
        }

        die(Tools::jsonEncode($res));
    }
}
