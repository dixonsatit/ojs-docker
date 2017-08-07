<?php

/**
 * @file classes/core/Application.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Application
 * @ingroup core
 * @see PKPApplication
 *
 * @brief Class describing this application.
 *
 */

import('lib.pkp.classes.core.PKPApplication');

define('PHP_REQUIRED_VERSION', '5.2.0');
define('REQUIRES_XSL', false);

define('ASSOC_TYPE_ARTICLE',		ASSOC_TYPE_SUBMISSION);
define('ASSOC_TYPE_PUBLISHED_ARTICLE',	ASSOC_TYPE_PUBLISHED_SUBMISSION);
define('ASSOC_TYPE_GALLEY',		ASSOC_TYPE_REPRESENTATION);

define('ASSOC_TYPE_JOURNAL',		0x0000100);
define('ASSOC_TYPE_ISSUE',		0x0000103);
define('ASSOC_TYPE_ISSUE_GALLEY',	0x0000105);

define('CONTEXT_JOURNAL', 1);

class Application extends PKPApplication {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Get the "context depth" of this application, i.e. the number of
	 * parts of the URL after index.php that represent the context of
	 * the current request (e.g. Journal [1], or Conference and
	 * Scheduled Conference [2]).
	 * @return int
	 */
	function getContextDepth() {
		return 1;
	}

	/**
	 * Get the list of context elements.
	 * @return array
	 */
	function getContextList() {
		return array('journal');
	}

	/**
	 * Get the symbolic name of this application
	 * @return string
	 */
	function getName() {
		return 'ojs2';
	}

	/**
	 * Get the locale key for the name of this application.
	 * @return string
	 */
	function getNameKey() {
		return('common.openJournalSystems');
	}

	/**
	 * Get the URL to the XML descriptor for the current version of this
	 * application.
	 * @return string
	 */
	function getVersionDescriptorUrl() {
		return('http://pkp.sfu.ca/ojs/xml/ojs-version.xml');
	}

	/**
	 * Get the map of DAOName => full.class.Path for this application.
	 * @return array
	 */
	function getDAOMap() {
		return array_merge(parent::getDAOMap(), array(
			'SubmissionCommentDAO' => 'lib.pkp.classes.submission.SubmissionCommentDAO',
			'ArticleDAO' => 'classes.article.ArticleDAO',
			'ArticleGalleyDAO' => 'classes.article.ArticleGalleyDAO',
			'ArticleSearchDAO' => 'classes.search.ArticleSearchDAO',
			'AuthorDAO' => 'classes.article.AuthorDAO',
			'EmailTemplateDAO' => 'classes.mail.EmailTemplateDAO',
			'GiftDAO' => 'classes.gift.GiftDAO',
			'IndividualSubscriptionDAO' => 'classes.subscription.IndividualSubscriptionDAO',
			'InstitutionalSubscriptionDAO' => 'classes.subscription.InstitutionalSubscriptionDAO',
			'IssueDAO' => 'classes.issue.IssueDAO',
			'IssueGalleyDAO' => 'classes.issue.IssueGalleyDAO',
			'IssueFileDAO' => 'classes.issue.IssueFileDAO',
			'JournalDAO' => 'classes.journal.JournalDAO',
			'JournalSettingsDAO' => 'classes.journal.JournalSettingsDAO',
			'MetricsDAO' => 'classes.statistics.MetricsDAO',
			'OAIDAO' => 'classes.oai.ojs.OAIDAO',
			'OJSCompletedPaymentDAO' => 'classes.payment.ojs.OJSCompletedPaymentDAO',
			'PublishedArticleDAO' => 'classes.article.PublishedArticleDAO',
			'QueuedPaymentDAO' => 'lib.pkp.classes.payment.QueuedPaymentDAO',
			'ReviewAssignmentDAO' => 'lib.pkp.classes.submission.reviewAssignment.ReviewAssignmentDAO',
			'ReviewerSubmissionDAO' => 'classes.submission.reviewer.ReviewerSubmissionDAO',
			'RoleDAO' => 'classes.security.RoleDAO',
			'ScheduledTaskDAO' => 'lib.pkp.classes.scheduledTask.ScheduledTaskDAO',
			'SectionDAO' => 'classes.journal.SectionDAO',
			'StageAssignmentDAO' => 'lib.pkp.classes.stageAssignment.StageAssignmentDAO',
			'SubmissionEventLogDAO' => 'classes.log.SubmissionEventLogDAO',
			'SubmissionFileDAO' => 'classes.article.SubmissionFileDAO',
			'SubscriptionDAO' => 'classes.subscription.SubscriptionDAO',
			'SubscriptionTypeDAO' => 'classes.subscription.SubscriptionTypeDAO',
			'UserGroupAssignmentDAO' => 'lib.pkp.classes.security.UserGroupAssignmentDAO',
			'UserDAO' => 'classes.user.UserDAO',
			'UserSettingsDAO' => 'classes.user.UserSettingsDAO'
		));
	}

