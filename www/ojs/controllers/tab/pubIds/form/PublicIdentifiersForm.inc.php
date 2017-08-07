<?php

/**
 * @file controllers/tab/pubIds/form/PublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PublicIdentifiersForm
 * @ingroup controllers_tab_pubIds_form
 *
 * @brief Displays a pub ids form.
 */

import('lib.pkp.controllers.tab.pubIds.form.PKPPublicIdentifiersForm');

class PublicIdentifiersForm extends PKPPublicIdentifiersForm {

	/**
	 * Constructor.
	 * @param $pubObject object
	 * @param $stageId integer
	 * @param $formParams array
	 */
	function __construct($pubObject, $stageId = null, $formParams = null) {
		parent::__construct($pubObject, $stageId, $formParams);
	}

	/**
	 * Store objects with pub ids.
	 * @copydoc Form::execute()
	 */
	function execute($request) {
		parent::execute($request);
		$pubObject = $this->getPubObject();
		if (is_a($pubObject, 'Issue')) {
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issueDao->updateObject($pubObject);
		}
	}

	/**
	 * Clear issue objects pub ids.
	 * @param $pubIdPlugInClassName string
	 */
	function clearIssueObjectsPubIds($pubIdPlugInClassName) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				if (get_class($pubIdPlugin) == $pubIdPlugInClassName) {
					$pubIdPlugin->clearIssueObjectsPubIds($this->getPubObject());
				}
			}
		}
	}

	/**
	 * @copydoc PKPPublicIdentifiersForm::execute()
	 */
	function getAssocType($pubObject) {
		if (is_a($pubObject, 'Issue')) {
			return ASSOC_TYPE_ISSUE;
		}
		return parent::getAssocType($pubObject);
	}

}

?>
