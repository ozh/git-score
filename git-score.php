<?php
/**
 * Collect and output git "scores" in the CLI
 *
 * @author Ozh <ozh@ozh.org>
 */


namespace Ozh\Git;

class Score {

    /**
     * @var const The git command which output we'll parse
     *
     * This will output something like:
     *  
     *   > ozh <ozh@ozh.org>
     *   625     1747    includes/geo/geoip.inc
     *
     *   > Joe <em@i.l>
     *   2       0       .travis.yml
     *
     *   > ozh <ozh@ozh.org>
     *   22      0       CHANGELOG.md
     *   4       0       js/jquery-2.2.4.min.js
     *   
     */
    CONST GIT_CMD = 'git log --use-mailmap --numstat --pretty=format:"> %aN <%aE>" --no-merges';

    /**
     * @var array Will collect the output of the git command
     */
    private $gitlog = array();


    /**
     * @var array Will eventually contain infos for each commit author
     *
     * Eventually this array will contain data structured like the following:
     *   $this->authors = array(
     *       'ozh@ozh.org' => array(
     *               'name'    => 'Ozh',
     *               'email'   => 'ozh@ozh.org',
     *               'commits' => 69,
     *               'delta'   => 850,
     *               '(+)'     => 1000,
     *               '(-)'     => 150,
     *               'files'   => 13,
     *       ),
     *       ...
     *   );
     *
     */
    private $authors = array();


    /**
     * @var array Keys for the $authors array
     */
    private $keys = array(
        'name',
        'commits',
        'delta',
        '(+)',
        '(-)',
        'files',
    );

    /**
     * @var array Widest column in table output
     */
    private $widest = array();


    /**
     * @param $args Command line arguments as passed by class call, see end of file
     */
    public function __construct($args) {
        $cmd = self::GIT_CMD;

        // remove first element of cmd line args, as it's the script file itself, and pass arguments to git
        array_shift($args);
        if (!empty($args)) {
            $cmd .= ' ' . implode(' ', $args);
        }

        $this->get_raw_gitlog($cmd);
        $this->parse_gitlog();
        $this->set_stats_per_author();
        $this->print_stats();
    }

    /**
     * Exec git command and collect its output
     *
     * @param $args Command line arguments as passed by class call, see end of file
     */
    public function get_raw_gitlog($cmd) {
        $this->gitlog = array();

        $handle = popen($cmd, 'r');
        while (!feof($handle)) {
            $this->gitlog[] = fgets($handle, 4096);
        }
        pclose($handle);
    }

    /**
     * Parse raw git output and collect info into the $authors array
     */
    public function parse_gitlog() {
        $current_author = array();

        foreach ($this->gitlog as $line) {

            $line = trim($line);
            /*
            $line can be one this 3 forms:
            "> ozh <ozh@ozh.org>",
            "1337   43   some/file.ext",
            ""
            */

            if ($this->is_new_commit($line)) {
                $current_author = $this->get_author($line);

                if ($this->is_new_author($current_author['email'])) {
                    $this->add_empty_author($current_author);
                    $this->authors[$current_author['email']]['name'] = $current_author['name'];
                }

                $line = '';
            }

            if ($line) {
                $stats = $this->get_stats($line);

                $this->add_stats_to_author(
                    $current_author['email'],
                    array(
                        'commits' => 1,
                        '(+)'     => $stats['added'],
                        '(-)'     => $stats['deleted'],
                    )
                );

                $this->add_files_to_author($current_author['email'], $stats['file']);
            }

        }

    }

    /**
     * Increment values of an author in $authors
     *
     * @param string $email Email which is a key of $this->authors
     * @param array $data Array of $keys=>$values used to increment $keys of $this->authors[$email] with $values
     */
    public function add_stats_to_author($email, $data) {
        foreach ($data as $key => $value) {
            $this->authors[$email][$key] += (int)$value;
        }
    }

    /**
     * Add a value to an array if not already present
     *
     * @param string $email Email which is a key of $this->authors
     * @param string $file  Filename to be uniquely added to $this->authors[$email][$files]
     */
    public function add_files_to_author($email, $file) {
        if (!in_array($file, $this->authors[$email]['files'])) {
            $this->authors[$email]['files'][] = $file;
        }
    }

