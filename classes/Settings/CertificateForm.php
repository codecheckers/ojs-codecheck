<?php
/**
 * @file classes/Settings/CertificateForm.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CertificateForm
 * @brief Form for editors to add CODECHECK certificate information
 */

namespace APP\plugins\generic\codecheck\classes\Settings;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class CertificateForm extends Form
{
    /** @var CodecheckPlugin */
    public CodecheckPlugin $plugin;
    
    /** @var int */
    public int $submissionId;

    public function __construct(CodecheckPlugin &$plugin, int $submissionId)
    {
        $this->plugin = &$plugin;
        $this->submissionId = $submissionId;

        parent::__construct($this->plugin->getTemplateResource('certificateForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load existing certificate data
     */
    public function initData(): void
    {
        $dao = new CodecheckSubmissionDAO();
        $codecheckData = $dao->getBySubmissionId($this->submissionId);

        if ($codecheckData) {
            $this->setData('certificateDoi', $codecheckData->getCertificateDoi());
            $this->setData('certificateUrl', $codecheckData->getCertificateUrl());
            $this->setData('codecheckerNames', $codecheckData->getCodecheckerNames());
            $this->setData('checkStatus', $codecheckData->getCheckStatus());
            $this->setData('certificateDate', $codecheckData->getCertificateDate());
        }

        parent::initData();
    }

    /**
     * Load submitted form data
     */
    public function readInputData(): void
    {
        $this->readUserVars([
            'certificateDoi',
            'certificateUrl', 
            'codecheckerNames',
            'checkStatus',
            'certificateDate'
        ]);

        parent::readInputData();
    }

    /**
     * Fetch the form for display
     */
    public function fetch($request, $template = null, $display = false): ?string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', $this->submissionId);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the certificate data
     */
    public function execute(...$functionArgs): mixed
    {
        $dao = new CodecheckSubmissionDAO();
        
        // Get existing data to preserve other fields
        $existing = $dao->getBySubmissionId($this->submissionId);
        $data = [];
        
        if ($existing) {
            $data = [
                'opt_in' => $existing->getOptIn(),
                'code_repository' => $existing->getCodeRepository(),
                'data_repository' => $existing->getDataRepository(),
                'dependencies' => $existing->getDependencies(),
                'execution_instructions' => $existing->getExecutionInstructions(),
            ];
        }

        // Add certificate data
        $data['certificate_doi'] = $this->getData('certificateDoi');
        $data['certificate_url'] = $this->getData('certificateUrl');
        $data['codechecker_names'] = $this->getData('codecheckerNames');
        $data['check_status'] = $this->getData('checkStatus');
        $data['certificate_date'] = $this->getData('certificateDate');

        $dao->insertOrUpdate($this->submissionId, $data);

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => 'CODECHECK certificate information saved successfully']
        );

        return parent::execute();
    }
}