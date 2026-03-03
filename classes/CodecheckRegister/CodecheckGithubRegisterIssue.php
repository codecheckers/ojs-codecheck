<?

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenue;

class CodecheckGithubRegisterIssue {
    private string $repositoryOwner;
    private string $repository;
    private string $title;
    private string $body;
    private array $labels;

    public function __construct(
        string $repositoryOwner,
        string $repository,
        CertificateIdentifier $certificateIdentifier,
        CodecheckVenue $codecheckVenue,
        string $journalName,
        string $authorString,
    ){
        $this->repositoryOwner = $repositoryOwner;
        $this->repository = $repository;
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        $this->title = $authorString . ' | ' . $certificateIdentifier->toStr();
        $this->body = 'Journal: `' . $journalName . '`<br />' . 'Submission ID: `' . $this->submissionID . '`';
        $this->labels = ['id assigned'];

        $this->labels[] = $codecheckVenue->getVenueType();
        $this->labels[] = $codecheckVenue->getVenueName();
    }

    public function getRepositoryOwner(): string
    {
        return $this->repositoryOwner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    private function formatContentForUrl(string|array $content): string
    {
        if(is_string($content)) {
            return preg_replace_callback(
                '/[:\n |]/',
                fn($m) => rawurlencode($m[0]),
                $content
            );
        } else {
            $labels = "";
            $countLabels = count($this->labels);
            foreach($this->labels as $label) {
                $labels = $labels . $this->formatContentForUrl($label);

                if($countLabels < count($this->labels) - 1) {
                    $labels = $labels  . ",";
                }
            }

            return $labels;
        }
    }

    public function getNewIssueUrl(): string
    {
        $url = "https://github.com/$this->repositoryOwner/$this->repository/issues/new";
        $queryTitle = "title=" . $this->formatContentForUrl($this->title);
        $queryBody = "body=" . $this->formatContentForUrl($this->body);
        $queryLabels = "labels=" . $this->formatContentForUrl($this->labels);

        return $url . "?" . $queryTitle . "&" . $queryBody . "&" . $queryLabels;
    }
}