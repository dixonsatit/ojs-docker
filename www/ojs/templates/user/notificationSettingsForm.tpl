{**
 * templates/user/notificationSettings.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
{capture assign="additionalNotificationSettingsContent"}
	{* FIXME: https://github.com/pkp/pkp-lib/issues/490 *}
	{if $displayOpenAccessNotification}
		{assign var=notFirstJournal value=0}
		{foreach from=$journals name=journalOpenAccessNotifications key=thisJournalId item=thisJournal}
			{assign var=thisJournalId value=$thisJournal->getId()}
			{assign var=publishingMode value=$thisJournal->getSetting('publishingMode')}
			{assign var=enableOpenAccessNotification value=$thisJournal->getSetting('enableOpenAccessNotification')}
			{assign var=notificationEnabled value=$user->getSetting('openAccessNotification', $thisJournalId)}
			{if !$notFirstJournal}
				{assign var=notFirstJournal value=1}
				<tr>
					<td class="label">{translate key="user.profile.form.openAccessNotifications"}</td>
					<td class="value">
			{/if}

			{if $publishingMode == $smarty.const.PUBLISHING_MODE_SUBSCRIPTION && $enableOpenAccessNotification}
				<input type="checkbox" name="openAccessNotify[]" {if $notificationEnabled}checked="checked" {/if}id="openAccessNotify-{$thisJournalId|escape}" value="{$thisJournalId|escape}" /> <label for="openAccessNotify-{$thisJournalId|escape}">{$thisJournal->getLocalizedName()|escape}</label><br/>
			{/if}

			{if $smarty.foreach.journalOpenAccessNotifications.last}
					</td>
				</tr>
			{/if}
		{/foreach}
	{/if}
{/capture}
{include file="core:user/notificationSettingsForm.tpl" additionalNotificationSettingsContent=$additionalNotificationSettingsContent}
