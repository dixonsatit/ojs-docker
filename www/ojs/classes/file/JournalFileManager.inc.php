<?php

/**
 * @file classes/file/JournalFileManager.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JournalFileManager
 * @ingroup file
 *
 * @brief Class defining operations for private journal file management.
 */

import('lib.pkp.classes.file.BaseSubmissionFileManager');

class JournalFileManager extends BaseSubmissionFileManager {

	/** @var Journal the associated article */
	var $journal;

	/**
	 * Constructor.
	 * @param $journalId int
	 * @param $articleId int
	 */
	function __construct($journalId, $articleId) {
		parent::__construct($journalId, $articleId);
	}

	/**
	 * Get the base path for file storage
	 * @return string
	 */
	function getBasePath() {
		return parent::getBasePath() . '/journals/' . $this->contextId . '/';
	}

	function uploadFile($fileName, $destFileName) {
		return parent::uploadFile($fileName, $this->filesDir . $destFileName);
	}

	function downloadFile($filePath, $fileType, $inline = false) {
		return parent::downloadFile($this->filesDir . $filePath, $fileType, $inline);
	}

	function deleteFile($fileName) {
		return parent::deleteFile($this->filesDir . $fileName);
	}
}

?>
