{if $codecheckInfo.optedIn}
<div class="section">
    <h3>CODECHECK Information</h3>
    <div class="fields">
        <div class="field">
            <div class="label">Status</div>
            <div class="value">{$codecheckInfo.status}</div>
        </div>
        {if $codecheckInfo.repository}
        <div class="field">
            <div class="label">Code Repository</div>
            <div class="value">{$codecheckInfo.repository}</div>
        </div>
        {/if}
    </div>
</div>
{/if}