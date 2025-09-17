<?php

require __DIR__ . '/vendor/autoload.php';

use Github\Client;
use Dotenv\Dotenv;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Set {
    protected $array_set = [];

    function insert($element) {
        foreach ($this->array_set as $list_element) {
            if($list_element->to_str() == $element->to_str()) {
                return;
            }
        }
        $this->array_set[] = $element;
    }

    function insert_array($array) {
        foreach ($array as $element) {
            $this->insert($element);
        }
    }

    function get_array() {
        return $this->array_set;
    }
}

enum Codecheck_Type {
    case check_nl;
    case community;
    case conference_workshop;
    case institution;
    case journal;
    case lifecycle_journal;

    public function labels(): array {
        return match($this) {
            self::check_nl              => ['check-nl', 'community'],
            self::community             => ['community'],
            self::conference_workshop   => ['conference/workshop'],
            self::institution           => ['institution'],
            self::journal               => ['journal'],
            self::lifecycle_journal     => ['lifecycle journal'],
        };
    }
}

class Certificate_Identifier {
    private $year;
    private $id;

    function set_year($year) {
        $this->year = $year;
    }

    function set_id($id) {
        $this->id = $id;
    }

    function get_year() {
        return $this->year;
    }

    function get_id() {
        return $this->id;
    }

    // Factory Method for Certificate Identifier
    static function from_str($identifier_str) {
        // split Identifier String at '-'
        list($year, $id) = explode('-', $identifier_str);
        // create new instance of $certificate_identifier
        $certificate_identifier = new Certificate_Identifier();
        // set year and id (cast to int from str)
        $certificate_identifier->set_year((int) $year);
        $certificate_identifier->set_id((int) $id);
        // return new instance of $certificate_identifier
        return $certificate_identifier;
    }

    // Factory Method for new unique Identifier
    static function new_unique_identifier($codecheck_register) {
        $latest_identifier = $codecheck_register->get_newest_identifier();
        $current_year = (int) date("Y");

        $new_identifier = new Certificate_Identifier();

        // different year, so this is the first CODECHECK certificate of the year -> id 001
        if($current_year != $latest_identifier->get_year()) {
            // configure new Identifier
            $new_identifier->set_year($current_year);
            $new_identifier->set_id(1);
            return $new_identifier;
        }

        // get the latest id
        $latest_id = (int) $latest_identifier->get_id();
        // increment the latest id by one to get a new unique one
        $latest_id++;
        // configure new Identifier
        $new_identifier->set_year($latest_identifier->get_year());
        $new_identifier->set_id($latest_id);
        return $new_identifier;
    }

    function to_str() {
        // pad with leading zeros (3 digits) in case number doesn't have 3 digits already
        return $this->year . '-' . str_pad($this->id, 3, '0', STR_PAD_LEFT);;
    }
}

class Codecheck_Register extends Set {
    // Factory Method to create a new Codecheck_Register from a GitHub API fetch
    static function from_api($api_parser) {
        $new_codecheck_register = new Codecheck_Register();

        // fetch API
        $api_parser->fetch_api();

        foreach ($api_parser->get_issues() as $issue) {
            // raw identifier (can still have ranges of identifiers);
            $raw_identifier = get_raw_identifier($issue['title']);
            
            // append to all identifiers in new Register
            $new_codecheck_register->append_to_certificate_id_list($raw_identifier);
        }

        // return the new Register
        return $new_codecheck_register;
    }

    function append_to_certificate_id_list($raw_identifier) {
        // list of certificate identifiers in range
        $id_range = [];

        // if it is a range
        if(strpos($raw_identifier, '/')) {
            // split into "from_str" and "to_str"
            list($from_str, $to_str) = explode('/', $raw_identifier);

            $from_identifier = Certificate_Identifier::from_str($from_str);
            $to_identifier = Certificate_Identifier::from_str($to_str);

            // append to $id_range list
            for ($id_count = $from_identifier->get_id(); $id_count <= $to_identifier->get_id(); $id_count++) {
                $new_identifier = new Certificate_Identifier();
                $new_identifier->set_year($from_identifier->get_year());
                $new_identifier->set_id($id_count);
                // append new identifier
                $id_range[] = $new_identifier;
            }
        }
        // if it isn't a list then just append on identifier
        else {
            $new_identifier = Certificate_Identifier::from_str($raw_identifier);
            $id_range[] = $new_identifier;
        }

        // append to all certificate identifiers
        $this->insert_array($id_range);
    }

