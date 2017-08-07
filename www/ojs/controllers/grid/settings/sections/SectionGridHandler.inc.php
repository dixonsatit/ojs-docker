<?php

/**
 * @file controllers/grid/settings/sections/SectionGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SectionGridHandler
 * @ingroup controllers_grid_settings_section
 *
 * @brief Handle section grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('controllers.grid.settings.sections.SectionGridRow');

class SectionGridHandler extends SetupGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow', 'addSection', 'editSection', 'updateSection', 'deleteSection', 'saveSequence')
		);
	}


	//
	// Overridden template methods
	//
	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);
		$journal = $request->getJournal();

		// FIXME are these all required?
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_APP_COMMON
		);

		// Set the grid title.
		$this->setTitle('section.sections');

		// Elements to be displayed in the grid
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
		$sectionIterator = $sectionDao->getByJournalId($journal->getId());

		$gridData = array();
		while ($section = $sectionIterator->next()) {
			// Get the section editors data for the row
			$assignedSubEditors = $subEditorsDao->getBySectionId($section->getId(), $journal->getId());
			if(empty($assignedSubEditors)) {
				$editorsString = __('common.none');
			} else {
				$editors = array();
				foreach ($assignedSubEditors as $subEditor) {
					$editors[] = $subEditor->getLastName();
				}
				$editorsString = implode(', ', $editors);
			}

			$sectionId = $section->getId();
			$gridData[$sectionId] = array(
				'title' => $section->getLocalizedTitle(),
				'editors' => $editorsString,
				'seq' => $section->getSequence()
			);
		}
		uasort($gridData, create_function('$a,$b', 'return $a[\'seq\']-$b[\'seq\'];'));

		$this->setGridDataElements($gridData);

		// Add grid-level actions
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addSection',
				new AjaxModal(
					$router->url($request, null, null, 'addSection', null, array('gridId' => $this->getId())),
					__('manager.sections.create'),
					'modal_manage'
				),
				__('manager.sections.create'),
				'add_section'
			)
		);

		// Columns
		$this->addColumn(
			new GridColumn(
				'title',
				'common.title'
			)
		);
		$this->addColumn(new GridColumn('editors', 'user.role.editors'));
	}

	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		return array(new OrderGridItemsFeature());
	}

	/**
	 * Get the row handler - override the default row handler
	 * @return SectionGridRow
	 */
	protected function getRowInstance() {
		return new SectionGridRow();
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($row) {
		return $row['seq'];
	}

	/**
	 * @copydoc GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$journal = $request->getJournal();
		$section = $sectionDao->getById($rowId, $journal->getId());
		$section->setSequence($newSequence);
		$sectionDao->updateObject($section);
	}

	//
	// Public Section Grid Actions
	//
	/**
	 * An action to add a new section
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addSection($args, $request) {
		// Calling editSection with an empty ID will add
		// a new section.
		return $this->editSection($args, $request);
	}

	/**
	 * An action to edit a section
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 * @return JSONMessage JSON object
	 */
	function editSection($args, $request) {
		$sectionId = isset($args['sectionId']) ? $args['sectionId'] : null;
		$this->setupTemplate($request);

		import('controllers.grid.settings.sections.form.SectionForm');
		$sectionForm = new SectionForm($request, $sectionId);
		$sectionForm->initData($args, $request);
		return new JSONMessage(true, $sectionForm->fetch($request));
	}

	/**
	 * Update a section
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateSection($args, $request) {
		$sectionId = $request->getUserVar('sectionId');

		import('controllers.grid.settings.sections.form.SectionForm');
		$sectionForm = new SectionForm($request, $sectionId);
		$sectionForm->readInputData();

		if ($sectionForm->validate()) {
			$sectionForm->execute($args, $request);
			return DAO::getDataChangedEvent($sectionForm->getSectionId());
		}
		return new JSONMessage(false);
	}

	/**
	 * Delete a section
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteSection($args, $request) {
		$journal = $request->getJournal();

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById(
			$request->getUserVar('sectionId'),
			$journal->getId()
		);

		if ($section && $request->checkCSRF()) {
			$sectionDao->deleteObject($section);
			return DAO::getDataChangedEvent($section->getId());
		}
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER); // manager.setup.errorDeletingItem
		return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
	}
}

?>
