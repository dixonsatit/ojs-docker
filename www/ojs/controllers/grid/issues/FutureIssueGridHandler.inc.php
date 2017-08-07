<?php

/**
 * @file controllers/grid/issues/IssueGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IssueGridHandler
 * @ingroup controllers_grid_issues
 *
 * @brief Handle issues grid requests.
 */

import('classes.controllers.grid.issues.IssueGridHandler');

class FutureIssueGridHandler extends IssueGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request, $args) {
		// Basic grid configuration.
		$this->setTitle('editor.issues.futureIssues');

		parent::initialize($request, $args);

		// Add Create Issue action
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addIssue',
				new AjaxModal(
					$router->url($request, null, null, 'addIssue', null, array('gridId' => $this->getId())),
					__('grid.action.addIssue'),
					'modal_manage'
				),
				__('grid.action.addIssue'),
				'add_category'
			)
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		$journal = $request->getJournal();
		$issueDao = DAORegistry::getDAO('IssueDAO');
		return $issueDao->getUnpublishedIssues($journal->getId());
	}
}

?>
