{**
 * templates/frontend/objects/codecheck_badge.tpl
 *
 * CODECHECK badge display
 *}
<a href="{$certificateLink|escape}" 
   target="_blank" 
   title="{translate key="plugins.generic.codecheck.viewCertificate"}"
   class="codecheck-badge">
    <img src="{$badgeUrl|escape}" 
         alt="{translate key="plugins.generic.codecheck.badge.altText"}" 
         class="codecheck-badge-img" />
</a>
