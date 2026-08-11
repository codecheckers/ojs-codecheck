{**
 * templates/frontend/objects/codecheck_badge.tpl
 *
 * CODECHECK badge display for issue table of contents
 *}
{if $badgeUrl}
<a href="{$certificateLink|escape}"
   target="_blank"
   title="{translate key="plugins.generic.codecheck.viewCertificate"}"
   class="codecheck-badge">
    <img src="{$badgeUrl|escape}"
         alt="{translate key="plugins.generic.codecheck.badge.altText"}"
         class="codecheck-badge-img"
         style="{$badgeStyle}" />
</a>
{else}
    {if $certificateLink}
    <a href="{$certificateLink|escape}"
       target="_blank"
       class="codecheck-badge codecheck-badge--text"
       style="color:#2d7f3e; font-weight:600;">
        {translate key="plugins.generic.codecheck.badge.textOnly"}
    </a>
    {else}
    <span class="codecheck-badge codecheck-badge--text"
          style="color:#2d7f3e; font-weight:600;">
        {translate key="plugins.generic.codecheck.badge.textOnly"}
    </span>
    {/if}
{/if}
