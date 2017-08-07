<?php

/**
 * @file pages/information/InformationHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InformationHandler
 * @ingroup pages_information
 *
 * @brief Display journal information.
 */

import('classes.handler.Handler');

class InformationHandler extends Handler {
	/**
	 * Constructor
	 **/
	function __construct() {
		parent::__construct();
	}

	/**
	 * Display the information page for the journal.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$journal = $request->getJournal();
		if (!$journal) $request->redirect('index');

		$this->validate();
		$this->setupTemplate($request, $journal);

		switch(array_shift($args)) {
			case 'readers':
				$content = $journal->getLocalizedSetting('readerInformation');
				$pageTitle = 'navigation.infoForReaders.long';
				break;
			case 'authors':
				$content = $journal->getLocalizedSetting('authorInformation');
				$pageTitle = 'navigation.infoForAuthors.long';
				break;
			case 'librarians':
				$content = $journal->getLocalizedSetting('librarianInformation');
				$pageTitle = 'navigation.infoForLibrarians.long';
				break;
			case 'competingInterestGuidelines':
				$content = $journal->getLocalizedSetting('competingInterestsPolicy');
				$pageTitle = 'navigation.competingInterestGuidelines';
				break;
			case 'sampleCopyrightWording':
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
				$content = __('manager.setup.authorCopyrightNotice.sample');
				$pageTitle = 'manager.setup.copyrightNotice';
				break;
			default:
				return $request->redirect($journal->getPath());
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pageTitle', $pageTitle);
		$templateMgr->assign('content', $content);
		$templateMgr->display('frontend/pages/information.tpl');
	}

	function readers($args, $request) {
		$this->index(array('readers'), $request);
	}

	function authors($args, $request) {
		$this->index(array('authors'), $request);
	}

	function librarians($args, $request) {
		$this->index(array('librarians'), $request);
	}

	function competingInterestGuidelines($args, $request) {
		$this->index(array('competingInterestGuidelines'), $request);
	}

	function sampleCopyrightWording($args, $request) {
		$this->index(array('sampleCopyrightWording'), $request);
	}

	/**
	 * Initialize the template.
	 * @param $request PKPRequest
	 * @param $journal Journal
	 */
	function setupTemplate($request, $journal) {
		parent::setupTemplate($request);
		if (!$journal->getSetting('restrictSiteAccess')) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		}
	}
}

?>
