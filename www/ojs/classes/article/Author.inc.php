<?php

/**
 * @file classes/article/Author.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Author
 * @ingroup article
 * @see AuthorDAO
 *
 * @brief Article author metadata class.
 */


import('lib.pkp.classes.submission.PKPAuthor');

class Author extends PKPAuthor {
	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Get/set methods
	//

	/**
	 * Get the localized competing interests statement for this author
	 */
	function getLocalizedCompetingInterests() {
		return $this->getLocalizedData('competingInterests');
	}

	/**
	 * Get author competing interests.
	 * @param $locale string
	 * @return string
	 */
	function getCompetingInterests($locale) {
		return $this->getData('competingInterests', $locale);
	}

	/**
	 * Set author competing interests.
	 * @param $competingInterests string
	 * @param $locale string
	 */
	function setCompetingInterests($competingInterests, $locale) {
		return $this->setData('competingInterests', $competingInterests, $locale);
	}
}

?>
