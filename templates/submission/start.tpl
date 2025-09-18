{**
 * templates/submission/start.tpl
 *
 * Override for submission start template to add CODECHECK checkbox
 *}

{* Include the original template content first *}
{include file="core:submission/start.tpl"}

{* Add CODECHECK checkbox if plugin is active *}
{if $showCodecheckCheckbox}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find the privacy consent section and add CODECHECK before it
    const privacySection = document.querySelector('fieldset legend').parentElement;
    if (privacySection) {
        const codecheckHTML = `
            <fieldset style="border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 4px; background: #f9f9f9;">
                <legend style="font-weight: bold; color: #007bff; padding: 0 10px; background: white;">CODECHECK</legend>
                <div class="section">
                    <label class="sub_label">
                        <input type="checkbox" id="codecheckOptIn" name="codecheckOptIn" value="1" {if $codecheckOptIn}checked{/if}>
                        Yes, I want my paper to be codechecked. See: <a href="https://codecheck.org.uk/" target="_blank">CODECHECK</a>
                    </label>
                </div>
            </fieldset>
        `;
        
        // Insert before the last fieldset (usually Privacy Consent)
        const fieldsets = document.querySelectorAll('fieldset');
        const lastFieldset = fieldsets[fieldsets.length - 1];
        lastFieldset.insertAdjacentHTML('beforebegin', codecheckHTML);
    }
});
</script>
{/if}