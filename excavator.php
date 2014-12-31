<?php
/**
 * @author      Bhargav Vadher
 * @version     0.1 2014-12-25 12:24AM (yes it was a Christmas night!)
 * @description This script crawls http://httparchive.org for your site and downloads necessary .har and .csv files.
 *              You can later use these files to analyze your site's behavior or performance.
 * @license     Publicly available to modify and redistribute.
 */

/**
 * Class Excavator
 */
class Excavator
{
	/**
	 * A base url for the site
	 */
	const BASE_URL = 'http://httparchive.org/';

	/**
	 * A base url for the site search functionality
	 */
	const FIND_BASE_URL = 'http://httparchive.org/findurl.php';

	/**
	 * A base url for the view site functionality
	 */
	const VIEW_BASE_URL = 'http://httparchive.org/viewsite.php';

	/**
	 * @var null|string
	 *
	 * The site/query you want to process/search.
	 */
	private $siteUrl = null;

	/**
	 * @var null|string
	 *
	 * Where do you want to store the .har files?
	 */
	private $writeDir = null;

	/**
	 * @var bool
	 *
	 * Do you want the debug mode enabled.
	 */
	private $debug = true;

	/**
	 * @var bool
	 *
	 * A flag for dry mode. In dry mode, the script will do everything as regular mode except the download/save part.
	 */
	private $dryRun = false;

	/**
	 * @param string $siteUrl
	 * @param string $writeDir
	 * @param bool   $dryRun
	 */
	public function __construct($siteUrl, $writeDir, $dryRun = false) {
		$this->siteUrl  = $siteUrl;
		$this->writeDir = $writeDir;
		$this->dryRun   = $dryRun;
	}

	/**
	 * Kick off the script
	 */
	public function init() {
		$base               = self::FIND_BASE_URL;
		$searchJsonResponse = file_get_contents("{$base}?term={$this->siteUrl}");
		$sites              = json_decode($searchJsonResponse, true);
		$siteCount          = count($sites);
		$postFix            = $siteCount > 1 ? 's' : '';

		if ($sites) {
			$this->__debug("Great, ({$siteCount}) site{$postFix} found.\n\n");
			$this->_processSites($sites);
		} else {
			$this->__debug("Sorry, looks like `{$this->siteUrl}` is not available in http://httparchive.org database.\n");
			exit(1);
		}
	}

	/**
	 * @param array $sites
	 */
	private function _processSites(array $sites) {
		$count = count($sites);

		if ($count === 1) {
			$this->_processSingleSite($sites);
		} else {
			$this->_processAllSites($sites);
		}
	}

	/**
	 * @param array $sites
	 *
	 * Process any single site which was selected by the user, when it finds multiple sites.
	 */
	private function _processSingleSite(array $sites) {
		$site = array_shift($sites);
		$url  = $site['value'];
		$this->__debug("Is '{$url}' the correct site(y/n)?: ");
		$input = trim(strtolower($this->_getUserInput()));

		if ($input === 'y' || $input === 'yes') {
			$this->_processSite($site);
		} else if ($input === 'n' || $input === 'no') {
			$this->__debug("Sorry, please refine your search query...\n");
		} else {
			$this->__debug("'{$input}' is not a valid option... exiting.\n");
		}
	}

	/**
	 * @param array $sites
	 *
	 * Process all sites found in the search one by one.
	 */
	private function _processAllSites(array $sites) {
		$count       = count($sites);
		$siteOptions = $this->_getSiteOptions($sites);

		$this->__debug("{$siteOptions}\n");
		$this->__debug("Above sites found. Which one is yours(enter a number for particular site or `all` for all sites)?: ");

		$origInput = trim(strtolower($this->_getUserInput()));
		$input     = (int)$origInput;

		$this->__debug(PHP_EOL);

		if (($input > 0 && $input <= $count) || $origInput === 'all') {
			$key = ($input - 1);

			if ($origInput === 'all') {
				foreach ($sites as $key => $site) {
					$this->_processSite($site);
				}
			} else {
				$this->_processSite($sites[$key]);
			}
		} else {
			$this->__debug("Please select a number from above options only or enter `all`.\n");
			exit(1);
		}
	}

	/**
	 * @return string
	 *
	 * Reads a user's input from CLI and returns the same.
	 */
	private function _getUserInput() {
		if (PHP_OS == 'WINNT') {
			$line = stream_get_line(STDIN, 1024);
		} else {
			$line = readline();
		}

		readline_add_history($line);

		return $line;
	}

	/**
	 * @param array $sites
	 *
	 * @return string
	 *
	 * Returns a "key: url" mapping string to be shown on the CLI
	 */
	private function _getSiteOptions(array $sites) {
		$options = '';
		foreach ($sites as $key => $site) {
			$index = $key + 1;
			$options .= "{$index}: {$site['value']}\n";
		}

		return $options;
	}

	/**
	 * @param array $site
	 *
	 * Processes a site by its url.
	 *      If no runs found, it will show an error message.
	 *      If some runs found, it will process each individual run.
	 */
	private function _processSite(array $site) {
		$this->__debug("Processing {$site['value']}\n");

		$url    = $site['value'];
		$pageId = $site['data-pageid'];

		$viewBaseUrl        = self::VIEW_BASE_URL;
		$siteMainPageSource = $this->_getHtmlSourceByUrl("{$viewBaseUrl}?pageid={$pageId}");

		$htmlSource = $siteMainPageSource;
		$a          = explode(PHP_EOL, $htmlSource);
		$matches    = preg_grep("/<option value='.*'>/i", $a);

		if (empty($matches)) {
			$this->__debug("     sorry no runs found for `{$url}`\n");
		}

		$filteredDates = array();
		foreach ($matches as $match) {
			if (preg_match("/.*'([A-Z]{1}[a-z]{2}\s[0-9]{1,2}\s[0-9]{4}).*'/i", $match, $dateMatch)) {
				$filteredDates[$dateMatch[1]] = $dateMatch[1];
			}
		}

		$runCount = count($filteredDates);
		$postFix  = $runCount > 1 ? 's' : '';
		$this->__debug("     awesome, {$runCount} run{$postFix} found for `{$url}`\n");

		foreach ($filteredDates as $index => $runDate) {
			$this->_processSiteRun($runDate, $url);
		}
	}