	/**
	 * Get the list of plugin categories for this application.
	 * @return array
	 */
	function getPluginCategories() {
		return array(
			// NB: Meta-data plug-ins are first in the list as this
			// will make them load (and install) first.
			// This is necessary as several other plug-in categories
			// depend on meta-data. This is a very rudimentary type of
			// dependency management for plug-ins.
			'metadata',
			'auth',
			'blocks',
			// NB: 'citationFormats' is an obsolete category for backwards
			// compatibility only. This will be replaced by 'citationOutput',
			// see #5156.
			'citationFormats',
			'citationLookup',
			'citationOutput',
			'citationParser',
			'gateways',
			'generic',
			'importexport',
			'oaiMetadataFormats',
			'paymethod',
			'pubIds',
			'reports',
			'themes'
		);
	}

	/**
	 * Get the top-level context DAO.
	 * @return ContextDAO
	 */
	static function getContextDAO() {
		return DAORegistry::getDAO('JournalDAO');
	}

	/**
	 * Get the submission DAO.
	 * @return SubmissionDAO
	 */
	static function getSubmissionDAO() {
		return DAORegistry::getDAO('ArticleDAO');
	}

	/**
	 * Get the section DAO.
	 * @return SectionDAO
	 */
	static function getSectionDAO() {
		return DAORegistry::getDAO('SectionDAO');
	}

	/**
	 * Get the representation DAO.
	 * @return RepresentationDAO
	 */
	static function getRepresentationDAO() {
		return DAORegistry::getDAO('ArticleGalleyDAO');
	}

	/**
	 * Returns the name of the context column in plugin_settings
	 * @return string
	 */
	static function getPluginSettingsContextColumnName() {
		if (defined('SESSION_DISABLE_INIT')) {
			$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
			$driver = $pluginSettingsDao->getDriver();
			switch ($driver) {
				case 'mysql':
				case 'mysqli':
					$checkResult = $pluginSettingsDao->retrieve('SHOW COLUMNS FROM plugin_settings LIKE ?', array('context_id'));
					if ($checkResult->NumRows() == 0) {
						return 'journal_id';
					}
					break;
				case 'postgres':
					$checkResult = $pluginSettingsDao->retrieve('SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?', array('plugin_settings', 'context_id'));
					if ($checkResult->NumRows() == 0) {
						return 'journal_id';
					}
					break;
				default: fatalError('Unknown database type!');
			}
		}
		return 'context_id';
	}

	/**
	 * Get the stages used by the application.
	 * @return array
	 */
	static function getApplicationStages() {
		// We leave out WORKFLOW_STAGE_ID_PUBLISHED since it technically is not a 'stage'.
		return array(
				WORKFLOW_STAGE_ID_SUBMISSION,
				WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
				WORKFLOW_STAGE_ID_EDITING,
				WORKFLOW_STAGE_ID_PRODUCTION
		);
	}

	/**
	 * Returns the context type for this application.
	 * @return int ASSOC_TYPE_...
	 */
	static function getContextAssocType() {
		return ASSOC_TYPE_JOURNAL;
	}

	/**
	 * Get the file directory array map used by the application.
	 */
	static function getFileDirectories() {
		return array('context' => '/journals/', 'submission' => '/articles/');
	}
}

?>
