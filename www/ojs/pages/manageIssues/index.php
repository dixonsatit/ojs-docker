<?php

/**
 * @defgroup pages_manageIssues Issue Management Pages
 */

/**
 * @file pages/manageIssues/index.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_editor
 * @brief Handle requests for issue management functions.
 *
 */

switch ($op) {
	//
	// Issue
	//
	case 'index':
		define('HANDLER_CLASS', 'ManageIssuesHandler');
		import('pages.manageIssues.ManageIssuesHandler');
		break;
}

?>
