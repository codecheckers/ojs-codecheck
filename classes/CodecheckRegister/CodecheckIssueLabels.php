<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Tasks\RefreshCodecheckLists;
use Illuminate\Support\Facades\DB;

class CodecheckIssueLabels
{
    /**
     * How old stored labels may grow before the labels endpoint reads the list
     * itself. The scheduled refresh keeps them far younger; this is for an
     * install whose scheduler never runs.
     */
    public const STORED_LABELS_BACKSTOP_SECONDS = 30 * 24 * 60 * 60;

    private UniqueArray $uniqueArray;

    /**
     * Initializes a new List of all CODECHECK Issue Labels
     */
    public function __construct(array $issueLabelArray)
    {
        // Initialize and fill unique Array
        $this->uniqueArray = UniqueArray::from($issueLabelArray);
    }

    /**
     * Reads the venue list and stores its labels in place of the stored ones.
     *
     * Called by the scheduled refresh (#65), and by the labels endpoint only
     * while nothing is stored, or what is stored has outlived
     * `STORED_LABELS_BACKSTOP_SECONDS`. A failed read throws and leaves the
     * stored labels as they were, so the form keeps offering them.
     *
     * @throws \Throwable when the list cannot be read or names no venue
     */
    public static function fromApi(): CodecheckIssueLabels
    {
        // OJS's own client, so its [proxy] setting applies.
        $response = Application::get()->getHttpClient()->request('GET', Constants::CODECHECK_VENUES_URL, [
            'timeout' => RefreshCodecheckLists::READ_TIMEOUT_SECONDS,
        ]);
        $venues = json_decode((string) $response->getBody(), true);
        if (!is_array($venues)) {
            throw new \UnexpectedValueException('The venue list is not JSON.');
        }

        $labels = self::labelsFrom($venues);
        if ($labels === []) {
            // An empty list would replace every stored label with none.
            throw new \UnexpectedValueException('The venue list names no issue label.');
        }

        self::store($labels);

        return new CodecheckIssueLabels($labels);
    }

    /**
     * Reads the venue list on demand, for the labels endpoint, unless a read
     * failed within the hour or the lists may not be read. Only a read tried
     * and failed starts the hour, so a skipped one cannot push the next back.
     *
     * @return ?CodecheckIssueLabels the labels read, `null` when none were
     */
    public static function refresh(): ?CodecheckIssueLabels
    {
        if (!RefreshCodecheckLists::listsReadable() || RefreshCodecheckLists::failedRecently()) {
            return null;
        }

        try {
            return self::fromApi();
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not read the CODECHECK venue list: ' . $e->getMessage());
            RefreshCodecheckLists::recordFailure();
            return null;
        }
    }

    /**
     * The issue labels the venue list names, without those the plugin assigns
     * itself; an entry without a label is skipped.
     *
     * @return string[]
     */
    public static function labelsFrom(array $venues): array
    {
        $labels = [];
        foreach ($venues as $venue) {
            $label = is_array($venue) && is_string($venue['Issue label'] ?? null) ? trim($venue['Issue label']) : '';
            if ($label !== '' && !self::isAssignedByThePlugin($label)) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * When the stored labels were last read, or `null` when none are stored.
     */
    public static function lastUpdated(): ?int
    {
        $lastUpdated = DB::table('codecheck_issue_labels')->max('labels_last_updated');

        return $lastUpdated === null ? null : (strtotime((string) $lastUpdated) ?: null);
    }

    public static function fromDB(): CodecheckIssueLabels
    {
        return new CodecheckIssueLabels(DB::table('codecheck_issue_labels')->pluck('label')->toArray());
    }

    /**
     * Stores these labels in place of the stored ones, so a venue taken off
     * the list is no longer offered.
     *
     * @param string[] $labels
     */
    private static function store(array $labels): void
    {
        $labelsLastUpdated = date('Y-m-d H:i:s');

        // Tried twice: the scheduled refresh and a form load can store at once,
        // and two such transactions on the unindexed table can deadlock.
        DB::transaction(function () use ($labels, $labelsLastUpdated) {
            DB::table('codecheck_issue_labels')->delete();
            DB::table('codecheck_issue_labels')->insert(array_map(
                fn (string $label) => ['label' => $label, 'labels_last_updated' => $labelsLastUpdated],
                $labels
            ));
        }, 2);
    }

    public function add(string $issue): void
    {
        $this->uniqueArray->add($issue);
    }

    public function addLabelArray(array $labels): void
    {
        // A journal's own labels go through the same filter as the venue ones:
        // a label the plugin assigns itself must not also be offered as a
        // checkbox, or the form would add what a status change had just removed
        // and the two would fight over it (#174).
        $this->uniqueArray->addArray(array_values(array_filter(
            $labels,
            fn ($label) => !self::isAssignedByThePlugin($label)
        )));
    }

    /**
     * Whether the plugin assigns this label itself, which is what makes it none
     * of the editor's business.
     *
     * `id assigned` marks a register issue as carrying an identifier and
     * `development` belongs to the register's own issues; the rest follow the
     * CODECHECK status (#174). Offering any of them as a checkbox would make the
     * form a second writer of a label the plugin already maintains.
     */
    public static function isAssignedByThePlugin(string $label): bool
    {
        return in_array(
            $label,
            array_merge(
                [
                    Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL,
                    Constants::CODECHECK_REGISTER_DEVELOPMENT_LABEL,
                ],
                Constants::CODECHECK_REGISTER_MANAGED_LABELS
            ),
            true
        );
    }

    /**
     * Gets the List of all CODECHECK Venue Names
     *
     * @return UniqueArray Returns all CODECHECK Venue Names inside a `UniqueArray`
     */
    public function get(): UniqueArray
    {
        return $this->uniqueArray;
    }
}
