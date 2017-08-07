<?php

/**
 * @file controllers/grid/articleGalleys/ArticleGalleyGridCellProvider.inc.php
 *
 * Copyright (c) 2016-2017 Simon Fraser University Library
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArticleGalleyGridCellProvider
 * @ingroup controllers_grid_articleGalleys
 *
 * @brief Base class for a cell provider for article galleys.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class ArticleGalleyGridCellProvider extends DataObjectGridCellProvider {

	/** @var Submission **/
	var $_submission;

	/**
	 * Constructor
	 * @param $submission Submission
	 */
	function __construct($submission) {
		parent::__construct();
		$this->_submission = $submission;
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));

		switch ($columnId) {
			case 'label':
				return array(
					'label' => ($element->getRemoteUrl()=='' && $element->getFileId())?'':$element->getLabel()
				);
				break;
			default: assert(false);
		}
		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * Get request arguments.
	 * @param $row GridRow
	 * @return array
	 */
	function getRequestArgs($row) {
		return array(
			'submissionId' => $this->_submission->getId(),
		);
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column) {
		switch ($column->getId()) {
			case 'label':
				$element = $row->getData();
				if ($element->getRemoteUrl() != '' || !$element->getFileId()) break;

				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				import('lib.pkp.classes.submission.SubmissionFile');
				$submissionFile = $submissionFileDao->getLatestRevision(
					$element->getFileId(),
					null,
					$element->getSubmissionId()
				);
				import('lib.pkp.controllers.api.file.linkAction.DownloadFileLinkAction');
				return array(new DownloadFileLinkAction($request, $submissionFile, $request->getUserVar('stageId'), $element->getLabel()));
		}
		return parent::getCellActions($request, $row, $column);
	}
}

?>
