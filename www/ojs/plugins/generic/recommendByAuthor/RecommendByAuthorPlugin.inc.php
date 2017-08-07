<?php

/**
 * @file plugins/generic/recommendByAuthor/RecommendByAuthorPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RecommendByAuthorPlugin
 * @ingroup plugins_generic_recommendByAuthor
 *
 * @brief Plugin to recommend articles from the same author.
 */


import('lib.pkp.classes.plugins.GenericPlugin');

define('RECOMMEND_BY_AUTHOR_PLUGIN_COUNT', 10);

class RecommendByAuthorPlugin extends GenericPlugin {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}


	//
	// Implement template methods from Plugin.
	//
	/**
	 * @see Plugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;

		if ($success && $this->getEnabled()) {
			HookRegistry::register('Templates::Article::Footer::PageFooter', array($this, 'callbackTemplateArticlePageFooter'));
		}
		return $success;
	}

	/**
	 * @see Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.recommendByAuthor.displayName');
	}

	/**
	 * @see Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.recommendByAuthor.description');
	}

	/**
	 * @copydoc Plugin::getTemplatePath()
	 */
	function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore) . 'templates/';
	}


	//
	// View level hook implementations.
	//
	/**
	 * @see templates/article/footer.tpl
	 */
	function callbackTemplateArticlePageFooter($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];

		// Find articles of the same author(s).
		$displayedArticle = $smarty->get_template_vars('article');
		$authors = $displayedArticle->getAuthors();
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$foundArticles = array();
		foreach($authors as $author) { /* @var $author Author */
			// The following article search is by name only as authors are
			// not normalized in OJS. This is rather crude and may produce
			// false positives or miss some entries. But there's no other way
			// until OJS allows users to consistently normalize authors (via name,
			// email, ORCID, whatever).
			$articles = $authorDao->getPublishedArticlesForAuthor(
				null, $author->getFirstName(), $author->getMiddleName(),
				$author->getLastName(), $author->getLocalizedAffiliation(),
				$author->getCountry()
			);
			foreach ($articles as $article) { /* @var $article PublishedArticle */
				if ($displayedArticle->getId() == $article->getId()) continue;
				$foundArticles[] = $article->getId();
			}
		}
		$results = array_unique($foundArticles);

		// Order results by metric.
		$application = PKPApplication::getApplication();
		$metricType = $application->getDefaultMetricType();
		if (empty($metricType)) $smarty->assign('noMetricSelected', true);
		$column = STATISTICS_DIMENSION_ARTICLE_ID;
		$filter = array(
				STATISTICS_DIMENSION_ASSOC_TYPE => array(ASSOC_TYPE_GALLEY, ASSOC_TYPE_ARTICLE),
				STATISTICS_DIMENSION_ARTICLE_ID => array($results)
		);
		$orderBy = array(STATISTICS_METRIC => STATISTICS_ORDER_DESC);
		$statsReport = $application->getMetrics($metricType, $column, $filter, $orderBy);
		$orderedResults = array();
		foreach ($statsReport as $reportRow) {
			$orderedResults[] = $reportRow['submission_id'];
		}
		// Make sure we even get results that have no statistics (yet) and that
		// we get them in some consistent order for paging.
		$remainingResults = array_diff($results, $orderedResults);
		sort($remainingResults);
		$orderedResults = array_merge($orderedResults, $remainingResults);

		// Pagination.
		$request = PKPApplication::getRequest();
		$rangeInfo = Handler::getRangeInfo($request, 'articlesBySameAuthor');
		if ($rangeInfo && $rangeInfo->isValid()) {
			$page = $rangeInfo->getPage();
		} else {
			$page = 1;
		}
		$totalResults = count($orderedResults);
		$itemsPerPage = RECOMMEND_BY_AUTHOR_PLUGIN_COUNT;
		$offset = $itemsPerPage * ($page-1);
		$length = max($totalResults - $offset, 0);
		$length = min($itemsPerPage, $length);
		if ($length == 0) {
			$pagedResults = array();
		} else {
			$pagedResults = array_slice(
				$orderedResults,
				$offset,
				$length
			);
		}

		// Visualization.
		import('classes.search.ArticleSearch');
		$articleSearch = new ArticleSearch();
		$pagedResults = $articleSearch->formatResults($pagedResults);
		import('lib.pkp.classes.core.VirtualArrayIterator');
		$returner = new VirtualArrayIterator($pagedResults, $totalResults, $page, $itemsPerPage);
		$smarty->assign('articlesBySameAuthor', $returner);
		$output .= $smarty->fetch($this->getTemplatePath() . 'articleFooter.tpl');
		return false;
	}
}
?>