    /**
     * Print git scores
     */
    public function print_stats() {
        // header
        $this->print_line(array_combine($this->keys, $this->keys));

        // each line
        foreach ($this->authors as $author => $data) {
            $this->print_line($data);
        }
    }

    /**
     * Print tabular line of data
     *
     * First cell will be left aligned (padded right with spaces), subsequent cells are right aligned (padded left)
     * Line of data to be printed must be an array of keys=>values where keys match $this->keys
     *
     * @param array $data Array of (key=>values) to print
     */
    public function print_line($data) {
        $pad = STR_PAD_RIGHT;
        foreach ($this->widest as $key => $len) {
            echo str_pad($data[$key], $len + 1, ' ', $pad) . ' ';
            $pad = STR_PAD_LEFT;

        }
        echo PHP_EOL;
    }

    /**
     * Loop over $this->authors values and compute some stats, also sets the widest value of each key in the $authors array
     */
    public function set_stats_per_author() {

        // init widest cells, default value is the length of $keys (ie the header of the table to be printed)
        foreach ($this->keys as $key) {
            $this->widest[$key] = strlen($key);
        }

        // count files & delta, also get max width for each column
        foreach ($this->authors as $author => $stats) {
            $this->authors[$author]['files'] = $stats['files'] = count($stats['files']);
            $this->authors[$author]['delta'] = $stats['(+)'] - $stats['(-)'];

            foreach ($this->keys as $key) {
                $this->widest[$key] = $this->get_widest($key, $stats[$key]);
            }
        }

        // order author bys number of commits
        usort($this->authors, array($this, 'compare_by_commits'));
    }

    /**
     * Compare two values. Used to sort an array
     *
     * @param array $a Array
     * @param array $b Array
     * @return int 0, 1 or -1. See usort()
     */
    public function compare_by_commits($a, $b) {
        return $a['commits'] < $b['commits'];
    }

    /**
     * Get widest column
     *
     * @param string $key Key
     * @param mixed $key Data (string or integer)
     * @return int
     */
    public function get_widest($key, $stats) {
        return max($this->widest[$key], strlen((string)$stats));
    }

    /**
     * Parse line of git output and return number of added lines, number of deleted, and file name
     *
     * @param string $line Line to parse
     * @return array
     */
    public function get_stats($line) {
        // $line = "1337   43   some/file.ext"
        // $line = "-      -    some/file.bin"
        preg_match('/^([\-|\d]+)\s+([\-|\d]+)\s+(.*)$/', $line, $matches);
        return array('added' => $matches[1], 'deleted' => $matches[2], 'file' => $matches[3]);
    }

    /**
     * Parse line of git output and commit author name and email
     *
     * @param string $line Line to parse
     * @return array
     */
    public function get_author($line) {
        // $line = "> ozh <ozh@ozh.org>"
        preg_match('/^> (.*) <(.*)>$/', $line, $matches);
        return array('name' => $matches[1], 'email' => $matches[2]);
    }

    /**
     * Check if line begins with a '>'
     *
     * @param string $line Line to parse
     * @return bool
     */
    public function is_new_commit($line) {
        // if line starts with a '>' : we're parsing a new commit
        return (strpos($line, '>') === 0);
    }

    /**
     * Check if a given author email is already registered in the authors array
     *
     * @param string $email Email
     * @return bool True if email isn't already a key of $this->authors, false otherwise
     */
    public function is_new_author($email) {
        return !array_key_exists($email, $this->authors);
    }

    /**
     * Add empty array associated to a provided key
     *
     * The array is initialized with values of zero to allow being used in a loop
     * with '+=' without issuing warning.
     *
     * @param array $author Array of ('email'=>email, 'name'=>name)
     */
    public function add_empty_author($author) {
        // Init all keys to 0
        foreach ($this->keys as $key) {
            $this->authors[$author['email']][$key] = 0;
        }
        // But then overwrite these two to something else
        $this->authors[$author['email']]['name']  = $author['name'];
        $this->authors[$author['email']]['files'] = array();
    }

}

new Score($argv);
