<?php
/**
 * @file classes/Orcid/PeerReviewPayloadBuilder.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PeerReviewPayloadBuilder
 * @brief Builds the JSON payload for an ORCID peer-review item.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\submission\Submission;

class PeerReviewPayloadBuilder
{
    public function build(Submission $submission, string $orcidId, array $meta, array $journal): array
    {
        $publication = $submission->getCurrentPublication();

        $checkDate = !empty($meta['check_time'])
            ? new \DateTime($meta['check_time'])
            : new \DateTime();

        $submissionTitle = $publication
            ? strip_tags($publication->getLocalizedFullTitle() ?? '')
            : __('plugins.generic.codecheck.orcid.payload.untitledSubmission');

        if (empty($submissionTitle)) {
            $submissionTitle = __('plugins.generic.codecheck.orcid.payload.untitledSubmission');
        }

        $certificateDoi = !empty($meta['certificate']) ? $meta['certificate'] : null;
        $articleDoi     = $publication ? ($publication->getData('pub-id::doi') ?? null) : null;

        $issn = !empty($journal['issn']) ? trim($journal['issn']) : '';
        if (!empty($issn)) {
            $groupId = 'issn:' . $issn;
        } else {
            $groupId = 'orcid-generated:codecheck-ojs';
        }

        $payload = [
            'reviewer-role'          => 'reviewer',
            'review-type'            => 'review',
            'review-completion-date' => $this->buildDate($checkDate),
            'review-group-id'        => $groupId,
            'review-identifiers'     => $this->buildReviewIdentifiers($certificateDoi),
            'convening-organization' => $this->buildConveningOrganization($journal),
            'subject-type'           => 'journal-article',
            'subject-name'           => ['title' => ['value' => $submissionTitle]],
        ];

        if ($certificateDoi) {
            $payload['review-url'] = str_starts_with(ltrim($certificateDoi, '/'), '10.')
                ? 'https://doi.org/' . ltrim($certificateDoi, '/')
                : 'https://codecheck.org.uk/register/certs/' . ltrim($certificateDoi, '/');
        }

        $journalName = !empty($journal['name']) ? trim($journal['name']) : '';
        if (!empty($journalName)) {
            $payload['subject-container-name'] = ['value' => $journalName];
        }

        if ($articleDoi) {
            $payload['subject-external-identifier'] = $this->buildExternalId('doi', $articleDoi);
        }

        return $payload;
    }

    private function buildDate(\DateTime $date): array
    {
        return [
            'year'  => ['value' => $date->format('Y')],
            'month' => ['value' => $date->format('m')],
            'day'   => ['value' => $date->format('d')],
        ];
    }

    private function buildReviewIdentifiers(?string $certificateDoi): array
    {
        if (!$certificateDoi) {
            return [
                'external-id' => [[
                    'external-id-type'         => 'uri',
                    'external-id-value'        => 'codecheck:unknown',
                    'external-id-relationship' => 'self',
                ]]
            ];
        }

        $isDoi = str_starts_with(ltrim($certificateDoi, '/'), '10.');

        return [
            'external-id' => [[
                'external-id-type'         => $isDoi ? 'doi' : 'source-work-id',
                'external-id-value'        => ltrim($certificateDoi, '/'),
                'external-id-url'          => ['value' => $isDoi
                    ? 'https://doi.org/' . ltrim($certificateDoi, '/')
                    : 'https://codecheck.org.uk/register/venues/journals/' . ltrim($certificateDoi, '/')
                ],
                'external-id-relationship' => 'self',
            ]]
        ];
    }

    private function buildExternalId(string $type, string $value): array
    {
        return [
            'external-id-type'         => $type,
            'external-id-value'        => $value,
            'external-id-url'          => ['value' => 'https://doi.org/' . ltrim($value, '/')],
            'external-id-relationship' => 'self',
        ];
    }

    /**
     * Build convening-organization.
     *
     * ORCID requires name and country. City is optional — OJS does not
     * expose a publisher city field so we include it only when available.
     *
     * @throws \InvalidArgumentException if name or country are missing
     */
    private function buildConveningOrganization(array $journal): array
    {
        $publisherName = !empty($journal['publisherName'])
            ? trim($journal['publisherName'])
            : (!empty($journal['name']) ? trim($journal['name']) : '');

        $country = !empty($journal['publisherCountry']) ? trim($journal['publisherCountry']) : '';

        $missing = [];
        if (empty($publisherName)) $missing[] = __('plugins.generic.codecheck.orcid.payload.missingPublisherName');
        if (empty($country))       $missing[] = __('plugins.generic.codecheck.orcid.payload.missingCountry');

        if (!empty($missing)) {
        throw new \InvalidArgumentException(
                __('plugins.generic.codecheck.orcid.payload.missingMetadata', ['fields' => implode(', ', $missing)])
            );
        }

        $org = [
            'name'    => $publisherName,
            'address' => [
                'country' => $country,
            ],
        ];

        $city = !empty($journal['publisherCity']) ? trim($journal['publisherCity']) : '';
        if (!empty($city)) {
            $org['address']['city'] = $city;
        }

        if (!empty($journal['ringgoldId'])) {
            $org['disambiguated-organization'] = [
                'disambiguated-organization-identifier' => $journal['ringgoldId'],
                'disambiguation-source'                 => 'RINGGOLD',
            ];
        }

        return $org;
    }
}