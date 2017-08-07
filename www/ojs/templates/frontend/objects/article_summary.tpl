{**
 * templates/frontend/objects/article_summary.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief View of an Article summary which is shown within a list of articles.
 *
 * @uses $article Article The article
 * @uses $hasAccess bool Can this user access galleys for this context? The
 *       context may be an issue or an article
 * @uses $showDatePublished bool Show the date this article was published?
 *}
{assign var=articlePath value=$article->getBestArticleId()}
{if (!$section.hideAuthor && $article->getHideAuthor() == $smarty.const.AUTHOR_TOC_DEFAULT) || $article->getHideAuthor() == $smarty.const.AUTHOR_TOC_SHOW}
	{assign var="showAuthor" value=true}
{/if}

<div class="obj_article_summary">
	{if $article->getLocalizedCoverImage()}
		<div class="cover">
			<a href="{url page="article" op="view" path=$articlePath}" class="file">
				<img src="{$article->getLocalizedCoverImageUrl()|escape}"{if $article->getLocalizedCoverImageAltText() != ''} alt="{$article->getLocalizedCoverImageAltText()|escape}"{else} alt="{translate key="article.coverPage.altText"}"{/if}>
			</a>
		</div>
	{/if}

	<div class="title">
		<a href="{url page="article" op="view" path=$articlePath}">
			{$article->getLocalizedTitle()|strip_unsafe_html}
		</a>
	</div>

	{if $showAuthor || $article->getPages() || ($article->getDatePublished() && $showDatePublished)}
	<div class="meta">
		{if $showAuthor}
		<div class="authors">
			{$article->getAuthorString()}
		</div>
		{/if}

		{* Page numbers for this article *}
		{if $article->getPages()}
			<div class="pages">
				{$article->getPages()|escape}
			</div>
		{/if}

		{if $showDatePublished && $article->getDatePublished()}
			<div class="published">
				{$article->getDatePublished()|date_format:$dateFormatShort}
			</div>
		{/if}

	</div>
	{/if}

	{if $hasAccess}
		<ul class="galleys_links">
			{foreach from=$article->getGalleys() item=galley}
				<li>
					{assign var="hasArticleAccess" value=$hasAccess}
					{if ($article->getAccessStatus() == $smarty.const.ARTICLE_ACCESS_OPEN)}
						{assign var="hasArticleAccess" value=1}
					{/if}
					{include file="frontend/objects/galley_link.tpl" parent=$article hasAccess=$hasArticleAccess}
				</li>
			{/foreach}
		</ul>
	{/if}

	{call_hook name="Templates::Issue::Issue::Article"}
</div>
