<?php

/**
 * @author Cristian Nicolescu <cristi@mandagreen.com>
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 *
 * Usage Examples:
 *   Monitoring Revive Adserver: php theia.php --path /var/www/html/revive/ --exclude "(var\/cache\/.*)|(var\/(.*?)log)|(var\/templates_compiled)"
 *
 */

class Theia_Monitor
{
    const DB_FILE = '.theia.db';
    const VERSION = '1.1';

    protected $signature = "Theia File Monitor";

    protected $args;
    protected $db = null;

    protected $new = array();
    protected $mod = array();
    protected $del = array();

    protected $path = './';

    static public function bootstrap()
    {
        /** @var Theia_Monitor $theia */
        $theia = new self();
        $theia->parseArgs();

        $theia->signature .= ' ' . self::VERSION;

        $theia->path = $theia->getArg('path') ? $theia->getArg('path') : '.';
        if (!is_dir($theia->path)) {
            $theia->path = '.';
        }

        $theia->path = realpath($theia->path);

        if ($theia->getArg('help')) {
            echo $theia->signature;
            echo $theia->help();
            exit;
        }

        if ($theia->getArg('version')) {
            echo $theia->signature;
            exit;
        }

        $theia->loadDb();

        if ($theia->getArg('show-paths')) {
            if (!count((array)$theia->db)) {
                echo "No tracked paths in the database\n";
            } else {
                echo "\nTracked paths:\n";
                foreach (array_keys((array)$theia->db) as $path) {
                    echo "  $path\n";
                }
            }
        } elseif ($theia->getArg('show-files')) {
            if (!count((array)$theia->db) || !isset($theia->db->{$theia->path})) {
                echo "No tracked paths in the database\n";
            } else {
                echo "\nTracked files in {$theia->path}:\n";
                foreach ((array)$theia->db->{$theia->path} as $path => $sha) {
                    echo "  $path\n";
                }
            }
        } else {
            $theia->walk();
            $theia->saveDb();
            $theia->email();
        }

    }

    protected function walk_alone(){
					$objects = scandir( $this->path);
					$array=array();
					foreach($objects as $file){
						$array[$this->path . DIRECTORY_SEPARATOR .$file] = $file;
					}
					return $array;
		}

    protected function walk()
    {
        $path = $this->path;
        if ($this->getArg('alone')){
          $objects=$this->walk_alone();
        }else{
          $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        }


        $found = array();

        if (!isset($this->db->$path)) {
            $this->db->$path = new stdClass;
        }

        foreach ($objects as $name => $object) {
            if (!is_file($name)) {
                continue;
            }

            $ds = '/';
            if ($name == $path . DIRECTORY_SEPARATOR . self::DB_FILE || $name == $path . DIRECTORY_SEPARATOR . $_SERVER['argv'][0]) {
                continue;
            }

            $relativePath = str_replace($path, '.', $name);
            if (DIRECTORY_SEPARATOR != $ds) {
                $relativePath = str_replace(DIRECTORY_SEPARATOR, $ds, $relativePath);
            }
            if ($this->matchExclusion($relativePath)) {
                continue;
            }

            $sha = sha1_file($name);
            $found[] = $relativePath;

            if (!isset($this->db->$path->$relativePath)) {
                $this->new[] = $relativePath;
            } elseif ($this->db->$path->$relativePath != $sha) {
                $this->mod[] = $relativePath;
            }

            $this->db->$path->$relativePath = $sha;
        }

        $this->del = array_diff(array_keys((array)clone $this->db->$path), $found);
        if (count($this->del)) {
            foreach ($this->del as $delPath) {
                unset($this->db->$path->$delPath);
            }
        }

    }

    public function saveDb()
    {
        file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::DB_FILE, json_encode($this->db));
        return $this;
    }

    public function loadDb()
    {
        if (null !== $this->db) {
            return $this->db;
        }

        if (!is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::DB_FILE)) {
            return $this->db = new stdClass;
        }

        return $this->db = json_decode(file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::DB_FILE));
    }

    protected function parseArgs()
    {
        $current = null;
        foreach ($_SERVER['argv'] as $arg) {
            $match = array();
            if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
                $current = $match[1];
                $this->args[$current] = true;
            } else {
                if ($current) {
                    $this->args[$current] = $arg;
                } else if (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                    $this->args[$match[1]] = true;
                }
            }
        }
        return $this;
    }

    public function getArg($name)
    {
        if (isset($this->args[$name])) {
            return $this->args[$name];
        }
        return false;
    }

    protected function matchExclusion($path)
    {
        $exclusion = $this->getArg('exclude');
        if (!$exclusion) {
            return false;
        }

        return @preg_match("/$exclusion/i", $path);
    }

    protected function email()
    {
        if (!count($this->new) && !count($this->mod) && !count($this->del)) {
            return;
        }

        $to = $this->getArg('email');
        if (!$to) {
            echo $this->getReport();
            exit;
        }

        $subject = 'Theia Monitor Report';
        if ($this->getArg('subject')) {
            $subject = $this->getArg('subject');
        }

        if ($this->getArg('from')) {
            $headers[] = 'From: ' . $this->getArg('from');
        }

        $headers[] = 'X-Sender: Theia Monitor ' . self::VERSION;
        if ($cc = $this->getArg('cc')) {
            $headers[] = ' Bcc: ' . $cc;
        }

        $body = $this->getReport();
        $body .= "\n\n";
        $body .= "--\n";
        $body .= "Email sent by {$this->signature}";

        mail($to, $subject, $body, implode("\n", $headers));
    }

    protected function getReport()
    {
        $content = array('Monitored path: ' . $this->path, '');
        if (count($this->new)) {
            $content[] = 'New Files Added';
            $content[] = '----------------';
            foreach ($this->new as $file) {
                $content[] = $file;
            }
            $content[] = '';
            $content[] = '';
        }

        if (count($this->mod)) {
            $content[] = 'Changed Files';
            $content[] = '----------------';
            foreach ($this->mod as $file) {
                $content[] = $file;
            }
            $content[] = '';
            $content[] = '';
        }

        if (count($this->del)) {
            $content[] = 'Deleted Files';
            $content[] = '----------------';
            foreach ($this->del as $file) {
                $content[] = $file;
            }
            $content[] = '';
            $content[] = '';
        }

        return implode("\n", $content);
    }

    /**
     * @return string
     */
    function help()
    {
        global $argv;
        return <<<HELP

Usage: php {$argv[0]} <options>
Options:
    --help        Shows this screen
    --version     Shows version
    --show-files  Shows monitored files in <path>
    --show-paths  Shows monitored paths
    --path        Path to monitor (mandatory for monitoring)
    --exclude     RegExp for excluding files or folders from the monitor
    --email       Email address to send the report to. If none specified
                  the report will be send on stdout
    --from        Email sender (requires <email>)
    --subject     Email  subject (requires <email>)
    --cc          Bcc the report to these addresses (requires <email>)
    --alone       only watch files under first level

HELP;

    }
}

Theia_Monitor::bootstrap();
