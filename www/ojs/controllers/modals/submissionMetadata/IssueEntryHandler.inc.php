<?php

/**
 * @file controllers/modals/submissionMetadata/IssueEntryHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IssueEntryHandler
 * @ingroup controllers_modals_submissionMetadata
 *
 * @brief Handle the request to generate the tab structure on the New Catalog Entry page.
 */

// Import the base Handler.
import('lib.pkp.controllers.modals.submissionMetadata.PublicationEntryHandler');

class IssueEntryHandler extends PublicationEntryHandler {

	/** the selected galley id **/
	var $_selectedGalleyId;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Public handler methods
	//
	/**
	 * Display the tabs index page.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function fetch($args, $request) {
		parent::fetch($args, $request);

		$templateMgr = TemplateManager::getManager($request);

		$submission = $this->getSubmission();

		// load in any galley formats assigned to this published article
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$articleGalleys = $galleyDao->getBySubmissionId($submission->getId());

		$templateMgr->assign('galleys', $articleGalleys->toArray());

		$application = Application::getApplication();
		$request = $application->getRequest();
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$tabsUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'modals.submissionMetadata.IssueEntryHandler', 'fetchFormatInfo', null, array('submissionId' => $submission->getId(), 'stageId' => $this->getStageId()));
		$templateMgr->assign('tabsUrl', $tabsUrl);

		$tabContentUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'tab.issueEntry.IssueEntryTabHandler', 'galleyMetadata', null, array('submissionId' => $submission->getId(), 'stageId' => $this->getStageId()));
		$templateMgr->assign('tabContentUrl', $tabContentUrl);

		return $templateMgr->fetchJson('controllers/modals/submissionMetadata/issueEntryTabs.tpl');
	}

	/**
	 * Returns a JSON response containing information regarding the galley formats enabled
	 * for this submission.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function fetchFormatInfo($args, $request) {
		$submission = $this->getSubmission();
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$galleys = $galleyDao->getBySubmissionId($submission->getId());
		$formats = array();
		while ($galley = $galleys->next()) {
			$formats[$galley->getId()] = $galley->getLocalizedName();
		}
		$json = new JSONMessage(true, true);
		$json->setAdditionalAttributes(array('formats' => $formats));
		return $json;
	}
}

?>
