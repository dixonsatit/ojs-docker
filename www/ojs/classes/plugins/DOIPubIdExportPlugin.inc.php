<?php

/**
 * @file classes/plugins/DOIPubIdExportPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DOIPubIdExportPlugin
 * @ingroup plugins
 *
 * @brief Basis class for DOI XML metadata export plugins
 */

import('classes.plugins.PubObjectsExportPlugin');

// Configuration errors.
define('DOI_EXPORT_CONFIG_ERROR_DOIPREFIX', 0x01);

// The name of the setting used to save the registered DOI.
define('DOI_EXPORT_REGISTERED_DOI', 'registeredDoi');

abstract class DOIPubIdExportPlugin extends PubObjectsExportPlugin {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success) {
			HookRegistry::register('AcronPlugin::parseCronTab', array($this, 'callbackParseCronTab'));
		}
		return $success;
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		parent::display($args, $request);
		$context = $request->getContext();
		switch (array_shift($args)) {
			case 'index':
			case '':
				parent::display($args, $request);
				$templateMgr = TemplateManager::getManager($request);
				// Check for configuration errors:
				$configurationErrors = $templateMgr->get_template_vars('configurationErrors');
				// missing DOI prefix
				$doiPrefix = $exportArticles = $exportIssues = null;
				$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
				if (isset($pubIdPlugins['doipubidplugin'])) {
					$doiPlugin = $pubIdPlugins['doipubidplugin'];
					$doiPrefix = $doiPlugin->getSetting($context->getId(), $doiPlugin->getPrefixFieldName());
					$exportArticles = $doiPlugin->getSetting($context->getId(), 'enableSubmissionDoi');
					$exportIssues = $doiPlugin->getSetting($context->getId(), 'enableIssueDoi');
					$exportRepresentations = $doiPlugin->getSetting($context->getId(), 'enableRepresentationDoi');
				}
				if (empty($doiPrefix)) {
					$configurationErrors[] = DOI_EXPORT_CONFIG_ERROR_DOIPREFIX;
				}
				$templateMgr->assign(array(
					'exportArticles' => $exportArticles,
					'exportIssues' => $exportIssues,
					'exportRepresentations' => $exportRepresentations,
				));
				$templateMgr->display($this->getTemplatePath() . 'index.tpl');
				break;
		}
	}

	/**
	 * Get pub ID type
	 * @return string
	 */
	function getPubIdType() {
		return 'doi';
	}

	/**
	 * Get pub ID display type
	 * @return string
	 */
	function getPubIdDisplayType() {
		return 'DOI';
	}

	/**
	 * Mark selected submissions or issues as registered.
	 * @param $context Context
	 * @param $objects array Array of published articles, issues or galleys
	 */
	function markRegistered($context, $objects) {
		foreach ($objects as $object) {
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
			$this->saveRegisteredDoi($context, $object);
		}
	}

	/**
	 * Saving object's DOI to the object's
	 * "registeredDoi" setting.
	 * We prefix the setting with the plugin's
	 * id so that we do not get name clashes
	 * when several DOI registration plug-ins
	 * are active at the same time.
	 * @param $context Context
	 * @param $object Issue|PublishedArticle|ArticleGalley
	 * @param $testPrefix string
	 */
	function saveRegisteredDoi($context, $object, $testPrefix = '10.1234') {
		$registeredDoi = $object->getStoredPubId('doi');
		assert(!empty($registeredDoi));
		if ($this->isTestMode($context)) {
			$registeredDoi = PKPString::regexp_replace('#^[^/]+/#', $testPrefix . '/', $registeredDoi);
		}
		$object->setData($this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI, $registeredDoi);
		$this->updateObject($object);
	}

	/**
	 * Hook callback that returns the
	 * "registeredDoi" setting's name prefixed with
	 * the plug-in's id to avoid name collisions.
	 * @see DAO::getAdditionalFieldNames()
	 * @param $hookName string
	 * @param $args array
	 */
	function getAdditionalFieldNames($hookName, $args) {
		parent::getAdditionalFieldNames($hookName, $args);
		$additionalFields =& $args[1];
		assert(is_array($additionalFields));
		$additionalFields[] = $this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI;
	}

	/**
	 * Retrieve all unregistered articles.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredArticles($context) {
		// Retrieve all published articles that have not yet been registered.
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO'); /* @var $publishedArticleDao PublishedArticleDAO */
		$articles = $publishedArticleDao->getExportable(
			$context->getId(),
			$this->getPubIdType(),
			null,
			null,
			null,
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		return $articles->toArray();
	}

	/**
	 * Retrieve all unregistered issues.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredIssues($context) {
		// Retrieve all issues that have not yet been registered.
		$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
		$issuesFactory = $issueDao->getExportable(
			$context->getId(),
			$this->getPubIdType(),
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		$issues = $issuesFactory->toArray();
		// Cache issues.
		$cache = $this->getCache();
		foreach ($issues as $issue) {
			$cache->add($issue, null);
			unset($issue);
		}
		return $issues;
	}

	/**
	 * Retrieve all unregistered articles.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredGalleys($context) {
		// Retrieve all galleys that have not yet been registered.
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO'); /* @var $galleyDao ArticleGalleyDAO */
		$galleys = $galleyDao->getExportable(
			$this->getPubIdType(),
			$context?$context->getId():null,
			null,
			null,
			null,
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		return $galleys->toArray();
	}

	/**
	 * Get published articles with a DOI asigned from submission IDs.
	 * @param $submissionIds array
	 * @param $context Context
	 * @return array
	 */
	function getPublishedArticles($submissionIds, $context) {
		$publishedArticles = array();
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		foreach ($submissionIds as $submissionId) {
			$publishedArticle = $publishedArticleDao->getPublishedArticleByArticleId($submissionId, $context->getId());
			if ($publishedArticle && $publishedArticle->getStoredPubId('doi')) $publishedArticles[] = $publishedArticle;
		}
		return $publishedArticles;
	}

	/**
	 * Get published issues with a DOI asigned  from issue IDs.
	 * @param $issueIds array
	 * @param $context Context
	 * @return array
	 */
	function getPublishedIssues($issueIds, $context) {
		$publishedIssues = array();
		$issueDao = DAORegistry::getDAO('IssueDAO');
		foreach ($issueIds as $issueId) {
			$publishedIssue = $issueDao->getById($issueId, $context->getId());
			if ($publishedIssue && $publishedIssue->getStoredPubId('doi')) $publishedIssues[] = $publishedIssue;
		}
		return $publishedIssues;
	}

	/**
	 * Get article galleys with a DOI assigned from gallley IDs.
	 * @param $galleyIds array
	 * @param $context Context
	 * @return array
	 */
	function getArticleGalleys($galleyIds, $context) {
		$galleys = array();
		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		foreach ($galleyIds as $galleyId) {
			$articleGalley = $articleGalleyDao->getById($galleyId, null, $context->getId());
			if ($articleGalley && $articleGalley->getStoredPubId('doi')) $galleys[] = $articleGalley;
		}
		return $galleys;
	}

}

?>
