<?php

namespace APP\plugins\generic\codecheck\controllers\page;

use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;
use PKP\controllers\page\PageHandler;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;

class CodecheckPageHandler extends \APP\handler\Handler
{
    protected $plugin;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Handle index request (redirect to "view")
     *
     * @param array $args Arguments array.
     * @param PKPRequest $request Request object.
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'view', $args);
    }

    /**
     * Handle view page request (redirect to "view")
     *
     * @param array $args Arguments array.
     * @param PKPRequest $request Request object.
     */
    public function view($args, $request)
    {
        $path = array_shift($args);
        $context = $request->getContext();

        // Assign the template vars needed and display
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign('title', __('plugins.generic.codecheck.infoPage.title'));
        $templateMgr->display($this->plugin->getTemplateResource('pages/codecheck-info.tpl'));
    }
}
