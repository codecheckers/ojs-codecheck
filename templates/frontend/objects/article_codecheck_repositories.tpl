{**
 * templates/frontend/objects/article_codecheck_repositories.tpl
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief The repositories of a CODECHECK, one link each.
 *
 * Every repository gets its own anchor: a single anchor holding a joined list
 * links nowhere as soon as there is more than one repository. The label drops
 * the scheme so that the part telling two repositories apart stays readable in
 * a narrow sidebar, and the title carries the address in full for anyone who
 * cannot see the browser's own link preview.
 *
 * Expects $repositories — a list of {url, containsCodecheckYaml}, hidden
 * entries already removed by CodecheckSubmission::getPublicRepositories().
 *}

{if $repositories}
    <div class="sub_item">
        <h2 class="label">{translate key='plugins.generic.codecheck.repositories.title'}</h2>
        <ul class="value codecheck-sidebar-list codecheck-article-repositories">
            {foreach from=$repositories item=repository}
                <li>
                    {if $repository.isWebLink}
                        <a href="{$repository.url|escape}" target="_blank" rel="noopener noreferrer" title="{$repository.url|escape}">
                            {$repository.url|regex_replace:'#^https?://(www\.)?#':''|escape}
                        </a>
                    {else}
                        {* Anything that is not http(s) is shown but never linked *}
                        {$repository.url|escape}
                    {/if}
                    {if $repository.containsCodecheckYaml}
                        <span class="codecheck-article-repositories__yaml">{translate key='plugins.generic.codecheck.repositories.containsCodecheckYaml'}</span>
                    {/if}
                </li>
            {/foreach}
        </ul>
    </div>
{/if}
