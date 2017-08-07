<?php

/**
 * @file pages/gateway/GatewayHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GatewayHandler
 * @ingroup pages_gateway
 *
 * @brief Handle external gateway requests. 
 */

import('classes.handler.Handler');

class GatewayHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	function index($args, $request) {
		$request->redirect(null, 'index');
	}

	function lockss($args, $request) {
		$this->validate();
		$this->setupTemplate();

		$journal = $request->getJournal();
		$templateMgr = TemplateManager::getManager($request);

		if ($journal != null) {
			if (!$journal->getSetting('enableLockss')) {
				$request->redirect(null, 'index');
			}

			$year = $request->getUserVar('year');

			$issueDao = DAORegistry::getDAO('IssueDAO');

			// FIXME Should probably go in IssueDAO or a subclass
			if (isset($year)) {
				$year = (int)$year;
				$result = $issueDao->retrieve(
					'SELECT * FROM issues WHERE journal_id = ? AND year = ? AND published = 1 ORDER BY current DESC, year ASC, volume ASC, number ASC',
					array($journal->getId(), $year)
				);
				if ($result->RecordCount() == 0) {
					unset($year);
				}
			}

			if (!isset($year)) {
				$result = $issueDao->retrieve(
					'SELECT MAX(year) FROM issues WHERE journal_id = ? AND published = 1',
					$journal->getId()
				);
				list($year) = $result->fields;
				$result = $issueDao->retrieve(
					'SELECT * FROM issues WHERE journal_id = ? AND year = ? AND published = 1 ORDER BY current DESC, year ASC, volume ASC, number ASC',
					array($journal->getId(), $year)
				);
				$issues = new DAOResultFactory($result, $issueDao, '_returnIssueFromRow');
				$templateMgr->assign('issues', $issues);
				$templateMgr->assign('showInfo', true);
			}

			$prevYear = null;
			$nextYear = null;
			if (isset($year)) {
				$result = $issueDao->retrieve(
					'SELECT MAX(year) FROM issues WHERE journal_id = ? AND published = 1 AND year < ?',
					array($journal->getId(), $year)
				);
				list($prevYear) = $result->fields;

				$result = $issueDao->retrieve(
					'SELECT MIN(year) FROM issues WHERE journal_id = ? AND published = 1 AND year > ?',
					array($journal->getId(), $year)
				);
				list($nextYear) = $result->fields;
			}

			$templateMgr->assign('journal', $journal);
			$templateMgr->assign('year', $year);
			$templateMgr->assign('prevYear', $prevYear);
			$templateMgr->assign('nextYear', $nextYear);

			$locales = $journal->getSupportedLocaleNames();
			if (!isset($locales) || empty($locales)) {
				$localeNames = AppLocale::getAllLocales();
				$primaryLocale = AppLocale::getPrimaryLocale();
				$locales = array($primaryLocale => $localeNames[$primaryLocale]);
			}
			$templateMgr->assign('locales', $locales);

		} else {
			$journalDao = DAORegistry::getDAO('JournalDAO');
			$journals = $journalDao->getAll(true);
			$templateMgr->assign('journals', $journals);
		}

		$templateMgr->display('gateway/lockss.tpl');
	}

	function clockss($args, $request) {
		$this->validate();
		$this->setupTemplate();

		$journal = $request->getJournal();
		$templateMgr = TemplateManager::getManager($request);

		if ($journal != null) {
			if (!$journal->getSetting('enableClockss')) {
				$request->redirect(null, 'index');
			}

			$year = $request->getUserVar('year');

			$issueDao = DAORegistry::getDAO('IssueDAO');

			// FIXME Should probably go in IssueDAO or a subclass
			if (isset($year)) {
				$year = (int)$year;
				$result = $issueDao->retrieve(
					'SELECT * FROM issues WHERE journal_id = ? AND year = ? AND published = 1 ORDER BY current DESC, year ASC, volume ASC, number ASC',
					array($journal->getId(), $year)
				);
				if ($result->RecordCount() == 0) {
					unset($year);
				}
			}

			if (!isset($year)) {
				$result = $issueDao->retrieve(
					'SELECT MAX(year) FROM issues WHERE journal_id = ? AND published = 1',
					$journal->getId()
				);
				list($year) = $result->fields;
				$result = $issueDao->retrieve(
					'SELECT * FROM issues WHERE journal_id = ? AND year = ? AND published = 1 ORDER BY current DESC, year ASC, volume ASC, number ASC',
					array($journal->getId(), $year)
				);
				$issues = new DAOResultFactory($result, $issueDao, '_returnIssueFromRow');
				$templateMgr->assign('issues', $issues);
				$templateMgr->assign('showInfo', true);
			}

			$prevYear = null;
			$nextYear = null;
			if (isset($year)) {
				$result = $issueDao->retrieve(
					'SELECT MAX(year) FROM issues WHERE journal_id = ? AND published = 1 AND year < ?',
					array($journal->getId(), $year)
				);
				list($prevYear) = $result->fields;

				$result = $issueDao->retrieve(
					'SELECT MIN(year) FROM issues WHERE journal_id = ? AND published = 1 AND year > ?',
					array($journal->getId(), $year)
				);
				list($nextYear) = $result->fields;
			}

			$templateMgr->assign('journal', $journal);
			$templateMgr->assign('year', $year);
			$templateMgr->assign('prevYear', $prevYear);
			$templateMgr->assign('nextYear', $nextYear);

			$locales =& $journal->getSupportedLocaleNames();
			if (!isset($locales) || empty($locales)) {
				$localeNames =& AppLocale::getAllLocales();
				$primaryLocale = AppLocale::getPrimaryLocale();
				$locales = array($primaryLocale => $localeNames[$primaryLocale]);
			}
			$templateMgr->assign('locales', $locales);

		} else {
			$journalDao = DAORegistry::getDAO('JournalDAO');
			$journals = $journalDao->getAll(true);
			$templateMgr->assign('journals', $journals);
		}

		$templateMgr->display('gateway/clockss.tpl');
	}

	/**
	 * Handle requests for gateway plugins.
	 */
	function plugin($args, $request) {
		$this->validate();
		$pluginName = array_shift($args);

		$plugins =& PluginRegistry::loadCategory('gateways');
		if (isset($pluginName) && isset($plugins[$pluginName])) {
			$plugin =& $plugins[$pluginName];
			if (!$plugin->fetch($args, $request)) {
				$request->redirect(null, 'index');
			}
		} else {
			$request->redirect(null, 'index');
		}
	}
}

?>