	/**
	 * @param String $runDate
	 * @param String $url
	 *
	 * @return bool
	 *
	 * Retrieves an HTML source-code for `$url` and finds the download url. Once the download url is found, it will
	 * download it in `$this->writeDir` directory
	 */
	private function _processSiteRun($runDate, $url) {
		$this->__debug("     processing the run for {$runDate}\n");

		$site = urlencode($url);
		$date = str_replace(" ", "%20", $runDate);

		$viewSiteBase = self::VIEW_BASE_URL;
		$siteRunUrl   = "{$viewSiteBase}?u={$site}&l={$date}";

		$htmlSource = $this->_getHtmlSourceByUrl($siteRunUrl);

		$this->_downloadDetailsHarFile($htmlSource, $runDate);
		$this->_downloadRequestCsvFile($htmlSource, $runDate);
	}

	/**
	 * @param string $htmlSource
	 * @param string $runDate
	 *
	 * Search and download the .har file containing the full details for this run
	 */
	private function _downloadDetailsHarFile($htmlSource, $runDate) {
		$this->__debug("          downloading .har file with full details.\n");

		if ($this->dryRun) {
			return true;
		}

		$a       = explode(PHP_EOL, $htmlSource);
		$matches = preg_grep("/http:\/\/httparchive\.webpagetest\.org\/export\.php/i", $a);

		if (empty($matches)) {
			$this->__debug("               sorry no download link for .har file found.\n");

			return false;
		}

		foreach ($matches as $key => $downloadUrl) {
			if (preg_match("/'(http:\/\/httparchive.webpagetest.org\/export.php\?test=.*)'/i", $downloadUrl, $downloadMatches)) {
				$this->__debug("          downloading...  {$downloadMatches[1]}\n");
				$fileName = str_replace(" ", "-", $runDate) . '.har';
				$filePath = rtrim($this->writeDir, '/') . "/{$fileName}";
				$command  = "curl -s -o {$filePath} '{$downloadMatches[1]}'";

				shell_exec($command);
				$this->__debug("          downloaded...   {$filePath}\n");
			} else {
				$this->__debug("               sorry the download link for .har file can not be found.\n");
			}
		}
	}

	/**
	 * @param string $htmlSource
	 * @param string $runDate
	 *
	 * Search and download the .csv file containing details for all http requests made for this run
	 */
	private function _downloadRequestCsvFile($htmlSource, $runDate) {
		$this->__debug("          downloading .csv file with all requests.\n");

		if ($this->dryRun) {
			return true;
		}

		$a       = explode(PHP_EOL, $htmlSource);
		$matches = preg_grep("/download\.php/i", $a);

		if (empty($matches)) {
			$this->__debug("               sorry no download link for .csv file found.\n");

			return false;
		}

		foreach ($matches as $key => $downloadUrl) {
			if (preg_match("/\"(download\.php\?p=[0-9]+\&format=csv)\"/i", $downloadUrl, $downloadMatches)) {
				$fileName      = str_replace(" ", "-", $runDate) . '.csv';
				$filePath      = rtrim($this->writeDir, '/') . "/{$fileName}";
				$remoteCsvPath = self::BASE_URL . $downloadMatches[1];
				$this->__debug("          downloading...  {$remoteCsvPath}\n");
				$command = "curl -s -o {$filePath} '{$remoteCsvPath}'";

				shell_exec($command);
				$this->__debug("          downloaded...   {$filePath}\n");
			} else {
				$this->__debug("               sorry the download link for .csv file can not be found.\n");
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return string
	 *
	 * Gets the URL's HTML source code
	 */
	private function _getHtmlSourceByUrl($url) {
		return file_get_contents($url);
	}

	/**
	 * @param string $message
	 *
	 * Prints a message on the CLI
	 */
	private function __debug($message = PHP_EOL) {
		if ($this->debug) {
			echo "$message";
		}
	}
}

// START CLI
$args = getopt("s:d:h::", array('dry::', 'help::'));

if (isset($args['h']) || isset($args['help'])) {
	print "   -s          (mandatory) is used to take site url\n";
	print "   -d          (mandatory) is used to take local path where the files will be downloaded\n";
	print "   --dry       (optional) is used for dry run(no download)\n";
	print "   -h,--help   (optional) is used to get help about this script\n";
	print "Example:\n";
	print "./excavator.php -s http://www.example.com -d /tmp/data/ [--dry, -h, --help] \n\n";
	exit(0);
}

if (empty($args['s']) || empty($args['d'])) {
	print "\nBoth `s` and `d` arguments required. Please try again ...\n";
	print "Usage: ./excavator.php -s http://www.example.com -d /tmp/data/ [--dry] \n\n";
	exit(1);
}

$dryRun = false;
if (isset($args['dry'])) {
	$dryRun = true;
}

$site = $args['s'];
$dir  = realpath($args['d']);

if (!$dir || !is_dir($dir) || !is_writable($dir)) {
	print "`{$args['d']}` is not a directory. Make sure you have that directory writable ...\n";
	exit(1);
}

print "Download folder set to {$dir}\n";

$excavator = new Excavator($site, $dir, $dryRun);
$excavator->init();
print "Finished\n";
// END CLI