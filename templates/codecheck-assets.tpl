{**
 * templates/codecheck-assets.tpl
 *
 * CODECHECK plugin assets template
 *}

{* Load CSS styles *}
<style>
.codecheck-info {
    border: 1px solid #d4edda;
    background-color: #d1ecf1;
    color: #0c5460;
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.375rem;
}

.codecheck-info h4 {
    margin-top: 0;
    color: #0c5460;
    font-weight: bold;
}

.codecheck-certificate {
    border: 2px solid #008033;
    background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
    padding: 1.5rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
    position: relative;
}

.codecheck-certificate::before {
    content: "✓";
    position: absolute;
    top: -10px;
    right: -10px;
    background: #008033;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.codecheck-badge {
    display: inline-block;
    background: #008033;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: bold;
    text-decoration: none;
}

.codecheck-badge:hover {
    background: #006629;
    color: white;
    text-decoration: none;
}

.codecheck-details {
    margin-top: 1rem;
    font-size: 0.9rem;
    color: #495057;
}

.codecheck-settings-form .form-group {
    margin-bottom: 1rem;
}

.codecheck-settings-form label {
    font-weight: bold;
    margin-bottom: 0.5rem;
    display: block;
}

.codecheck-settings-form input[type="text"],
.codecheck-settings-form input[type="url"],
.codecheck-settings-form textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}

.codecheck-settings-form input[type="checkbox"] {
    margin-right: 0.5rem;
}
</style>

{* Load JavaScript functionality *}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // CODECHECK plugin JavaScript functionality
    
    /**
     * Handle certificate verification
     */
    function verifyCertificate(certificateId) {
        console.log('Verifying certificate:', certificateId);
        
        // Future: Make AJAX call to verify certificate
        // fetch('/api/codecheck/verify/' + certificateId)
        //     .then(response => response.json())
        //     .then(data => {
        //         // Handle verification result
        //     });
    }

    /**
     * Toggle certificate details
     */
    function toggleCertificateDetails(element) {
        const details = element.nextElementSibling;
        if (details && details.classList.contains('codecheck-details')) {
            details.style.display = details.style.display === 'none' ? 'block' : 'none';
        }
    }

    /**
     * Initialize CODECHECK functionality
     */
    function initCodecheck() {
        // Add click handlers for certificates
        document.querySelectorAll('.codecheck-certificate').forEach(function(cert) {
            cert.addEventListener('click', function() {
                toggleCertificateDetails(this);
            });
        });

        // Add verification handlers
        document.querySelectorAll('.codecheck-verify-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const certificateId = this.dataset.certificateId;
                if (certificateId) {
                    verifyCertificate(certificateId);
                }
            });
        });
    }

    // Initialize when DOM is ready
    initCodecheck();

    // Make functions available globally for template use
    window.CodecheckPlugin = {
        verifyCertificate: verifyCertificate,
        toggleCertificateDetails: toggleCertificateDetails,
        init: initCodecheck
    };
});
</script>