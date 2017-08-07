{**
 * templates/subscription/institutionalSubscriptionForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Individual subscription form under journal management.
 *
 *}
{strip}
{if $subscriptionId}
	{assign var="pageTitle" value="manager.subscriptions.edit"}
	{url|assign:"currentUrl" op="editSubscription" path="institutional"|to_array:$subscriptionId userId=$userId}
{else}
	{assign var="pageTitle" value="manager.subscriptions.create"}
	{url|assign:"currentUrl" op="createSubscription" path="institutional" userId=$userId}
{/if}
{assign var="pageId" value="manager.subscriptions.institutionalSubscriptionForm"}
{include file="common/header.tpl"}
{/strip}

<br/>
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#subscriptionForm').pkpHandler('$.pkp.controllers.form.FormHandler');
	{rdelim});
</script>
<form class="pkp_form" method="post" id="subscriptionForm" action="{url op="updateSubscription" path="institutional"}">
{if $subscriptionId}
<input type="hidden" name="subscriptionId" value="{$subscriptionId|escape}" />
{/if}
{csrf}

{include file="common/formErrors.tpl"}

<table class="data">
{if count($formLocales) > 1}
	<tr>
		<td class="label">{fieldLabel name="formLocale" key="form.formLanguage"}</td>
		<td class="value">
			{if $subscriptionId}
				{url|assign:"formUrl" op="editSubscription" path="institutional"|to_array:$subscriptionId userId=$userId escape=false}
			{else}
				{url|assign:"formUrl" op="createSubscription" path="institutional" escape=false}
			{/if}
			{form_language_chooser form="subscriptionForm" url=$formUrl}
			<span class="instruct">{translate key="form.formLanguage.description"}</span>
		</td>
	</tr>
{/if}

{include file="subscription/subscriptionForm.tpl"}

{* For new subscriptions, set end date for default subscription type *}
{if !$subscriptionId && !$ipRanges}
	<script>
	<!--
	chooseEndDate();
	// -->
	</script>
{/if}
</table>

<br />
<div class="separator"></div>
<br />

<table class="data">
<tr>
	<td class="label">{fieldLabel name="institutionName" required="true" key="manager.subscriptions.form.institutionName"}</td>
	<td class="value"><input type="text" name="institutionName" id="institutionName" value="{if $institutionName}{$institutionName|escape}{/if}" size="30" maxlength="90" class="textField" /></td>
</tr>
<tr>
	<td class="label">{fieldLabel name="institutionMailingAddress" key="manager.subscriptions.form.institutionMailingAddress"}</td>
	<td class="value"><textarea name="institutionMailingAddress" id="institutionMailingAddress" rows="3" cols="40" class="textArea">{$institutionMailingAddress|escape}</textarea></td>
</tr>
<tr>
	<td class="label">{fieldLabel name="domain" key="manager.subscriptions.form.domain"}</td>
	<td class="value"><input type="text" name="domain" id="domain" value="{if $domain}{$domain|escape}{/if}" size="30" maxlength="90" class="textField" /></td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td><span class="instruct">{translate key="manager.subscriptions.form.domainInstructions"}</span></td>
</tr>

</table>
<table class="data">
	{foreach name=ipRanges from=$ipRanges key=ipRangeIndex item=ipRange}
	<tr>
		{if $ipRangeIndex == 0}
		<td class="label">{fieldLabel name="ipRanges" key="manager.subscriptions.form.ipRange"}</td>
		{else}
		<td>&nbsp;</td>
		{/if}
		<td width="5%" class="label">{fieldLabel name="ipRanges[$ipRangeIndex]" key="manager.subscriptions.form.ipRangeItem}</td>
		<td class="value"><input type="text" name="ipRanges[{$ipRangeIndex|escape}]" id="ipRanges-{$ipRangeIndex|escape}" value="{$ipRange|escape}" size="30" maxlength="40" class="textField" />
		{if $smarty.foreach.ipRanges.total > 1}
		<input type="submit" name="delIpRange[{$ipRangeIndex|escape}]" value="{translate key="manager.subscriptions.form.deleteIpRange"}" class="button" /></td>
		{else}
		</td>
		{/if}
	</tr>
	{foreachelse}
	<tr>
		<td class="label">{fieldLabel name="ipRanges" key="manager.subscriptions.form.ipRange"}</td>
		<td width="5%" class="label">{fieldLabel name="ipRanges[0]" key="manager.subscriptions.form.ipRangeItem}</td>
		<td class="value"><input type="text" name="ipRanges[0]" id="ipRanges-0" size="30" maxlength="40" class="textField" /></td>
	</tr>
	{/foreach}
	<tr>
		<td>&nbsp;</td>
		<td width="5%">&nbsp;</td>
		<td><input type="submit" class="button" name="addIpRange" value="{translate key="manager.subscriptions.form.addIpRange"}" /></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td width="5%">&nbsp;</td>
		<td><span class="instruct">{translate key="manager.subscriptions.form.ipRangeInstructions"}</span></td>
	</tr>
</table>

<div class="separator"></div>
<br />

<table class="data">
<tr>
	<td class="label">{fieldLabel name="userId" required="true" key="manager.subscriptions.form.userContact"}</td>
	<td class="value">
		{assign var=emailString value="$userFullName <$userEmail>"}
		{url|assign:"url" page="user" op="email" to=$emailString|to_array redirectUrl=$currentUrl}
		{$username|escape}&nbsp;&nbsp;{icon name="mail" url=$url}&nbsp;&nbsp;<a href="{if $subscriptionId}{url op="selectSubscriber" path="institutional" subscriptionId=$subscriptionId}{else}{url op="selectSubscriber" path="institutional"}{/if}" class="action">{translate key="common.select"}</a>
		<input type="hidden" name="userId" id="userId" value="{$userId|escape}"/>
	</td>
</tr>
{include file="subscription/subscriptionFormUser.tpl"}
</table>

<br />
<div class="separator"></div>
<br />

<table class="data">
<tr>
	<td class="label">{fieldLabel name="notes" key="manager.subscriptions.form.notes"}</td>
	<td class="value"><textarea name="notes" id="notes" cols="40" rows="6" class="textArea richContent">{$notes|escape}</textarea></td>
</tr>
</table>

<p><input type="submit" value="{translate key="common.save"}" class="button defaultButton" /> {if not $subscriptionId}<input type="submit" name="createAnother" value="{translate key="manager.subscriptions.form.saveAndCreateAnother"}" class="button" /> {/if}<input type="button" value="{translate key="common.cancel"}" class="button" onclick="document.location.href='{url op="subscriptions" path="institutional" escape=false}'" /></p>

</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

{include file="common/footer.tpl"}

