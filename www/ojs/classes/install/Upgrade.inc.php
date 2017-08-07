<?php

/**
 * @file classes/install/Upgrade.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Upgrade
 * @ingroup install
 *
 * @brief Perform system upgrade.
 */


import('lib.pkp.classes.install.Installer');

class Upgrade extends Installer {
	/**
	 * Constructor.
	 * @param $params array upgrade parameters
	 */
	function __construct($params, $installFile = 'upgrade.xml', $isPlugin = false) {
		parent::__construct($installFile, $params, $isPlugin);
	}


	/**
	 * Returns true iff this is an upgrade process.
	 * @return boolean
	 */
	function isUpgrade() {
		return true;
	}

	//
	// Upgrade actions
	//

	/**
	 * Rebuild the search index.
	 * @return boolean
	 */
	function rebuildSearchIndex() {
		import('classes.search.ArticleSearchIndex');
		$articleSearchIndex = new ArticleSearchIndex();
		$articleSearchIndex->rebuildIndex();
		return true;
	}

	/**
	 * Clear the data cache files (needed because of direct tinkering
	 * with settings tables)
	 * @return boolean
	 */
	function clearDataCache() {
		$cacheManager = CacheManager::getManager();
		$cacheManager->flush(null, CACHE_TYPE_FILE);
		$cacheManager->flush(null, CACHE_TYPE_OBJECT);
		return true;
	}

	/**
	 * Clear the CSS cache files (needed when changing LESS files)
	 * @return boolean
	 */
	function clearCssCache() {
		$request = Application::getRequest();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->clearCssCache();
		return true;
	}

	/**
	 * For 3.0.0 upgrade: Convert string-field semi-colon separated metadata to controlled vocabularies.
	 * @return boolean
	 */
	function migrateArticleMetadata() {
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');

		// controlled vocabulary DAOs.
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO');
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');
		$controlledVocabDao = DAORegistry::getDAO('ControlledVocabDAO');

		// check to see if there are any existing controlled vocabs for submissionAgency, submissionDiscipline, submissionSubject, or submissionLanguage.
		// IF there are, this implies that this code has run previously, so return.
		$vocabTestResult = $controlledVocabDao->retrieve('SELECT count(*) AS total FROM controlled_vocabs WHERE symbolic = \'submissionAgency\' OR symbolic = \'submissionDiscipline\' OR symbolic = \'submissionSubject\' OR symbolic = \'submissionLanguage\'');
		$testRow = $vocabTestResult->GetRowAssoc(false);
		if ($testRow['total'] > 0) return true;

		$journals = $journalDao->getAll();
		while ($journal = $journals->next()) {
			// for languages, we depend on the journal locale settings since languages are not localized.
			// Use Journal locales, or primary if no defined submission locales.
			$supportedLocales = $journal->getSupportedSubmissionLocales();

			if (empty($supportedLocales)) $supportedLocales = array($journal->getPrimaryLocale());
			else if (!is_array($supportedLocales)) $supportedLocales = array($supportedLocales);

			$result = $articleDao->retrieve('SELECT a.submission_id FROM submissions a WHERE a.context_id = ?', array((int)$journal->getId()));
			while (!$result->EOF) {
				$row = $result->GetRowAssoc(false);
				$articleId = (int)$row['submission_id'];
				$settings = array();
				$settingResult = $articleDao->retrieve('SELECT setting_value, setting_name, locale FROM submission_settings WHERE submission_id = ? AND (setting_name = \'discipline\' OR setting_name = \'subject\' OR setting_name = \'sponsor\');', array((int)$articleId));
				while (!$settingResult->EOF) {
					$settingRow = $settingResult->GetRowAssoc(false);
					$locale = $settingRow['locale'];
					$settingName = $settingRow['setting_name'];
					$settingValue = $settingRow['setting_value'];
					$settings[$settingName][$locale] = $settingValue;
					$settingResult->MoveNext();
				}
				$settingResult->Close();

				$languageResult = $articleDao->retrieve('SELECT language FROM submissions WHERE submission_id = ?', array((int)$articleId));
				$languageRow = $languageResult->getRowAssoc(false);
				// language is NOT localized originally.
				$language = $languageRow['language'];
				$languageResult->Close();
				// test for locales for each field since locales may have been modified since
				// the article was last edited.

				$disciplines = $subjects = $agencies = array();

				if (array_key_exists('discipline', $settings)) {
					$disciplineLocales = array_keys($settings['discipline']);
					if (is_array($disciplineLocales)) {
						foreach ($disciplineLocales as &$locale) {
							$disciplines[$locale] = preg_split('/[,;:]/', $settings['discipline'][$locale]);
						}
						$submissionDisciplineDao->insertDisciplines($disciplines, $articleId, false);
					}
					unset($disciplineLocales);
					unset($disciplines);
				}

				if (array_key_exists('subject', $settings)) {
					$subjectLocales = array_keys($settings['subject']);
					if (is_array($subjectLocales)) {
						foreach ($subjectLocales as &$locale) {
							$subjects[$locale] = preg_split('/[,;:]/', $settings['subject'][$locale]);
						}
						$submissionSubjectDao->insertSubjects($subjects, $articleId, false);
					}
					unset($subjectLocales);
					unset($subjects);
				}

				if (array_key_exists('sponsor', $settings)) {
					$sponsorLocales = array_keys($settings['sponsor']);
					if (is_array($sponsorLocales)) {
						foreach ($sponsorLocales as &$locale) {
							// note array name change.  Sponsor -> Agency
							$agencies[$locale] = preg_split('/[,;:]/', $settings['sponsor'][$locale]);
						}
						$submissionAgencyDao->insertAgencies($agencies, $articleId, false);
					}
					unset($sponsorLocales);
					unset($agencies);
				}

				$languages = array();
				foreach ($supportedLocales as &$locale) {
					$languages[$locale] = preg_split('/\s+/', $language);
				}

				$submissionLanguageDao->insertLanguages($languages, $articleId, false);

				unset($languages);
				unset($language);
				unset($settings);
				$result->MoveNext();
			}
			$result->Close();
			unset($supportedLocales);
			unset($result);
			unset($journal);
		}

		return true;
	}

	/**
	 * For 3.0.0 upgrade:  Migrate the static user role structure to
	 * user groups and stage assignments.
	 * @return boolean
	 */
	function migrateUserRoles() {

		// if there are any user_groups created, then this has already run.  Return immediately in that case.

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupTest = $userGroupDao->retrieve('SELECT count(*) AS total FROM user_groups');
		$testRow = $userGroupTest->GetRowAssoc(false);
		if ($testRow['total'] > 0) return true;

		// First, do Admins.
		// create the admin user group.
		$userGroupDao->update('INSERT INTO user_groups (context_id, role_id, is_default) VALUES (?, ?, ?)', array(CONTEXT_SITE, ROLE_ID_SITE_ADMIN, 1));
		$userGroupId = $userGroupDao->getInsertId();

		$userResult = $userGroupDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array(CONTEXT_SITE, ROLE_ID_SITE_ADMIN));
		while (!$userResult->EOF) {
			$row = $userResult->GetRowAssoc(false);
			$userGroupDao->update('INSERT INTO user_user_groups (user_group_id, user_id) VALUES (?, ?)', array($userGroupId, (int) $row['user_id']));
			$userResult->MoveNext();
		}

