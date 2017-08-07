<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

import('classes.handler.Handler');

class IndexHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * If no journal is selected, display list of journals.
	 * Otherwise, display the index page for the selected journal.
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, $request) {
		$this->validate(null, $request);
		$journal = $request->getJournal();

		if (!$journal) {
			$journal = $this->getTargetContext($request, $journalsCount);
			if ($journal) {
				// There's a target context but no journal in the current request. Redirect.
				$request->redirect($journal->getPath());
			}
			if ($journalsCount === 0 && Validation::isSiteAdmin()) {
				// No contexts created, and this is the admin.
				$request->redirect(null, 'admin', 'contexts');
			}
		}

		$this->setupTemplate($request);
		$router = $request->getRouter();
		$templateMgr = TemplateManager::getManager($request);
		if ($journal) {
			// Assign header and content for home page
			$templateMgr->assign(array(
				'additionalHomeContent' => $journal->getLocalizedSetting('additionalHomeContent'),
				'homepageImage' => $journal->getLocalizedSetting('homepageImage'),
				'homepageImageAltText' => $journal->getLocalizedSetting('homepageImageAltText'),
				'journalDescription' => $journal->getLocalizedSetting('description'),
			));

			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getCurrent($journal->getId(), true);
			if (isset($issue) && $journal->getSetting('publishingMode') != PUBLISHING_MODE_NONE) {
				import('pages.issue.IssueHandler');
				// The current issue TOC/cover page should be displayed below the custom home page.
				IssueHandler::_setupIssueTemplate($request, $issue);
			}

			$enableAnnouncements = $journal->getSetting('enableAnnouncements');
			if ($enableAnnouncements) {
				$enableAnnouncementsHomepage = $journal->getSetting('enableAnnouncementsHomepage');
				if ($enableAnnouncementsHomepage) {
					$numAnnouncementsHomepage = $journal->getSetting('numAnnouncementsHomepage');
					$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
					$announcements =& $announcementDao->getNumAnnouncementsNotExpiredByAssocId(ASSOC_TYPE_JOURNAL, $journal->getId(), $numAnnouncementsHomepage);
					$templateMgr->assign('announcements', $announcements->toArray());
					$templateMgr->assign('enableAnnouncementsHomepage', $enableAnnouncementsHomepage);
					$templateMgr->assign('numAnnouncementsHomepage', $numAnnouncementsHomepage);
				}
			}

			$templateMgr->display('frontend/pages/indexJournal.tpl');
		} else {
			$journalDao = DAORegistry::getDAO('JournalDAO');
			$site = $request->getSite();

			if ($site->getRedirect() && ($journal = $journalDao->getById($site->getRedirect())) != null) {
				$request->redirect($journal->getPath());
			}

			$templateMgr->assign('about', $site->getLocalizedAbout());
			$templateMgr->assign('journalFilesPath', $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/journals/');

			// If we're using paging, fetch the parameters
			$usePaging = $site->getSetting('usePaging');
			if ($usePaging) $rangeInfo = $this->getRangeInfo($request, 'journals');
			else $rangeInfo = null;
			$templateMgr->assign('usePaging', $usePaging);

			$journals = $journalDao->getAll(true, $rangeInfo);
			$templateMgr->assign('journals', $journals);
			$templateMgr->assign('site', $site);

			$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
			$templateMgr->display('frontend/pages/indexSite.tpl');
		}
	}
}

?>
