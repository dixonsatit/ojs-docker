<?php

/**
 * @file controllers/tab/issueEntry/form/IssueEntryPublicationMetadataForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IssueEntryPublicationMetadataForm
 * @ingroup controllers_tab_issueEntry_form_IssueEntryPublicationMetadataForm
 *
 * @brief Displays a submission's publication metadata entry form.
 */

import('lib.pkp.classes.form.Form');

class IssueEntryPublicationMetadataForm extends Form {

	/** @var Submission The submission used to show metadata information */
	var $_submission;

	/** @var PublishedArticle The published article associated with this submission */
	var $_publishedArticle;

	/** @var int The current stage id */
	var $_stageId;

	/** @var int The current user ID */
	var $_userId;

	/**
	 * Parameters to configure the form template.
	 */
	var $_formParams;

	/**
	 * Constructor.
	 * @param $submissionId integer
	 * @param $userId integer
	 * @param $stageId integer
	 * @param $formParams array
	 */
	function __construct($submissionId, $userId, $stageId = null, $formParams = null) {
		parent::__construct('controllers/tab/issueEntry/form/publicationMetadataFormFields.tpl');
		$submissionDao = Application::getSubmissionDAO();
		$this->_submission = $submissionDao->getById($submissionId);

		$this->_stageId = $stageId;
		$this->_formParams = $formParams;
		$this->_userId = $userId;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		$this->addCheck(new FormValidatorURL($this, 'licenseURL', 'optional', 'form.url.invalid'));
	}