		// iterate through all journals and assign remaining users to their respective groups.
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$journals = $journalDao->getAll();

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_PKP_DEFAULT);

		define('ROLE_ID_LAYOUT_EDITOR',	0x00000300);
		define('ROLE_ID_COPYEDITOR', 0x00002000);
		define('ROLE_ID_PROOFREADER', 0x00003000);

		while ($journal = $journals->next()) {
			// Install default user groups so we can assign users to them.
			$userGroupDao->installSettings($journal->getId(), 'registry/userGroups.xml');

			// Readers.
			$group = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_READER);
			$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_READER));
			while (!$userResult->EOF) {
				$row = $userResult->GetRowAssoc(false);
				$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
				$userResult->MoveNext();
			}

			// Subscription Managers.
			$group = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_SUBSCRIPTION_MANAGER);
			$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_SUBSCRIPTION_MANAGER));
			while (!$userResult->EOF) {
				$row = $userResult->GetRowAssoc(false);
				$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
				$userResult->MoveNext();
			}

			// Managers.
			$group = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_MANAGER);
			$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_MANAGER));
			while (!$userResult->EOF) {
				$row = $userResult->GetRowAssoc(false);
				$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
				$userResult->MoveNext();
			}

			// Authors.
			$group = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_AUTHOR);
			$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_AUTHOR));
			while (!$userResult->EOF) {
				$row = $userResult->GetRowAssoc(false);
				$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
				$userResult->MoveNext();
			}

			// update the user_group_id column in the authors table.
			$userGroupDao->update('UPDATE authors SET user_group_id = ?', array((int) $group->getId()));

			// Reviewers.  All existing OJS reviewers get mapped to external reviewers.
			// There should only be one user group with ROLE_ID_REVIEWER in the external review stage.
			$userGroups = $userGroupDao->getUserGroupsByStage($journal->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, true, false, ROLE_ID_REVIEWER);
			$reviewerUserGroup = null; // keep this in scope for later.

			while ($group = $userGroups->next()) {
				// make sure.
				if ($group->getRoleId() != ROLE_ID_REVIEWER) continue;
				$reviewerUserGroup = $group;

				$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_REVIEWER));
				while (!$userResult->EOF) {
					$row = $userResult->GetRowAssoc(false);
					$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
					$userResult->MoveNext();
				}
			}

			// fix stage id assignments for reviews.  OJS hard coded *all* of these to '1' initially. Consider OJS reviews as external reviews.
			$userGroupDao->update('UPDATE review_assignments SET stage_id = ?', array(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW));

			// regular Editors.  NOTE:  this involves a role id change from 0x100 to 0x10 (old OJS _EDITOR to PKP-lib _MANAGER).
			$userGroups = $userGroupDao->getByRoleId($journal->getId(), ROLE_ID_MANAGER);
			$editorUserGroup = null;
			while ($group = $userGroups->next()) {
				if ($group->getData('nameLocaleKey') == 'default.groups.name.editor') {
					$editorUserGroup = $group; // stash for later.
					$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), 0x00000100 /* ROLE_ID_EDITOR */));
					while (!$userResult->EOF) {
						$row = $userResult->GetRowAssoc(false);
						$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
						$userResult->MoveNext();
					}
				}
			}

			// Section Editors.
			$sectionEditorGroup = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_SECTION_EDITOR);
			$userResult = $journalDao->retrieve('SELECT DISTINCT user_id FROM section_editors WHERE context_id = ?', array((int) $journal->getId()));;
			while (!$userResult->EOF) {
				$row = $userResult->GetRowAssoc(false);
				$userGroupDao->assignUserToGroup($row['user_id'], $sectionEditorGroup->getId());
				$userResult->MoveNext();
			}

			// Layout Editors. NOTE:  this involves a role id change from 0x300 to 0x1001 (old OJS _LAYOUT_EDITOR to PKP-lib _ASSISTANT).
			$userGroups = $userGroupDao->getByRoleId($journal->getId(), ROLE_ID_ASSISTANT);
			$layoutEditorGroup = null;
			while ($group = $userGroups->next()) {
				if ($group->getData('nameLocaleKey') == 'default.groups.name.layoutEditor') {
					$layoutEditorGroup = $group;
					$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_LAYOUT_EDITOR));
					while (!$userResult->EOF) {
						$row = $userResult->GetRowAssoc(false);
						$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
						$userResult->MoveNext();
					}
				}
			}

			// Copyeditors. NOTE:  this involves a role id change from 0x2000 to 0x1001 (old OJS _COPYEDITOR to PKP-lib _ASSISTANT).
			$userGroups = $userGroupDao->getByRoleId($journal->getId(), ROLE_ID_ASSISTANT);
			$copyEditorGroup = null;
			while ($group = $userGroups->next()) {
				if ($group->getData('nameLocaleKey') == 'default.groups.name.copyeditor') {
					$copyEditorGroup = $group;
					$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_COPYEDITOR));
					while (!$userResult->EOF) {
						$row = $userResult->GetRowAssoc(false);
						$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
						$userResult->MoveNext();
					}
				}
			}

			// Proofreaders. NOTE:  this involves a role id change from 0x3000 to 0x1001 (old OJS _PROOFREADER to PKP-lib _ASSISTANT).
			$userGroups = $userGroupDao->getByRoleId($journal->getId(), ROLE_ID_ASSISTANT);
			$proofreaderGroup = null;
			while ($group = $userGroups->next()) {
				if ($group->getData('nameLocaleKey') == 'default.groups.name.proofreader') {
					$proofreaderGroup = $group;
					$userResult = $journalDao->retrieve('SELECT user_id FROM roles WHERE journal_id = ? AND role_id = ?', array((int) $journal->getId(), ROLE_ID_PROOFREADER));
					while (!$userResult->EOF) {
						$row = $userResult->GetRowAssoc(false);
						$userGroupDao->assignUserToGroup($row['user_id'], $group->getId());
						$userResult->MoveNext();
					}
				}
			}

			// Now, migrate stage assignments. This code is based on the default stage assignments outlined in registry/userGroups.xml
			$submissionDao = Application::getSubmissionDAO();
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$submissionResult = $submissionDao->retrieve('SELECT article_id, user_id FROM articles_migration WHERE journal_id = ?', array($journal->getId()));
			$authorGroup = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_AUTHOR);
			while (!$submissionResult->EOF) {
				$submissionRow = $submissionResult->GetRowAssoc(false);
				$submissionId = $submissionRow['article_id'];
				$submissionUserId = $submissionRow['user_id'];
				unset($submissionRow);

				// Authors get access to all stages.
				$stageAssignmentDao->build($submissionId, $authorGroup->getId(), $submissionUserId);

				// Journal Editors
				// First, full editors.
				$editorsResult = $stageAssignmentDao->retrieve('SELECT e.* FROM submissions s, edit_assignments e, users u, roles r WHERE r.user_id = e.editor_id AND r.role_id = ' .
							0x00000100 /* ROLE_ID_EDITOR */ . ' AND e.article_id = ? AND r.journal_id = s.context_id AND s.submission_id = e.article_id AND e.editor_id = u.user_id', array($submissionId));
				while (!$editorsResult->EOF) {
					$editorRow = $editorsResult->GetRowAssoc(false);
					$stageAssignmentDao->build($submissionId, $editorUserGroup->getId(), $editorRow['editor_id']);
					$editorsResult->MoveNext();
				}
				unset($editorsResult);

				// Section Editors.
				$editorsResult = $stageAssignmentDao->retrieve('SELECT e.* FROM submissions s LEFT JOIN edit_assignments e ON (s.submission_id = e.article_id) LEFT JOIN users u ON (e.editor_id = u.user_id)
							LEFT JOIN roles r ON (r.user_id = e.editor_id AND r.role_id = ' . 0x00000100 /* ROLE_ID_EDITOR */ . ' AND r.journal_id = s.context_id) WHERE e.article_id = ? AND s.submission_id = e.article_id
							AND r.role_id IS NULL', array($submissionId));
				while (!$editorsResult->EOF) {
					$editorRow = $editorsResult->GetRowAssoc(false);
					$stageAssignmentDao->build($submissionId, $sectionEditorGroup->getId(), $editorRow['editor_id']);
					$editorsResult->MoveNext();
				}
				unset($editorsResult);

				// Copyeditors.  Pull from the signoffs for SIGNOFF_COPYEDITING_INITIAL.
				// there should only be one (or no) copyeditor for each submission.
				// 257 === 0x0000101 (the old assoc type for ASSOC_TYPE_ARTICLE)

				$copyEditorResult = $stageAssignmentDao->retrieve('SELECT user_id FROM signoffs WHERE assoc_type = ? AND assoc_id = ? AND symbolic = ?',
								array(257, $submissionId, 'SIGNOFF_COPYEDITING_INITIAL'));

				if ($copyEditorResult->NumRows() == 1) { // the signoff exists.
					$copyEditorRow = $copyEditorResult->GetRowAssoc(false);
					$copyEditorId = (int) $copyEditorRow['user_id'];
					if ($copyEditorId > 0) { // there is a user assigned.
						$stageAssignmentDao->build($submissionId, $copyEditorGroup->getId(), $copyEditorId);
					}
				}

				// Layout editors.  Pull from the signoffs for SIGNOFF_LAYOUT.
				// there should only be one (or no) layout editor for each submission.
				// 257 === 0x0000101 (the old assoc type for ASSOC_TYPE_ARTICLE)

				$layoutEditorResult = $stageAssignmentDao->retrieve('SELECT user_id FROM signoffs WHERE assoc_type = ? AND assoc_id = ? AND symbolic = ?',
						array(257, $submissionId, 'SIGNOFF_LAYOUT'));

				if ($layoutEditorResult->NumRows() == 1) { // the signoff exists.
					$layoutEditorRow = $layoutEditorResult->GetRowAssoc(false);
					$layoutEditorId = (int) $layoutEditorRow['user_id'];
					if ($layoutEditorId > 0) { // there is a user assigned.
						$stageAssignmentDao->build($submissionId, $layoutEditorGroup->getId(), $layoutEditorId);
					}
				}

				// Proofreaders.  Pull from the signoffs for SIGNOFF_PROOFREADING_PROOFREADER.
				// there should only be one (or no) layout editor for each submission.
				// 257 === 0x0000101 (the old assoc type for ASSOC_TYPE_ARTICLE)

				$proofreaderResult = $stageAssignmentDao->retrieve('SELECT user_id FROM signoffs WHERE assoc_type = ? AND assoc_id = ? AND symbolic = ?',
						array(257, $submissionId, 'SIGNOFF_PROOFREADING_PROOFREADER'));

				if ($proofreaderResult->NumRows() == 1) { // the signoff exists.
					$proofreaderRow = $proofreaderResult->GetRowAssoc(false);
					$proofreaderId = (int) $proofreaderRow['user_id'];
					if ($proofreaderId > 0) { // there is a user assigned.
						$stageAssignmentDao->build($submissionId, $proofreaderGroup->getId(), $proofreaderId);
					}
				}

				$submissionResult->MoveNext();
			}
		}

		return true;
	}

	/**
	 * For 3.0.0 upgrade.  Genres are required to migrate files.
	 */
	function installDefaultGenres() {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$siteDao = DAORegistry::getDAO('SiteDAO');
		$site = $siteDao->getSite();
		$contextsResult = $genreDao->retrieve('SELECT journal_id FROM journals');
		while (!$contextsResult->EOF) {

			$row = $contextsResult->GetRowAssoc(false);
			$genreDao->installDefaults($row['journal_id'], $site->getInstalledLocales());
			$contextsResult->MoveNext();
		}

		return true;
	}

	/**
	 * For 2.4 upgrade: migrate COUNTER statistics to the metrics table.
	 */
	function migrateCounterPluginUsageStatistics() {
		$metricsDao = DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		$result = $metricsDao->retrieve('SELECT * FROM counter_monthly_log');
		if ($result->EOF) return true;

		$loadId = '3.0.0-upgrade-counter';
		$metricsDao->purgeLoadBatch($loadId);

		$fileTypeCounts = array(
			'count_html' => STATISTICS_FILE_TYPE_HTML,
			'count_pdf' => STATISTICS_FILE_TYPE_PDF,
			'count_other' => STATISTICS_FILE_TYPE_OTHER
		);

		while(!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			foreach ($fileTypeCounts as $countType => $fileType) {
				$month = (string) $row['month'];
				if (strlen($month) == 1) {
					$month = '0' . $month;
				}
				if ($row[$countType]) {
					$record = array(
						'load_id' => $loadId,
						'assoc_type' => ASSOC_TYPE_JOURNAL,
						'assoc_id' => $row['journal_id'],
						'metric_type' => 'ojs::legacyCounterPlugin',
						'metric' => $row[$countType],
						'file_type' => $fileType,
						'month' => $row['year'] . $month
					);
					$metricsDao->insertRecord($record);
				}
			}
			$result->MoveNext();
		}

		// Remove the plugin settings.
		$metricsDao->update('DELETE FROM plugin_settings WHERE plugin_name = ?', array('counterplugin'), false);

		return true;
	}

	/**
	 * For 2.4 upgrade: migrate Timed views statistics to the metrics table.
	 */
	function migrateTimedViewsUsageStatistics() {
		$metricsDao = DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		$result =& $metricsDao->retrieve('SELECT * FROM timed_views_log');
		if ($result->EOF) return true;

		$loadId = '3.0.0-upgrade-timedViews';
		$metricsDao->purgeLoadBatch($loadId);

		$plugin = PluginRegistry::getPlugin('generic', 'usagestatsplugin');
		$plugin->import('UsageStatsTemporaryRecordDAO');
		$tempStatsDao = new UsageStatsTemporaryRecordDAO();
		$tempStatsDao->deleteByLoadId($loadId);

		import('plugins.generic.usageStats.GeoLocationTool');
		$geoLocationTool = new GeoLocationTool();

		while(!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			list($countryId, $cityName, $region) = $geoLocationTool->getGeoLocation($row['ip_address']);
			if ($row['galley_id']) {
				$assocType = ASSOC_TYPE_GALLEY;
				$assocId = $row['galley_id'];
			} else {
				$assocType = ASSOC_TYPE_SUBMISSION;
				$assocId = $row['submission_id'];
			};

			$day = date('Ymd', strtotime($row['date']));
			$tempStatsDao->insert($assocType, $assocId, $day, $countryId, $region, $cityName, null, $loadId);
			$result->MoveNext();
		}

		// Articles.
		$params = array('ojs::timedViews', $loadId, ASSOC_TYPE_SUBMISSION);
		$tempStatsDao->update(
					'INSERT INTO metrics (load_id, metric_type, assoc_type, assoc_id, day, country_id, region, city, submission_id, metric, context_id, issue_id)
					SELECT tr.load_id, ?, tr.assoc_type, tr.assoc_id, tr.day, tr.country_id, tr.region, tr.city, tr.assoc_id, count(tr.metric), a.context_id, pa.issue_id
					FROM usage_stats_temporary_records AS tr
					LEFT JOIN submissions AS a ON a.submission_id = tr.assoc_id
					LEFT JOIN published_submissions AS pa ON pa.submission_id = tr.assoc_id
					WHERE tr.load_id = ? AND tr.assoc_type = ? AND a.context_id IS NOT NULL AND pa.issue_id IS NOT NULL
					GROUP BY tr.assoc_type, tr.assoc_id, tr.day, tr.country_id, tr.region, tr.city, tr.file_type, tr.load_id', $params
		);

		// Galleys.
		$params = array('ojs::timedViews', $loadId, ASSOC_TYPE_GALLEY);
		$tempStatsDao->update(
					'INSERT INTO metrics (load_id, metric_type, assoc_type, assoc_id, day, country_id, region, city, submission_id, metric, context_id, issue_id)
					SELECT tr.load_id, ?, tr.assoc_type, tr.assoc_id, tr.day, tr.country_id, tr.region, tr.city, ag.submission_id, count(tr.metric), a.context_id, pa.issue_id
					FROM usage_stats_temporary_records AS tr
					LEFT JOIN submission_galleys AS ag ON ag.galley_id = tr.assoc_id
					LEFT JOIN submissions AS a ON a.submission_id = ag.submission_id
					LEFT JOIN published_submissions AS pa ON pa.submission_id = ag.submission_id
					WHERE tr.load_id = ? AND tr.assoc_type = ? AND a.context_id IS NOT NULL AND pa.issue_id IS NOT NULL
					GROUP BY tr.assoc_type, tr.assoc_id, tr.day, tr.country_id, tr.region, tr.city, tr.file_type, tr.load_id', $params
		);

		$tempStatsDao->deleteByLoadId($loadId);

		// Remove the plugin settings.
		$metricsDao->update('DELETE FROM plugin_settings WHERE plugin_name = ?', array('timedviewplugin'), false);

		return true;
	}

	/**
	 * For 2.4 upgrade: migrate OJS default statistics to the metrics table.
	 */
	function migrateDefaultUsageStatistics() {
		$loadId = '3.0.0-upgrade-ojsViews';
		$metricsDao = DAORegistry::getDAO('MetricsDAO');
		$insertIntoClause = 'INSERT INTO metrics (file_type, load_id, metric_type, assoc_type, assoc_id, submission_id, metric, context_id, issue_id)';
		$selectClause = null; // Conditionally set later

		// Galleys.
		$galleyUpdateCases = array(
			array('fileType' => STATISTICS_FILE_TYPE_PDF, 'isHtml' => false, 'assocType' => ASSOC_TYPE_GALLEY),
			array('fileType' => STATISTICS_FILE_TYPE_HTML, 'isHtml' => true, 'assocType' => ASSOC_TYPE_GALLEY),
			array('fileType' => STATISTICS_FILE_TYPE_OTHER, 'isHtml' => false, 'assocType' => ASSOC_TYPE_GALLEY),
			array('fileType' => STATISTICS_FILE_TYPE_PDF, 'assocType' => ASSOC_TYPE_ISSUE_GALLEY),
			array('fileType' => STATISTICS_FILE_TYPE_OTHER, 'assocType' => ASSOC_TYPE_ISSUE_GALLEY),
		);

		foreach ($galleyUpdateCases as $case) {
			$skipQuery = false;
			if ($case['fileType'] == STATISTICS_FILE_TYPE_PDF) {
				$pdfFileTypeWhereCheck = 'IN';
			} else {
				$pdfFileTypeWhereCheck = 'NOT IN';
			}

			$params = array($case['fileType'], $loadId, 'ojs::legacyDefault', $case['assocType']);

			if ($case['assocType'] == ASSOC_TYPE_GALLEY) {
				array_push($params, (int) $case['isHtml']);
				$selectClause = ' SELECT ?, ?, ?, ?, ag.galley_id, ag.article_id, ag.views, a.context_id, pa.issue_id
					FROM article_galleys_stats_migration as ag
					LEFT JOIN submissions AS a ON ag.article_id = a.submission_id
					LEFT JOIN published_submissions as pa on ag.article_id = pa.submission_id
					LEFT JOIN submission_files as af on ag.file_id = af.file_id
					WHERE a.submission_id is not null AND ag.views > 0 AND ag.html_galley = ?
						AND af.file_type ';
			} else {
				if ($this->tableExists('issue_galleys_stats_migration')) {
					$selectClause = 'SELECT ?, ?, ?, ?, ig.galley_id, 0, ig.views, i.journal_id, ig.issue_id
						FROM issue_galleys_stats_migration AS ig
						LEFT JOIN issues AS i ON ig.issue_id = i.issue_id
						LEFT JOIN issue_files AS ifi ON ig.file_id = ifi.file_id
						WHERE ig.views > 0 AND i.issue_id is not null AND ifi.file_type ';
				} else {
					// Upgrading from a version that
					// didn't support issue galleys. Skip.
					$skipQuery = true;
				}
			}

			array_push($params, 'application/pdf', 'application/x-pdf', 'text/pdf', 'text/x-pdf');

			if (!$skipQuery) {
				$metricsDao->update($insertIntoClause . $selectClause . $pdfFileTypeWhereCheck . ' (?, ?, ?, ?)', $params, false);
			}
		}

		// Published articles.
		$params = array(null, $loadId, 'ojs::legacyDefault', ASSOC_TYPE_SUBMISSION);
		$metricsDao->update($insertIntoClause .
			' SELECT ?, ?, ?, ?, pa.article_id, pa.article_id, pa.views, i.journal_id, pa.issue_id
			FROM published_articles_stats_migration as pa
			LEFT JOIN issues AS i ON pa.issue_id = i.issue_id
			WHERE pa.views > 0 AND i.issue_id is not null;', $params, false);

		// Set the site default metric type.
		$siteSettingsDao = DAORegistry::getDAO('SiteSettingsDAO'); /* @var $siteSettingsDao SiteSettingsDAO */
		$siteSettingsDao->updateSetting('defaultMetricType', OJS_METRIC_TYPE_COUNTER);

		return true;
	}

	/**
	 * Synchronize the ASSOC_TYPE_SERIES constant to ASSOC_TYPE_SECTION defined in PKPApplication.
	 * @return boolean
	 */
	function syncSeriesAssocType() {
		// Can be any DAO.
		$dao =& DAORegistry::getDAO('UserDAO'); /* @var $dao DAO */
		$tablesToUpdate = array(
			'announcements',
			'announcements_types',
			'user_settings',
			'notification',
			'email_templates',
			'email_templates_data',
			'controlled_vocabs',
			'gifts',
			'event_log',
			'email_log',
			'metadata_descriptions',
			'metrics',
			'notes',
			'item_views',
			'data_object_tombstone_oai_set_objects');

		foreach ($tablesToUpdate as $tableName) {
			if ($this->tableExists($tableName)) {
				$dao->update('UPDATE ' . $tableName . ' SET assoc_type = ' . ASSOC_TYPE_SECTION . ' WHERE assoc_type = ' . "'526'");
			}
		}

		return true;
	}

	/**
	 * Modernize review form storage from OJS 2.x
	 * @return boolean
	 */
	function fixReviewForms() {
		// 1. Review form possible options were stored with 'order'
		//    and 'content' attributes. Just store by content.
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$result = $reviewFormDao->retrieve(
			'SELECT * FROM review_form_element_settings WHERE setting_name = ?',
			'possibleResponses'
		);
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$options = unserialize($row['setting_value']);
			$newOptions = array();
			foreach ($options as $key => $option) {
				$newOptions[$key] = $option['content'];
			}
			$row['setting_value'] = serialize($newOptions);
			$reviewFormDao->Replace('review_form_element_settings', $row, array('review_form_element_id', 'locale', 'setting_name'));
			$result->MoveNext();
		}
		$result->Close();

		// 2. Responses were stored with indexes offset by 1. Fix.
		import('lib.pkp.classes.reviewForm.ReviewFormElement'); // Constants
		$result = $reviewFormDao->retrieve(
			'SELECT	rfe.element_type AS element_type,
				rfr.response_value AS response_value,
				rfr.review_id AS review_id,
				rfe.review_form_element_id AS review_form_element_id
			FROM	review_form_responses rfr
				JOIN review_form_elements rfe ON (rfe.review_form_element_id = rfr.review_form_element_id)
			WHERE	rfe.element_type IN (?, ?, ?)',
			array(
				REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
				REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
				REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX
			)
		);
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$value = $row['response_value'];
			switch ($row['element_type']) {
				case REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
					// Stored as a serialized object.
					$oldValue = unserialize($value);
					$value = array();
					foreach ($oldValue as $k => $v) {
						$value[$k] = $v-1;
					}
					$value = serialize($value);
					break;
				case REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
				case REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
					// Stored as a simple number.
					$value-=1;
					break;
			}
			$reviewFormDao->update(
				'UPDATE review_form_responses SET response_value = ? WHERE review_id = ? AND review_form_element_id = ?',
				array($value, $row['review_id'], $row['review_form_element_id'])
			);
			$result->MoveNext();
		}
		$result->Close();

		return true;
	}

	/**
	 * Convert email templates to HTML.
	 * @return boolean True indicates success.
	 */
	function htmlifyEmailTemplates() {
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');

		// Convert the email templates in email_templates_data to localized
		$result = $emailTemplateDao->retrieve('SELECT * FROM email_templates_data');
		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$emailTemplateDao->update(
				'UPDATE	email_templates_data
				SET	body = ?
				WHERE	email_key = ? AND
					locale = ? AND
					assoc_type = ? AND
					assoc_id = ?',
				array(
					preg_replace('/{\$[a-zA-Z]+Url}/', '<a href="\0">\0</a>', nl2br($row['body'])),
					$row['email_key'],
					$row['locale'],
					$row['assoc_type'],
					$row['assoc_id']
				)
			);
			$result->MoveNext();
		}
		$result->Close();

		// Convert the email templates in email_templates_default_data to localized
		$result = $emailTemplateDao->retrieve('SELECT * FROM email_templates_default_data');
		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$emailTemplateDao->update(
				'UPDATE	email_templates_default_data
				SET	body = ?
				WHERE	email_key = ? AND
					locale = ?',
				array(
					preg_replace('/{\$[a-zA-Z]+Url}/', '<a href="\0">\0</a>', nl2br($row['body'])),
					$row['email_key'],
					$row['locale'],
				)
			);
			$result->MoveNext();
		}
		$result->Close();

		// Localize the email header and footer fields.
		$contextDao = DAORegistry::getDAO('JournalDAO');
		$settingsDao = DAORegistry::getDAO('JournalSettingsDAO');
		$contexts = $contextDao->getAll();
		while ($context = $contexts->next()) {
			foreach (array('emailFooter', 'emailSignature') as $settingName) {
				$settingsDao->updateSetting(
					$context->getId(),
					$settingName,
					$context->getSetting('emailHeader'),
					'string'
				);
			}
		}

		return true;
	}

	/**
	 * For 2.4.6 upgrade: to enable localization of a CustomBlock,
	 * the blockContent values are converted from string to array (key: primary_language)
	 */
	function localizeCustomBlockSettings() {
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$journals = $journalDao->getAll();

		while ($journal = $journals->next()) {
			$journalId = $journal->getId();
			$primaryLocale = $journal->getPrimaryLocale();

			$blocks = $pluginSettingsDao->getSetting($journalId, 'customblockmanagerplugin', 'blocks');
			if ($blocks) foreach ($blocks as $block) {
				$blockContent = $pluginSettingsDao->getSetting($journalId, $block, 'blockContent');

				if (!is_array($blockContent)) {
					$pluginSettingsDao->updateSetting($journalId, $block, 'blockContent', array($primaryLocale => $blockContent));
				}
			}
			unset($journal);
		}

		return true;
	}

	/**
	 * Migrate submission filenames from OJS 2.x
	 * @param $upgrade Upgrade
	 * @param $params array
	 * @return boolean
	 */
	function migrateFiles($upgrade, $params) {
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$submissionDao = DAORegistry::getDAO('ArticleDAO');
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		DAORegistry::getDAO('GenreDAO'); // Load constants
		$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site = $siteDao->getSite();
		$adminEmail = $site->getLocalizedContactEmail();

		// get file names form OJS 2.4.x table article_files i.e.
		// from the temporary table article_files_migration
		$ojs2FileNames = array();
		$result = $submissionFileDao->retrieve('SELECT file_id, revision, file_name FROM article_files_migration');
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$ojs2FileNames[$row['file_id']][$row['revision']] = $row['file_name'];
			$result->MoveNext();
		}
		$result->Close();

		import('lib.pkp.classes.file.SubmissionFileManager');

		$contexts = $journalDao->getAll();
		while ($context = $contexts->next()) {
			$submissions = $submissionDao->getByContextId($context->getId());
			while ($submission = $submissions->next()) {
				$submissionFileManager = new SubmissionFileManager($context->getId(), $submission->getId());
				$submissionFiles = $submissionFileDao->getBySubmissionId($submission->getId());
				foreach ($submissionFiles as $submissionFile) {
					$generatedFilename = $submissionFile->getServerFileName();
					$basePath = $submissionFileManager->getBasePath() . '/';
					$globPattern = $ojs2FileNames[$submissionFile->getFileId()][$submissionFile->getRevision()];

					$pattern1 = glob($basePath . '*/*/' . $globPattern);
					$pattern2 = glob($basePath . '*/' . $globPattern);
					if (!is_array($pattern1)) $pattern1 = array();
					if (!is_array($pattern2)) $pattern2 = array();
					$matchedResults = array_merge($pattern1, $pattern2);

					if (count($matchedResults)>1) {
						// Too many filenames matched. Continue with the first; this is just a warning.
						error_log("WARNING: Duplicate potential files for \"$globPattern\" in \"" . $submissionFileManager->getBasePath() . "\". Taking the first.");
					} elseif (count($matchedResults)==0) {
						// No filenames matched. Skip migrating.
						error_log("WARNING: Unable to find a match for \"$globPattern\" in \"" . $submissionFileManager->getBasePath() . "\". Skipping this file.");
						continue;
					}
					$discoveredFilename = array_shift($matchedResults);
					$targetFilename = $basePath . $submissionFile->_fileStageToPath($submissionFile->getFileStage()) . '/' . $generatedFilename;
					if (file_exists($targetFilename)) continue; // Skip existing files/links
					if (!file_exists($path = dirname($targetFilename)) && !$submissionFileManager->mkdirtree($path)) {
						error_log("Unable to make directory \"$path\"");
					}
					if (!rename($discoveredFilename, $targetFilename)) {
						error_log("Unable to move \"$discoveredFilename\" to \"$targetFilename\".");
					}
				}
			}
		}
		return true;
	}

	/**
	 * Set the missing uploader user id and group id to a journal manager.
	 * @return boolean True indicates success.
	 */
	function setFileUploader() {
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$journalIterator = $journalDao->getAll();
		$driver = $submissionFileDao->getDriver();
		while ($journal = $journalIterator->next()) {
			$managerUserGroup = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_MANAGER);
			$managerUsers = $userGroupDao->getUsersById($managerUserGroup->getId(), $journal->getId());
			$creatorUserId = $managerUsers->next()->getId();
			switch ($driver) {
				case 'mysql':
				case 'mysqli':
					$submissionFileDao->update('UPDATE submission_files sf, submissions s SET sf.uploader_user_id = ?, sf.user_group_id = ? WHERE sf.uploader_user_id IS NULL AND sf.user_group_id IS NULL AND sf.submission_id = s.submission_id AND s.context_id = ?', array($creatorUserId, $managerUserGroup->getId(), $journal->getId()));
					break;
				case 'postgres':
					$submissionFileDao->update('UPDATE submission_files SET uploader_user_id = ?, user_group_id = ? FROM submissions s WHERE submission_files.uploader_user_id IS NULL AND submission_files.user_group_id IS NULL AND submission_files.submission_id = s.submission_id AND s.context_id = ?', array($creatorUserId, $managerUserGroup->getId(), $journal->getId()));
					break;
				default: fatalError('Unknown database type!');
			}
			$emptyUserGroupResult = $submissionFileDao->retrieve('SELECT DISTINCT sf.uploader_user_id FROM submission_files sf, submissions s WHERE sf.user_group_id IS NULL AND sf.submission_id = s.submission_id AND s.context_id = ?',array($journal->getId()));
			while (!$emptyUserGroupResult->EOF) {
				$row = $emptyUserGroupResult->getRowAssoc(false);
				$emptyUserGroupResult->MoveNext();
				$uploaderUserId = $row['uploader_user_id'];
				$userGroupIdResult = $userGroupDao->retrieve('SELECT MIN(ug.user_group_id) as user_group_id FROM user_groups ug, user_user_groups uug WHERE ug.user_group_id = uug.user_group_id AND uug.user_id = ? AND ug.context_id = ?', array($uploaderUserId, $journal->getId()));
				if ($userGroupIdResult->RecordCount() != 0) {
					$userGroupId = $userGroupIdResult->fields[0];
					switch ($driver) {
						case 'mysql':
						case 'mysqli':
							$submissionFileDao->update('UPDATE submission_files sf, submissions s SET sf.user_group_id = ? WHERE sf.uploader_user_id = ? AND sf.user_group_id IS NULL AND sf.submission_id = s.submission_id AND s.context_id = ?', array($userGroupId, $uploaderUserId, $journal->getId()));
							break;
						case 'postgres':
							$submissionFileDao->update('UPDATE submission_files SET user_group_id = ? FROM submissions s WHERE submission_files.uploader_user_id = ? AND submission_files.user_group_id IS NULL AND submission_files.submission_id = s.submission_id AND s.context_id = ?', array($userGroupId, $uploaderUserId, $journal->getId()));
							break;
						default: fatalError('Unknown database type!');
					}
				}
			}
			unset($managerUsers, $managerUserGroup);
		}
		return true;
	}

	/**
	 * Set the missing file names.
	 * @return boolean True indicates success.
	 */
	function setFileName() {
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$submissionDao = DAORegistry::getDAO('ArticleDAO');
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');

		$contexts = $journalDao->getAll();
		while ($context = $contexts->next()) {
			$submissions = $submissionDao->getByContextId($context->getId());
			while ($submission = $submissions->next()) {
				$submissionFiles = $submissionFileDao->getBySubmissionId($submission->getId());
				foreach ($submissionFiles as $submissionFile) {
					$reviewStage = $submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_FILE ||
						$submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_ATTACHMENT ||
						$submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_REVISION;
					if (!$submissionFile->getName(AppLocale::getPrimaryLocale())) {
						if ($reviewStage) {
							$submissionFile->setName($submissionFile->_generateName(true), AppLocale::getPrimaryLocale());
						} else {
							$submissionFile->setName($submissionFile->_generateName(), AppLocale::getPrimaryLocale());
						}
					}
					$submissionFileDao->updateObject($submissionFile);
				}
			}
		}
		return true;
	}

	/**
	 * Convert supplementary files to submission files.
	 * @return boolean True indicates success.
	 */
	function convertSupplementaryFiles() {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$journal = null;

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$suppFilesResult = $submissionFileDao->retrieve('SELECT a.context_id, sf.* FROM article_supplementary_files sf, submissions a WHERE a.submission_id = sf.article_id'); // COMMENT_TYPE_EDITOR_DECISION
		while (!$suppFilesResult->EOF) {
			$row = $suppFilesResult->getRowAssoc(false);
			$suppFilesResult->MoveNext();
			if (!$journal || $journal->getId() != $row['context_id']) {
				$journal = $journalDao->getById($row['context_id']);
				$managerUserGroup = $userGroupDao->getDefaultByRoleId($journal->getId(), ROLE_ID_MANAGER);
				$managerUsers = $userGroupDao->getUsersById($managerUserGroup->getId(), $journal->getId());
				$creatorUserId = $managerUsers->next()->getId();
			}
			$article = $articleDao->getById($row['article_id']);

			$genre = null;
			switch ($row['type']) {
				// author.submit.suppFile.dataAnalysis
				case 'Análise de Dados':
				case 'Análises de dados':
				case 'Anàlisi de les dades':
				case 'Analisi di dati':
				case 'Analisis Data':
				case 'Análisis de datos':
				case 'Análisis de los datos':
				case 'Analiza podataka':
				case 'Analize de date':
				case 'Analizy':
				case 'Analyse de données':
				case 'Analyse':
				case 'Analys':
				case 'Analýza dat':
				case 'Dataanalyse':
				case 'Data Analysis':
				case 'Datenanalyse':
				case 'Datu-analisia':
				case 'Gegevensanalyse':
				case 'Phân tích dữ liệu':
				case 'Veri Analizi':
				case 'آنالیز داده':
				case 'تحليل بيانات':
				case 'Ανάλυση δεδομένων':
				case 'Анализа на податоци':
				case 'Анализ данных':
				case 'Аналіз даних':
				case 'ഡേറ്റാ വിശകലനം':
				case 'データ分析':
				case '数据分析':
				case '資料分析':
					$genre = $genreDao->getByKey('DATAANALYSIS', $journal->getId());
					break;
				// author.submit.suppFile.dataSet
				case 'Baza podataka':
				case 'Conjunt de dades':
				case 'Conjunto de Dados':
				case 'Conjunto de datos':
				case 'Conjuntos de datos':
				case 'Conxuntos de dados':
				case 'Datasæt':
				case 'Data Set':
				case 'Dataset':
				case 'Datasett':
				case 'Datensatz':
				case 'Datový soubor':
				case 'Datu multzoa':
				case 'Ensemble de données':
				case 'Forskningsdata':
				case 'データセット':
				case 'Set Data':
				case 'Set di dati':
				case 'Set podataka':
				case 'Seturi de date':
				case 'Tập hợp dữ liệu':
				case 'Veri Seti':
				case 'Zbiory danych':
				case 'مجموعة بيانات':
				case 'مجموعه ناده':
				case 'Σύνολο δεδομένων':
				case 'Збирка на податоци':
				case 'Набір даних':
				case 'Набор данных':
				case 'ഡേറ്റാ സെറ്റ്':
				case '数据集':
				case '資料或數據組':
					$genre = $genreDao->getByKey('DATASET', $journal->getId());
					break;
				// author.submit.suppFile.researchInstrument
				case 'Araştırma Enstürmanları':
				case 'Công cụ nghiên cứu':
				case 'Forschungsinstrument':
				case 'Forskningsinstrument':
				case 'Herramienta de investigación':
				case 'Ikerketa-tresna':
				case 'Instrumen Riset':
				case 'Instrument de cercetare':
				case 'Instrument de recerca':
				case 'Instrument de recherche':
				case 'Instrumenti istraživanja':
				case 'Instrumento de investigación':
				case 'Instrumento de Pesquisa':
				case 'Istraživački instrument':
				case 'Narzędzie badawcze':
				case 'Onderzoeksinstrument':
				case 'Research Instrument':
				case 'Strumento di ricerca':
				case 'Výzkumný nástroj':
				case 'ابزار پژوهشی':
				case 'أداة بحث':
				case 'Όργανο έρευνας':
				case 'Дослідний інструмент':
				case 'Инструмент исследования':
				case 'Истражувачки инструмент':
				case 'ഗവേഷണ ഉപകരണങ്ങള്‍':
				case '研究方法或工具':
				case '研究装置':
				case '科研仪器':
					$genre = $genreDao->getByKey('RESEARCHINSTRUMENT', $journal->getId());
					break;
				// author.submit.suppFile.researchMaterials
				case 'Araştırma Materyalleri':
				case 'Các tài liệu nghiên cứu':
				case 'Documents de recherche':
				case 'Forschungsmaterial':
				case 'Forskningsmateriale':
				case 'Forskningsmaterialer':
				case 'Forskningsmaterial':
				case 'Ikerketako materialak':
				case 'Istraživački materijali':
				case 'Istraživački materijal':
				case 'Materiais de investigación':
				case 'Material de Pesquisa':
				case 'Materiale de cercetare':
				case 'Materiales de investigación':
				case 'Materiali di ricerca':
				case 'Materials de recerca':
				case 'Materiały badawcze':
				case 'Materi/ Bahan Riset':
				case 'Onderzoeksmaterialen':
				case 'Research Materials':
				case 'Výzkumné materiály':
				case 'مواد بحث':
				case 'مواد پژوهشی':
				case 'Υλικά έρευνας':
				case 'Дослідні матеріали':
				case 'Истражувачки материјали':
				case 'Материалы исследования':
				case 'ഗവേഷണ സാമഗ്രികള്‍':
				case '研究材料':
				case '科研资料':
					$genre = $genreDao->getByKey('RESEARCHMATERIALS', $journal->getId());
					break;
				// author.submit.suppFile.researchResults
				case 'Araştırma Sonuçları':
				case 'Forschungsergebnisse':
				case 'Forskningsresultater':
				case 'Forskningsresultat':
				case 'Hasil Riset':
				case 'Ikerketaren emaitza':
				case 'Istraživački rezultati':
				case 'Kết quả nghiên cứu':
				case 'Onderzoeksresultaten':
				case 'Research Results':
				case 'Resultados de investigación':
				case 'Resultados de la investigación':
				case 'Resultados de Pesquisa':
				case 'Resultats de la recerca':
				case 'Résultats de recherche':
				case 'Rezultate de cercetare':
				case 'Rezultati istraživanja':
				case 'Rezultaty z badań':
				case 'Risultati di ricerca':
				case 'Výsledky výzkumu':
				case 'نتایج پژوهش':
				case 'نتائج بحث':
				case 'Αποτελέσματα έρευνας':
				case 'Истражувачки резултати':
				case 'Результати дослідження':
				case 'Результаты исследования':
				case 'ഗവേഷണ ഫലങ്ങള്‍':
				case '研究結果':
				case '科研结果':
					$genre = $genreDao->getByKey('RESEARCHRESULTS', $journal->getId());
					break;
				// author.submit.suppFile.sourceText
				case 'Brontekst':
				case 'Iturburu-testua':
				case 'Izvorni tekst':
				case 'Källtext':
				case 'Kaynak Metin':
				case 'Kildetekst':
				case 'ソーステキスト':
				case 'Quellentext':
				case 'Source Text':
				case 'Teks Sumber':
				case 'Tekst źródłowy':
				case 'Testo della fonte':
				case 'Texte source':
				case 'Texte sursă':
				case 'Texto fonte':
				case 'Texto fuente':
				case 'Texto Original':
				case 'Text original':
				case 'Văn bản (text) nguồn':
				case 'Zdrojový text':
				case 'متن منبع':
				case 'نص مصدر':
				case 'Πηγαίο κείμενο':
				case 'Изворен текст':
				case 'Исходный текст':
				case 'Текст першоджерела':
				case 'സോഴ്സ് ടെക്സ്റ്റ്':
				case '來源文獻':
				case '源文本':
					$genre = $genreDao->getByKey('SOURCETEXTS', $journal->getId());
					break;
				// author.submit.suppFile.transcripts
				case 'Afskrifter':
				case 'Kopya / Suret':
				case 'Lời thoại':
				case 'Reproduktioner':
				case 'Transcrição':
				case 'Transcricións':
				case 'Transcripciones':
				case 'Transcripcions':
				case 'Transcripties':
				case 'Transcriptions':
				case 'Transcripts':
				case 'Transcripturi':
				case 'Transkrip':
				case 'Transkripsjoner':
				case 'Transkripte':
				case 'Transkripti':
				case 'Transkript':
				case 'Transkripty':
				case 'Transkripzioak':
				case 'Transkrypcje':
				case 'Trascrizioni':
				case 'رونوشت':
				case 'نصوص':
				case 'Καταγραφή':
				case 'Стенограми':
				case 'Транскрипти':
				case 'Транскрипты':
				case 'പകര്‍പ്പുകള്‍':
				case '副本':
				case '筆記録':
					$genre = $genreDao->getByKey('TRANSCRIPTS', $journal->getId());
					break;
				default:
					$genre = $genreDao->getByKey('OTHER', $journal->getId());
					break;
			}
			assert(isset($genre));

			// Set genres for files
			$submissionFiles = $submissionFileDao->getAllRevisions($row['file_id']);
			foreach ((array) $submissionFiles as $submissionFile) {
				$submissionFile->setGenreId($genre->getId());
				$submissionFile->setUploaderUserId($creatorUserId);
				$submissionFile->setUserGroupId($managerUserGroup->getId());
				$submissionFile->setFileStage(SUBMISSION_FILE_SUBMISSION);
				$submissionFileDao->updateObject($submissionFile);
			}

			// Reload the files now that they're cast; set metadata
			$submissionFiles = $submissionFileDao->getAllRevisions($row['file_id']);
			foreach ((array) $submissionFiles as $submissionFile) {
				$suppFileSettingsResult = $submissionFileDao->retrieve('SELECT * FROM article_supp_file_settings WHERE supp_id = ? AND setting_value IS NOT NULL', array($row['supp_id']));
				$extraSettings = $extraGalleySettings = array();
				while (!$suppFileSettingsResult->EOF) {
					$sfRow = $suppFileSettingsResult->getRowAssoc(false);
					$suppFileSettingsResult->MoveNext();
					switch ($sfRow['setting_name']) {
						case 'creator':
							$submissionFile->setCreator($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'description':
							$submissionFile->setDescription($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'publisher':
							$submissionFile->setPublisher($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'source':
							$submissionFile->setSource($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'sponsor':
							$submissionFile->setSponsor($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'subject':
							$submissionFile->setSubject($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'title':
							$submissionFile->setName($sfRow['setting_value'], $sfRow['locale']);
							break;
						case 'typeOther': break; // Discard (at least for now)
						case 'excludeDoi': break; // Discard (no longer relevant)
						case 'excludeURN': break; // Discard (no longer relevant)
						case 'pub-id::doi':
						case 'pub-id::other::urn':
						case 'pub-id::publisher-id':
						case 'urnSuffix':
						case 'doiSuffix':
							$extraGalleySettings[$sfRow['setting_name']] = $sfRow['setting_value'];
							break;
						default:
							error_log('Unknown supplementary file setting "' . $sfRow['setting_name'] . '"!');
							break;
					}
				}
				$suppFileSettingsResult->Close();

				// Store the old supp ID so that we can redirect requests for old URLs.
				$extraSettings['old-supp-id'] = $row['supp_id'];

				$submissionFileDao->updateObject($submissionFile);

				// Preserve extra settings. (Plugins may not be loaded, so other mechanisms might not work.)
				foreach ($extraSettings as $name => $value) {
					$submissionFileDao->update(
						'INSERT INTO submission_file_settings (file_id, setting_name, setting_value, setting_type) VALUES (?, ?, ?, ?)',
						array(
							$submissionFile->getFileId(),
							$name,
							$value,
							'string'
						)
					);
				}

				if ($article->getStatus() == STATUS_PUBLISHED) {
					$articleGalley = $articleGalleyDao->newDataObject();
					$articleGalley->setFileId($submissionFile->getFileId());
					$articleGalley->setSubmissionId($article->getId());
					$articleGalley->setLabel($submissionFile->getName($article->getLocale()));
					$articleGalley->setLocale($article->getLocale());
					$articleGalleyDao->insertObject($articleGalley);

					// Preserve extra settings. (Plugins may not be loaded, so other mechanisms might not work.)
					foreach ($extraGalleySettings as $name => $value) {
						$submissionFileDao->update(
							'INSERT INTO submission_galley_settings (galley_id, setting_name, setting_value, setting_type) VALUES (?, ?, ?, ?)',
							array(
								$articleGalley->getId(),
								$name,
								$value,
								'string'
							)
						);
					}
				}
			}
		}
		$suppFilesResult->Close();
		return true;
	}

	function _createQuery($stageId, $submissionId, $sequence, $title, $dateNotified = null) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$noteDao = DAORegistry::getDAO('NoteDAO');

		$query = $queryDao->newDataObject();
		$query->setAssocType(ASSOC_TYPE_SUBMISSION);
		$query->setAssocId($submissionId);
		$query->setStageId($stageId);
		$query->setSequence($sequence);
		$queryDao->insertObject($query);

		$headNote = $noteDao->newDataObject();
		$headNote->setAssocType(ASSOC_TYPE_QUERY);
		$headNote->setAssocId($query->getId());
		$headNote->setTitle($title);
		$headNote->setDateCreated($dateNotified?$dateNotified:time());
		$headNote->setDateModified($dateNotified?$dateNotified:time());
		$noteDao->insertObject($headNote);

		return $query;
	}

	/**
	 * Convert signoffs to queries.
	 * @return boolean True indicates success.
	 */
	function convertQueries() {
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile');

		$signoffsResult = $submissionFileDao->retrieve('SELECT * FROM signoffs WHERE user_id IS NOT NULL AND user_id <> 0');

		$queryDao = DAORegistry::getDAO('QueryDAO');
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

		// Go through all signoffs and migrate them into queries.
		$copyeditingQueries = $proofreadingQueries = $layoutQueries = array();
		while (!$signoffsResult->EOF) {
			$row = $signoffsResult->getRowAssoc(false);
			$fileId = $row['file_id'];
			$symbolic = $row['symbolic'];
			$dateNotified = $row['date_notified']?strtotime($row['date_notified']):null;
			$dateCompleted = $row['date_completed']?strtotime($row['date_completed']):null;
			$userId = $row['user_id'];
			$signoffId = $row['signoff_id'];
			assert($row['assoc_type'] == ASSOC_TYPE_SUBMISSION); // Already changed from ASSOC_TYPE_ARTICLE
			$assocId = $row['assoc_id'];
			$signoffsResult->MoveNext();

			// Stage 1. Create or look up the query object.
			switch ($symbolic) {
				case 'SIGNOFF_COPYEDITING_INITIAL':
				case 'SIGNOFF_COPYEDITING_AUTHOR':
				case 'SIGNOFF_COPYEDITING_FINAL':
					if (isset($copyeditingQueries[$assocId])) $query = $copyeditingQueries[$assocId];
					else {
						$query = $copyeditingQueries[$assocId] = $this->_createQuery(WORKFLOW_STAGE_ID_EDITING, $assocId, 1, 'Copyediting', $dateNotified);
					}
					break;
				case 'SIGNOFF_LAYOUT':
					$query = $layoutQueries[$assocId] = $this->_createQuery(WORKFLOW_STAGE_ID_PRODUCTION, $assocId, 1, 'Layout Editing', $dateNotified);
					break;
				case 'SIGNOFF_PROOFREADING_AUTHOR':
				case 'SIGNOFF_PROOFREADING_PROOFREADER':
				case 'SIGNOFF_PROOFREADING_LAYOUT':
					if (isset($proofreadingQueries[$assocId])) $query = $proofreadingQueries[$assocId];
					else {
						$query = $proofreadingQueries[$assocId] = $this->_createQuery(WORKFLOW_STAGE_ID_PRODUCTION, $assocId, 2, 'Proofreading', $dateNotified);
					}
					break;
			}
			assert(isset($query)); // We've created or looked up a query.

			$assignedUserIds = array($userId);
			foreach (array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT) as $roleId) {
				$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($assocId, $roleId, $query->getStageId());
				while ($stageAssignment = $stageAssignments->next()) {
					$assignedUserIds[] = $stageAssignment->getUserId();
				}
			}

			// Ensure that the necessary users are assigned to the query
			foreach (array_unique($assignedUserIds) as $assignedUserId) {
				if (count($queryDao->getParticipantIds($query->getId(), $assignedUserId))!=0) continue;
				$queryDao->insertParticipant($query->getId(), $assignedUserId);
			}

			$submissionFiles = $submissionFileDao->getAllRevisions($fileId);
			foreach((array) $submissionFiles as $submissionFile) {
				$submissionFile->setAssocType(ASSOC_TYPE_NOTE);
				$submissionFile->setAssocId($query->getHeadNote()->getId());
				$submissionFile->setFileStage(SUBMISSION_FILE_QUERY);
				$submissionFileDao->updateObject($submissionFile);
			}
		}
		$signoffsResult->Close();

		// Migrate related notes into the queries
		$commentsResult = $submissionFileDao->retrieve('SELECT * FROM submission_comments WHERE comment_type IN (3, 4, 5)');
		while (!$commentsResult->EOF) {
			$row = $commentsResult->getRowAssoc(false);
			$commentsResult->MoveNext();

			$note = $noteDao->newDataObject();
			$note->setAssocType(ASSOC_TYPE_QUERY);
			$note->setDateCreated(strtotime($row['date_posted']));
			$note->setDateModified(strtotime($row['date_modified']));
			switch ($row['comment_type']) {
				case 3: // COMMENT_TYPE_COPYEDIT
					$note->setTitle('Copyediting');
					if (!isset($copyeditingQueries[$row['submission_id']])) {
						$copyeditingQueries[$row['submission_id']] = $this->_createQuery(WORKFLOW_STAGE_ID_EDITING, $row['submission_id'], 1, 'Copyediting');
					}
					$note->setAssocId($copyeditingQueries[$row['submission_id']]->getId());
					break;
				case 4: // COMMENT_TYPE_LAYOUT
					$note->setTitle('Layout Editing');
					if (!isset($layoutQueries[$row['submission_id']])) {
						$layoutQueries[$row['submission_id']] = $this->_createQuery(WORKFLOW_STAGE_ID_PRODUCTION, $row['submission_id'], 1, 'Layout Editing');
					}
					$note->setAssocId($layoutQueries[$row['submission_id']]->getId());
					break;
				case 5: // COMMENT_TYPE_PROOFREAD
					$note->setTitle('Proofreading');
					if (!isset($proofreadingQueries[$row['submission_id']])) {
						$proofreadingQueries[$row['submission_id']] = $this->_createQuery(WORKFLOW_STAGE_ID_PRODUCTION, $row['submission_id'], 2, 'Proofreading');
					}
					$note->setAssocId($proofreadingQueries[$row['submission_id']]->getId());
					break;
			}
			$note->setContents(nl2br($row['comments']));
			$note->setUserId($row['author_id']);
			$noteDao->insertObject($note);
		}
		$commentsResult->Close();

		$submissionFileDao->update('DELETE FROM submission_comments WHERE comment_type IN (3, 4, 5)'); // COMMENT_TYPE_EDITOR_DECISION
		return true;
	}

	/**
	 * Convert editor decision notes to a query.
	 * @return boolean True indicates success.
	 */
	function convertEditorDecisionNotes() {
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

		$commentsResult = $noteDao->retrieve('SELECT sc.*, a.user_id FROM submission_comments sc, articles_migration a WHERE sc.submission_id = a.article_id AND sc.comment_type=2 ORDER BY sc.submission_id, sc.comment_id ASC'); // COMMENT_TYPE_EDITOR_DECISION
		$submissionId = 0;
		$query = null; // Avoid Scrutinizer warnings
		while (!$commentsResult->EOF) {
			$row = $commentsResult->getRowAssoc(false);
			$commentsResult->MoveNext();

			if ($submissionId != $row['submission_id']) {
				$submissionId = $row['submission_id'];
				$query = $queryDao->newDataObject();
				$query->setAssocType(ASSOC_TYPE_SUBMISSION);
				$query->setAssocId($submissionId);
				$query->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
				$query->setSequence(REALLY_BIG_NUMBER);
				$queryDao->insertObject($query);
				$queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submissionId);

				$assignedUserIds = array($row['user_id']);
				foreach (array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT) as $roleId) {
					$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, $roleId, $query->getStageId());
					while ($stageAssignment = $stageAssignments->next()) {
						$assignedUserIds[] = $stageAssignment->getUserId();
					}
				}

				// Ensure that the necessary users are assigned to the query
				foreach (array_unique($assignedUserIds) as $assignedUserId) {
					if (count($queryDao->getParticipantIds($query->getId(), $assignedUserId))!=0) continue;
					$queryDao->insertParticipant($query->getId(), $assignedUserId);
				}
			}

			$note = $noteDao->newDataObject();
			$note->setAssocType(ASSOC_TYPE_QUERY);
			$note->setAssocId($query->getId());
			$note->setContents(nl2br($row['comments']));
			$note->setTitle('Editor Decision');
			$note->setDateCreated(strtotime($row['date_posted']));
			$note->setDateModified(strtotime($row['date_modified']));
			$note->setUserId($row['author_id']);
			$noteDao->insertObject($note);
		}
		$commentsResult->Close();

		$noteDao->update('DELETE FROM submission_comments WHERE comment_type=2'); // COMMENT_TYPE_EDITOR_DECISION
		return true;
	}

	/**
	 * Convert comments to editors to queries.
	 * @return boolean True indicates success.
	 */
	function convertCommentsToEditor() {
		$submissionDao = Application::getSubmissionDAO();
		$stageAssignmetDao = DAORegistry::getDAO('StageAssignmentDAO');
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		import('lib.pkp.classes.security.Role'); // ROLE_ID_...

		$commentsResult = $submissionDao->retrieve(
			'SELECT s.submission_id, s.context_id, s.comments_to_ed, s.date_submitted
			FROM submissions_tmp s
			WHERE s.comments_to_ed IS NOT NULL AND s.comments_to_ed != \'\''
		);
		while (!$commentsResult->EOF) {
			$row = $commentsResult->getRowAssoc(false);
			$comments_to_ed = PKPString::stripUnsafeHtml($row['comments_to_ed']);
			if ($comments_to_ed != ""){
				$userId = null;
				$authorAssignmentsResult = $stageAssignmetDao->getBySubmissionAndRoleId($row['submission_id'], ROLE_ID_AUTHOR);
				if ($authorAssignmentsResult->getCount() != 0) {
					// We assume the results are ordered by stage_assignment_id i.e. first author assignemnt is first
					$userId = $authorAssignmentsResult->next()->getUserId();
				} else {
					$managerUserGroup = $userGroupDao->getDefaultByRoleId($row['context_id'], ROLE_ID_MANAGER);
					$managerUsers = $userGroupDao->getUsersById($managerUserGroup->getId(), $row['context_id']);
					$userId = $managerUsers->next()->getId();
				}
				assert($userId);

				$query = $queryDao->newDataObject();
				$query->setAssocType(ASSOC_TYPE_SUBMISSION);
				$query->setAssocId($row['submission_id']);
				$query->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
				$query->setSequence(REALLY_BIG_NUMBER);

				$queryDao->insertObject($query);
				$queryDao->resequence(ASSOC_TYPE_SUBMISSION, $row['submission_id']);
				$queryDao->insertParticipant($query->getId(), $userId);

				$queryId = $query->getId();

				$note = $noteDao->newDataObject();
				$note->setUserId($userId);
				$note->setAssocType(ASSOC_TYPE_QUERY);
				$note->setTitle('Comments for the Editor');
				$note->setContents($comments_to_ed);
				$note->setDateCreated(strtotime($row['date_submitted']));
				$note->setDateModified(strtotime($row['date_submitted']));
				$note->setAssocId($queryId);
				$noteDao->insertObject($note);
			}
			$commentsResult->MoveNext();
		}
		$commentsResult->Close();

		// remove temporary table
		$submissionDao->update('DROP TABLE submissions_tmp');

		return true;
	}


	/**
	 * Localize issue cover images.
	 * @return boolean True indicates success.
	 */
	function localizeIssueCoverImages() {
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$publicFileManager = new PublicFileManager();
		// remove strange old cover images with array values in the DB - from 3.alpha or 3.beta?
		$issueDao->update('DELETE FROM issue_settings WHERE setting_name = \'coverImage\' AND setting_type = \'object\'');

		// remove empty 3.0 cover images
		$issueDao->update('DELETE FROM issue_settings WHERE setting_name = \'coverImage\' AND locale = \'\' AND setting_value = \'\'');
		$issueDao->update('DELETE FROM issue_settings WHERE setting_name = \'coverImageAltText\' AND locale = \'\' AND setting_value = \'\'');

		// get cover image duplicates, from 2.4.x and 3.0
		$result = $issueDao->retrieve(
			'SELECT DISTINCT iss1.issue_id, iss1.setting_value, i.journal_id
			FROM issue_settings iss1
			LEFT JOIN issues i ON (i.issue_id = iss1.issue_id)
			JOIN issue_settings iss2 ON (iss2.issue_id = iss1.issue_id AND iss2.setting_name = \'coverImage\')
			WHERE iss1.setting_name = \'fileName\''
		);
		// remove the old 2.4.x cover images, for which a new cover image exists
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$oldFileName = $row['setting_value'];
			if ($publicFileManager->fileExists($publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['journal_id']) . '/' . $oldFileName)) {
				$publicFileManager->removeJournalFile($row['journal_id'], $oldFileName);
			}
			$issueDao->update('DELETE FROM issue_settings WHERE issue_id = ? AND setting_name = \'fileName\' AND setting_value = ?', array((int) $row['issue_id'], $oldFileName));
			$result->MoveNext();
		}
		$result->Close();

		// retrieve names for unlocalized issue cover images
		$result = $issueDao->retrieve(
			'SELECT iss.issue_id, iss.setting_value, j.journal_id, j.primary_locale
			FROM issue_settings iss, issues i, journals j
			WHERE iss.setting_name = \'coverImage\' AND iss.locale = \'\'
				AND i.issue_id = iss.issue_id AND j.journal_id = i.journal_id'
		);
		// for all unlocalized issue cover images
		// rename (copy + remove) the cover images files in the public folder,
		// considereing the locale (using the journal primary locale)
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$oldFileName = $row['setting_value'];
			$newFileName = str_replace('.', '_' . $row['primary_locale'] . '.', $oldFileName);
			if ($publicFileManager->fileExists($publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['journal_id']) . '/' . $oldFileName)) {
				$publicFileManager->copyJournalFile($row['journal_id'], $publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['journal_id']) . '/' . $oldFileName, $newFileName);
				$publicFileManager->removeJournalFile($row['journal_id'], $oldFileName);
			}
			$result->MoveNext();
		}
		$result->Close();
		$driver = $issueDao->getDriver();
		switch ($driver) {
			case 'mysql':
			case 'mysqli':
				// Update cover image names in the issue_settings table
				$issueDao->update(
					'UPDATE issue_settings iss, issues i, journals j
					SET iss.locale = j.primary_locale, iss.setting_value = CONCAT(LEFT( iss.setting_value, LOCATE(\'.\', iss.setting_value) - 1 ), \'_\', j.primary_locale, \'.\', SUBSTRING_INDEX(iss.setting_value,\'.\',-1))
					WHERE iss.setting_name = \'coverImage\' AND iss.locale = \'\' AND i.issue_id = iss.issue_id AND j.journal_id = i.journal_id'
				);
				// Update cover image alt texts in the issue_settings table
				$issueDao->update(
					'UPDATE issue_settings iss, issues i, journals j SET iss.locale = j.primary_locale WHERE iss.setting_name = \'coverImageAltText\' AND iss.locale = \'\' AND i.issue_id = iss.issue_id AND j.journal_id = i.journal_id'
				);
				break;
			case 'postgres':
				// Update cover image names in the issue_settings table
				$issueDao->update(
					'UPDATE issue_settings
					SET locale = j.primary_locale, setting_value = REGEXP_REPLACE(issue_settings.setting_value, \'[\.]\', CONCAT(\'_\', j.primary_locale, \'.\'))
					FROM issues i, journals j
					WHERE issue_settings.setting_name = \'coverImage\' AND issue_settings.locale = \'\' AND i.issue_id = issue_settings.issue_id AND j.journal_id = i.journal_id'
				);
				// Update cover image alt texts in the issue_settings table
				$issueDao->update(
					'UPDATE issue_settings
					SET locale = j.primary_locale
					FROM issues i, journals j
					WHERE issue_settings.setting_name = \'coverImageAltText\' AND issue_settings.locale = \'\' AND i.issue_id = issue_settings.issue_id AND j.journal_id = i.journal_id'
				);
				break;
			default: fatalError('Unknown database type!');
		}
		$issueDao->flushCache();
		return true;
	}

	/**
	 * Localize article cover images.
	 * @return boolean True indicates success.
	 */
	function localizeArticleCoverImages() {
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$publicFileManager = new PublicFileManager();
		// remove strange old cover images with array values in the DB - from 3.alpha or 3.beta?
		$articleDao->update('DELETE FROM submission_settings WHERE setting_name = \'coverImage\' AND setting_type = \'object\'');

		// remove empty 3.0 cover images
		$articleDao->update('DELETE FROM submission_settings WHERE setting_name = \'coverImage\' AND locale = \'\' AND setting_value = \'\'');
		$articleDao->update('DELETE FROM submission_settings WHERE setting_name = \'coverImageAltText\' AND locale = \'\' AND setting_value = \'\'');

		// get cover image duplicates, from 2.4.x and 3.0
		$result = $articleDao->retrieve(
			'SELECT DISTINCT ss1.submission_id, ss1.setting_value, s.context_id
			FROM submission_settings ss1
			LEFT JOIN submissions s ON (s.submission_id = ss1.submission_id)
			JOIN submission_settings ss2 ON (ss2.submission_id = ss1.submission_id AND ss2.setting_name = \'coverImage\')
			WHERE ss1.setting_name = \'fileName\''
		);
		// remove the old 2.4.x cover images, for which a new cover image exists
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$submissionId = $row['submission_id'];
			$oldFileName = $row['setting_value'];
			if ($publicFileManager->fileExists($publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['context_id']) . '/' . $oldFileName)) {
				$publicFileManager->removeJournalFile($row['journal_id'], $oldFileName);
			}
			$articleDao->update('DELETE FROM submission_settings WHERE submission_id = ? AND setting_name = \'fileName\' AND setting_value = ?', array((int) $submissionId, $oldFileName));
			$result->MoveNext();
		}
		$result->Close();

		// retrieve names for unlocalized article cover images
		$result = $articleDao->retrieve(
			'SELECT ss.submission_id, ss.setting_value, j.journal_id, j.primary_locale
			FROM submission_settings ss, submissions s, journals j
			WHERE ss.setting_name = \'coverImage\' AND ss.locale = \'\'
				AND s.submission_id = ss.submission_id AND j.journal_id = s.context_id'
		);
		// for all unlocalized article cover images
		// rename (copy + remove) the cover images files in the public folder,
		// considereing the locale (using the journal primary locale)
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$oldFileName = $row['setting_value'];
			$newFileName = str_replace('.', '_' . $row['primary_locale'] . '.', $oldFileName);
			if ($publicFileManager->fileExists($publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['journal_id']) . '/' . $oldFileName)) {
				$publicFileManager->copyJournalFile($row['journal_id'], $publicFileManager->getContextFilesPath(ASSOC_TYPE_JOURNAL, $row['journal_id']) . '/' . $oldFileName, $newFileName);
				$publicFileManager->removeJournalFile($row['journal_id'], $oldFileName);
			}
			$result->MoveNext();
		}
		$result->Close();
		$driver = $articleDao->getDriver();
		switch ($driver) {
			case 'mysql':
			case 'mysqli':
				// Update cover image names in the submission_settings table
				$articleDao->update(
					'UPDATE submission_settings ss, submissions s, journals j
					SET ss.locale = j.primary_locale, ss.setting_value = CONCAT(LEFT( ss.setting_value, LOCATE(\'.\', ss.setting_value) - 1 ), \'_\', j.primary_locale, \'.\', SUBSTRING_INDEX(ss.setting_value,\'.\',-1))
					WHERE ss.setting_name = \'coverImage\' AND ss.locale = \'\' AND s.submission_id = ss.submission_id AND j.journal_id = s.context_id'
				);
				// Update cover image alt texts in the submission_settings table
				$articleDao->update(
					'UPDATE submission_settings ss, submissions s, journals j
					SET ss.locale = j.primary_locale
					WHERE ss.setting_name = \'coverImageAltText\' AND ss.locale = \'\' AND s.submission_id = ss.submission_id AND j.journal_id = s.context_id'
				);
				break;
			case 'postgres':
				// Update cover image names in the submission_settings table
				$articleDao->update(
					'UPDATE submission_settings
					SET locale = j.primary_locale, setting_value = REGEXP_REPLACE(submission_settings.setting_value, \'[\.]\', CONCAT(\'_\', j.primary_locale, \'.\'))
					FROM submissions s, journals j
					WHERE submission_settings.setting_name = \'coverImage\' AND submission_settings.locale = \'\' AND s.submission_id = submission_settings.submission_id AND j.journal_id = s.context_id'
				);
				// Update cover image alt texts in the submission_settings table
				$articleDao->update(
					'UPDATE submission_settings
					SET locale = j.primary_locale
					FROM submissions s, journals j
					WHERE submission_settings.setting_name = \'coverImageAltText\' AND submission_settings.locale = \'\' AND s.submission_id = submission_settings.submission_id AND j.journal_id = s.context_id'
				);
				break;
			default: fatalError('Unknown database type!');
		}
		$articleDao->flushCache();
		return true;
	}
}

?>
