<div v-if="section.id == 'review'" class="codecheck-section">
    <h3>CODECHECK Information</h3>
    {if $codecheckData.optIn}
        <p><strong>Status:</strong> This submission will be codechecked</p>
        {if $codecheckData.codeRepository}
            <p><strong>Code Repository:</strong> {$codecheckData.codeRepository}</p>
        {/if}
        {if $codecheckData.manifestFiles}
            <p><strong>Expected Output Files:</strong></p>
            <div>{$codecheckData.manifestFiles}</div>
        {/if}
    {/if}
</div>