	/**
	 * Fetch the HTML contents of the form.
	 * @param $request PKPRequest
	 * return string
	 */
	function fetch($request) {

		$context = $request->getContext();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'submissionId' => $this->getSubmission()->getId(),
			'stageId' => $this->getStageId(),
			'formParams' => $this->getFormParams(),
			'context' => $context,
		));

		$journalSettingsDao = DAORegistry::getDAO('JournalSettingsDAO');
		$templateMgr->assign('issueOptions', $this->getIssueOptions($context));

		$publishedArticle = $this->getPublishedArticle();
		if ($publishedArticle) {
			$templateMgr->assign('publishedArticle', $publishedArticle);
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getById($publishedArticle->getIssueId());
			if ($issue) {
				$templateMgr->assign('issueAccess', $issue->getAccessStatus());
				$templateMgr->assign('accessOptions', array(
					ARTICLE_ACCESS_ISSUE_DEFAULT => __('editor.issues.default'),
					ARTICLE_ACCESS_OPEN => __('editor.issues.open')
				));
			}
		}

		// include payment information
		// Set up required Payment Related Information
		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$completedPaymentDao = DAORegistry::getDAO('OJSCompletedPaymentDAO');
		$publicationFeeEnabled = $paymentManager->publicationEnabled();
		$templateMgr->assign('publicationFeeEnabled',  $publicationFeeEnabled);
		if ($publicationFeeEnabled) {
			$templateMgr->assign('publicationPayment', $completedPaymentDao->getPublicationCompletedPayment($context->getId(), $this->getSubmission()->getId()));
		}

		$templateMgr->assign('submission', $this->getSubmission());

		return parent::fetch($request);
	}

	/**
	 * builds the issue options pulldown for published and unpublished issues
	 * @param $journal Journal
	 * @return array Associative list of options for pulldown
	 */
	function getIssueOptions($journal) {
		$issueOptions = array();
		$journalId = $journal->getId();

		$issueDao = DAORegistry::getDAO('IssueDAO');

		$issueOptions['future'] =  '------    ' . __('editor.issues.futureIssues') . '    ------';
		$issueIterator = $issueDao->getUnpublishedIssues($journalId);
		while ($issue = $issueIterator->next()) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
		}
		$issueOptions['current'] = '------    ' . __('editor.issues.currentIssue') . '    ------';
		$issuesIterator = $issueDao->getPublishedIssues($journalId);
		$issues = $issuesIterator->toArray();
		if (isset($issues[0]) && $issues[0]->getCurrent()) {
			$issueOptions[$issues[0]->getId()] = $issues[0]->getIssueIdentification();
			array_shift($issues);
		}
		$issueOptions['back'] = '------    ' . __('editor.issues.backIssues') . '    ------';
		foreach ($issues as $issue) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
		}

		return $issueOptions;
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_EDITOR
		);

		$submission = $this->getSubmission();
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$this->_publishedArticle = $publishedArticleDao->getPublishedArticleByArticleId($submission->getId(), null, false);

		$copyrightHolder = $submission->getCopyrightHolder(null);
		$copyrightYear = $submission->getCopyrightYear();
		$licenseURL = $submission->getLicenseURL();

		$this->_data = array(
			'copyrightHolder' => $submission->getDefaultCopyrightHolder(null), // Localized
			'copyrightYear' => $submission->getDefaultCopyrightYear(),
			'licenseURL' => $submission->getDefaultLicenseURL(),
			'arePermissionsAttached' => !empty($copyrightHolder) || !empty($copyrightYear) || !empty($licenseURL),
		);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the Submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the PublishedArticle
	 * @return PublishedArticle
	 */
	function getPublishedArticle() {
		return $this->_publishedArticle;
	}

	/**
	 * Get the stage id
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the extra form parameters.
	 */
	function getFormParams() {
		return $this->_formParams;
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'waivePublicationFee', 'markAsPaid', 'issueId',
			'datePublished', 'accessStatus', 'pages',
			'copyrightYear', 'copyrightHolder',
			'licenseURL', 'attachPermissions',
		));
	}

	/**
	 * Save the metadata and store the catalog data for this published
	 * monograph.
	 */
	function execute($request) {
		parent::execute($request);

		$submission = $this->getSubmission();
		$context = $request->getContext();

		$waivePublicationFee = $request->getUserVar('waivePublicationFee') ? true : false;
		if ($waivePublicationFee) {

			$markAsPaid = $request->getUserVar('markAsPaid');
			import('classes.payment.ojs.OJSPaymentManager');
			$paymentManager = new OJSPaymentManager($request);

			$user = $request->getUser();

			// Get a list of author user IDs
			$authorUserIds = array();
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
			$submitterAssignment = $submitterAssignments->next();
			assert(isset($submitterAssignment)); // At least one author should be assigned

			$queuedPayment =& $paymentManager->createQueuedPayment(
				$context->getId(),
				PAYMENT_TYPE_PUBLICATION,
				$markAsPaid ? $submitterAssignment->getUserId() : $user->getId(),
				$submission->getId(),
				$markAsPaid ? $context->getSetting('publicationFee') : 0,
				$markAsPaid ? $context->getSetting('currency') : ''
			);

			$paymentManager->queuePayment($queuedPayment);

			// Since this is a waiver, fulfill the payment immediately
			$paymentManager->fulfillQueuedPayment($request, $queuedPayment, $markAsPaid?'ManualPayment':'Waiver');
		} else {
			// Get the issue for publication.
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issueId = $this->getData('issueId');
			$issue = $issueDao->getById($issueId, $context->getId());

			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
			$publishedArticle = $publishedArticleDao->getPublishedArticleByArticleId($submission->getId(), null, false); /* @var $publishedArticle PublishedArticle */

			if ($publishedArticle) {
				if (!$issue || !$issue->getPublished()) {
					$fromIssue = $issueDao->getById($publishedArticle->getIssueId(), $context->getId());
					if ($fromIssue->getPublished()) {
						// Insert article tombstone
						import('classes.article.ArticleTombstoneManager');
						$articleTombstoneManager = new ArticleTombstoneManager();
						$articleTombstoneManager->insertArticleTombstone($submission, $context);
					}
				}
			}

			import('classes.search.ArticleSearchIndex');
			$articleSearchIndex = new ArticleSearchIndex();

			// define the access status for the article if none is set.
			$accessStatus = $this->getData('accessStatus') != '' ? $this->getData('accessStatus') : ARTICLE_ACCESS_ISSUE_DEFAULT;

			$articleDao = DAORegistry::getDAO('ArticleDAO');
			if (!is_null($this->getData('pages'))) {
				$submission->setPages($this->getData('pages'));
			}

			if ($issue) {

				// Schedule against an issue.
				if ($publishedArticle) {
					$publishedArticle->setIssueId($issueId);
					$publishedArticle->setSequence(REALLY_BIG_NUMBER);
					$publishedArticle->setDatePublished($this->getData('datePublished'));
					$publishedArticle->setAccessStatus($accessStatus);
					$publishedArticleDao->updatePublishedArticle($publishedArticle);

					// Re-index the published article metadata.
					$articleSearchIndex->articleMetadataChanged($publishedArticle);
				} else {
					$publishedArticle = $publishedArticleDao->newDataObject();
					$publishedArticle->setId($submission->getId());
					$publishedArticle->setIssueId($issueId);
					$publishedArticle->setDatePublished(Core::getCurrentDate());
					$publishedArticle->setSequence(REALLY_BIG_NUMBER);
					$publishedArticle->setAccessStatus($accessStatus);

					$publishedArticleDao->insertObject($publishedArticle);

					// If we're using custom section ordering, and if this is the first
					// article published in a section, make sure we enter a custom ordering
					// for it. (Default at the end of the list.)
					if ($sectionDao->customSectionOrderingExists($issueId)) {
						if ($sectionDao->getCustomSectionOrder($issueId, $submission->getSectionId()) === null) {
							$sectionDao->insertCustomSectionOrder($issueId, $submission->getSectionId(), REALLY_BIG_NUMBER);
							$sectionDao->resequenceCustomSectionOrders($issueId);
						}
					}

					// Index the published article metadata and files for the first time.
					$articleSearchIndex->articleMetadataChanged($publishedArticle);
					$articleSearchIndex->submissionFilesChanged($publishedArticle);
				}

			} else {
				if ($publishedArticle) {
					// This was published elsewhere; make sure we don't
					// mess up sequencing information.
					$issueId = $publishedArticle->getIssueId();
					$publishedArticleDao->deletePublishedArticleByArticleId($submission->getId());

					// Delete the article from the search index.
					$articleSearchIndex->submissionFileDeleted($submission->getId());
				}
			}

			if ($this->getData('attachPermissions')) {
				$submission->setCopyrightYear($this->getData('copyrightYear'));
				$submission->setCopyrightHolder($this->getData('copyrightHolder'), null); // Localized
				$submission->setLicenseURL($this->getData('licenseURL'));
			} else {
				$submission->setCopyrightYear(null);
				$submission->setCopyrightHolder(null, null);
				$submission->setLicenseURL(null);
			}

			// Resequence the articles.
			$publishedArticleDao->resequencePublishedArticles($submission->getSectionId(), $issueId);

			$submission->stampStatusModified();

			if ($issue && $issue->getPublished()) {
				$submission->setStatus(STATUS_PUBLISHED);
				// delete article tombstone
				$tombstoneDao = DAORegistry::getDAO('DataObjectTombstoneDAO');
				$tombstoneDao->deleteByDataObjectId($submission->getId());
			} else {
				$submission->setStatus(STATUS_QUEUED);
			}

			$articleDao->updateObject($submission);
			$articleSearchIndex->articleChangesFinished();
		}
	}
}

?>