    // sort ascending Certificate Identifiers
    function sort_asc() {
        usort($this->array_set, function($a, $b) {
            // First, compare year
            if ($a->get_year() !== $b->get_year()) {
                return $a->get_year() <=> $b->get_year();
            }
            // If years are equal, compare ID
            return $a->get_id() <=> $b->get_id();
        });
    }

    function sort_desc() {
        usort($this->array_set, function($a, $b) {
            // First, compare year descending
            if ($a->get_year() !== $b->get_year()) {
                return $b->get_year() <=> $a->get_year();
            }
            // If years are equal, compare ID descending
            return $b->get_id() <=> $a->get_id();
        });
    }

    function get_number_of_identifiers() {
        return count($this->array_set);
    }

    // return the latest identifier
    function get_newest_identifier() {
        $this->sort_desc();
        // get first element of sort descending -> newest element
        return $this->array_set[0];
    }

    function to_str() {
        echo "Certificate Identifiers:\n";
        foreach ($this->array_set as $id) {
            echo $id->to_str() . "\n";
        }
    }
}

// get the certificate ID from the issue description
function get_raw_identifier($title) {
    $title = strtolower($title); // convert whole title to lowercase

    //$title = "Arabsheibani, Winter, Tomko | 2025-026/2025-029";

    if (strpos($title, '|') !== false) {
        // find the last "|"
        $seperator = strrpos($title, '|');
        // move one position forwards (so we get character after '|')
        $seperator++;

        // Find where the next line break occurs after "certificate"
        $raw_identifier = substr($title, $seperator);
        // remove white spaces
        $raw_identifier = preg_replace('/[\s]+/', '', $raw_identifier);
    }

    return $raw_identifier;
}

// api call
class Codecheck_Register_Github_Issues_API_Parser {
    private $issues = [];
    private $client;

    function __construct() {
        $this->client = new Client();
    }

    function fetch_api() {
        $all_issues = $this->client->api('issue')->all('codecheckers', 'register', [
            'state'     => 'all',          // 'open', 'closed', or 'all'
            'labels'    => 'id assigned',  // label
            'sort'      => 'updated',
            'direction' => 'desc',
            'per_page'  => 100,            // get all issues in page
        ]);

        foreach ($all_issues as $issue) {
            // check if this issue has the certificate identifier in the title
            if(strpos($issue['title'], '|') !== false) {
                $this->issues[] = $issue;
            }
        }
    }

    function add_issue($certificate_identifier, $codecheck_type) {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $repository_owner = 'dxL1nus';
        $repository_name = 'dxL1nus';
        $issue_title = 'New CODECHECK | ' . $certificate_identifier->to_str();
        $issue_body = '';
        $labels = ['id assigned'];

        $labels = array_merge($labels, $codecheck_type->labels());

        $issue = $this->client->api('issue')->create(
            $repository_owner,
            $repository_name,
            [
                'title' => $issue_title,
                'body'  => $issue_body,
                'labels' => $labels
            ]
        );
    }

    function get_issues() {
        return $this->issues;
    }
}


// CODECHECK GitHub Issue Register API parser
$api_parser = new Codecheck_Register_Github_Issues_API_Parser();

// CODECHECK Register with list of all identifiers in range
$codecheck_register = Codecheck_Register::from_api($api_parser);

// print Certificate Identifier list
$codecheck_register->sort_desc();
$codecheck_register->to_str();

echo $codecheck_register->get_newest_identifier()->to_str() . "\n";

$new_identifier = Certificate_Identifier::new_unique_identifier($codecheck_register);

$api_parser->add_issue($new_identifier, Codecheck_Type::check_nl);

echo "Added new issue with identifier: " . $new_identifier->to_str() . "\n";

//echo "{$num_of_issues}";