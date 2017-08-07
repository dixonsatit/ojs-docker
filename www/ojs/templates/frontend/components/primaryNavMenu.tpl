{**
 * templates/frontend/components/primaryNavMenu.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Primary navigation menu list for OJS
 *}
<ul id="navigationPrimary" class="pkp_navigation_primary pkp_nav_list">

	{if $enableAnnouncements}
		<li>
			<a href="{url router=$smarty.const.ROUTE_PAGE page="announcement"}">
				{translate key="announcement.announcements"}
			</a>
		</li>
	{/if}

	{if $currentJournal}

		{if $currentJournal->getSetting('publishingMode') != $smarty.const.PUBLISHING_MODE_NONE}
			<li>
				<a href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="current"}">
					{translate key="navigation.current"}
				</a>
			</li>
			<li>
				<a href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}">
					{translate key="navigation.archives"}
				</a>
			</li>
		{/if}

		<li aria-haspopup="true" aria-expanded="false">
			<a href="{url router=$smarty.const.ROUTE_PAGE page="about"}">
				{translate key="navigation.about"}
			</a>
			<ul>
				<li>
					<a href="{url router=$smarty.const.ROUTE_PAGE page="about"}">
						{translate key="about.aboutContext"}
					</a>
				</li>
				{if $currentJournal->getLocalizedSetting('masthead')}
					<li>
						<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="editorialTeam"}">
							{translate key="about.editorialTeam"}
						</a>
					</li>
				{/if}
				<li>
					<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="submissions"}">
						{translate key="about.submissions"}
					</a>
				</li>
				{if $currentJournal->getSetting('mailingAddress') || $currentJournal->getSetting('contactName')}
					<li>
						<a href="{url router=$smarty.const.ROUTE_PAGE page="about" op="contact"}">
							{translate key="about.contact"}
						</a>
					</li>
				{/if}
			</ul>
		</li>
	{/if}
</ul>
