<?php

/**
 * @file plugins/importexport/native/NativeImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief Class configuring the native import/export process to this
 * application's specifics.
 */

import('lib.pkp.plugins.importexport.native.PKPNativeImportExportDeployment');

class NativeImportExportDeployment extends PKPNativeImportExportDeployment {

	var $_issue;

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User
	 */
	function __construct($context, $user) {
		parent::__construct($context, $user);
	}

	//
	// Deploymenturation items for subclasses to override
	//
	/**
	 * Get the submission node name
	 * @return string
	 */
	function getSubmissionNodeName() {
		return 'article';
	}

	/**
	 * Get the submissions node name
	 * @return string
	 */
	function getSubmissionsNodeName() {
		return 'articles';
	}

	/**
	 * Get the representation node name
	 */
	function getRepresentationNodeName() {
		return 'article_galley';
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return 'native.xsd';
	}

	/**
	 * Set the import/export issue.
	 * @param $issue Issue
	 */
	function setIssue($issue) {
		$this->_issue = $issue;
		if ($issue) $this->addProcessedObjectId(ASSOC_TYPE_ISSUE, $issue->getId());
	}

	/**
	 * Get the import/export issue.
	 * @return Issue
	 */
	function getIssue() {
		return $this->_issue;
	}

	/**
	 * Remove the processed objects.
	 * @param $assocType integer ASSOC_TYPE_...
	 */
	function removeImportedObjects($assocType) {
		switch ($assocType) {
			case ASSOC_TYPE_ISSUE:
				$processedIssuesIds = $this->getProcessedObjectsIds(ASSOC_TYPE_ISSUE);
				if (!empty($processedIssuesIds)) {
					$issueDao = DAORegistry::getDAO('IssueDAO');
					foreach ($processedIssuesIds as $issueId => $errorMessages) {
						$issue = $issueDao->getById($issueId);
						$issueDao->deleteObject($issue);
					}
				}
				break;
			default:
				parent::removeImportedObjects($assocType);
		}
	}

}

?>
