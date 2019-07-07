<?php
/**
 * Create a CLI Tool that will accept a URL for a StadiumGoods Product Listing Page (PLP)
 * and output a csv with columns "Product Name" and "Price"
 */
require __DIR__ . '/lib/cloudflare-bypass-master/src/autoload.php';
require __DIR__ . '/lib/simple_html_dom/simple_html_dom.php';

use CloudflareBypass\RequestMethod\CFCurl;

$valid_hostnames = ['stadiumgoods.com', 'www.stadiumgoods.com'];

/**
 * Parse CLI opts and return
 *
 * @return array
 */
function parse_opts(){
    print("parse_opts: Parsing CLI Options" . PHP_EOL);
    $return_values = [];
    $opts = getopt('u:h', ['url:', 'help']);

    // Basic 'help' output
    if(isset($opts['help']) || isset($opts['h'])){
        print('usage: php stadium_goods.php [-u url] [--url=url]' . PHP_EOL);
        exit(0);
    }

    // Check our opts (via 'php -f stadium_goods.php -u url | --url=url') otherwise fallback
    // to no named opts (in case user does something like 'php -f stadium_goods.php url')
    if(isset($opts['u']) || isset($opts['url'])){
        $return_values['url'] = isset($opts['u']) ? $opts['u'] : $opts['url'];
    } else if(count($opts) == 0 && $_SERVER['argc'] > 1){
        $return_values['url'] = $_SERVER['argv'][1];
    }

    return $return_values;
}

/**
 * Basic helper function to get a given URL
 * @param     $url
 * @param bool $curl_cf_wrapper
 * @param int $timeout
 *
 * @return bool|string
 */
function get_url($url, $curl_cf_wrapper = NULL, $timeout = 5){
    print("get_url: Fetching $url" . PHP_EOL);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

    if(is_null($curl_cf_wrapper)) {
        $html = curl_exec($ch);
    } else {
        $html = $curl_cf_wrapper->exec($ch);
    }
    curl_close($ch);

    if($html){
        return $html;
    }
    return FALSE;
}

// Parse opt and check that we have a valid URL and hostname fits our gated hostnames
$opts = parse_opts();
if(!isset($opts['url'])){
    print('No URL given, exiting.' . PHP_EOL);
    exit(1);
}

$hostname = parse_url($opts['url'], PHP_URL_HOST);
if (!filter_var($opts['url'], FILTER_VALIDATE_URL) || !in_array($hostname, $valid_hostnames)) {
    print("Please enter a valid URL (e.g. https://www.stadiumgoods.com/adidas)" . PHP_EOL);
    exit(1);
}

// We have to bypass CloudFlare's DDoS protection, so let's start a wrapper
try {
    $curl_cf_wrapper = new CFCurl(array(
        'max_retries' => 3,
        'cache' => FALSE,
        'cache_path' => '/tmp',
        'verbose' => FALSE
    ));
} catch (Exception $e){
    print("Unable to start CloudFlare Wrapper: " . $e->getMessage());
    exit(1);
}

// Fetch the URL content
// Note - we could have used simple_html_dom's load_file, but it would not bypass CloudFlare - which would fail
// from a server, but would be fine on a computer with a browser that previously visited the site
$html[] = get_url($opts['url'], $curl_cf_wrapper);

if(count($html) == 0){
    print("Could not fetch HTML.");
    exit(1);
}

$dom = new simple_html_dom();
$dom->load($html[0]);

// Check if there are more pages
$additional_pages = $dom->find('.sg-pager > ul > li > a');
foreach($additional_pages as $page){
    $html[] = get_url($page->href, $curl_cf_wrapper);
}

$csv_output = [];
foreach($html as $html_pages) {
    $dom->load($html_pages);
    foreach ($dom->find('.product-name > a') as $product) {
        $product_name = $product->innertext;
        $meta = $product->parent->parent->find('meta[itemprop="price"]');
        $product_price = $meta[0]->content;
        $csv_output[] = [$product_name, $product_price];
    }
}

$csv_h = fopen('./stadium_goods.csv', 'w');
print('"Product Name","Price"');
foreach($csv_output as $csv_line){
    print('"' . $csv_line[0] . '",' . $csv_line[1] . PHP_EOL);

    // also output it to a file
    fputcsv($csv_h, $csv_line);
}
fclose($csv_h);










