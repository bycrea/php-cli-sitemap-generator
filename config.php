<?php

    ini_set('memory_limit', '1024M');

    function dump(...$var)
    {
        foreach ($var as $v) {
            if(is_array($v) || is_object($v)) var_dump($v);
            else echo "$v\n";
        }
    }

    function dd(...$var)
    {
        dump($var);
        echo "\n";
        die;
    }


    /*** NOTE Default config
     * @var $echos bool                 // toggle logs
     * @var $domains_to_crawl array     // domains to crawl
     * @var $exportDir string           // where to export sitemap.xml
     * @var $frequency string           // hourly|daily|weekly|monthly|yearly|never
     * @var $ssl bool                   // enable ssl true=https:// | false=http://
     * @var $startWith string           // can be reset by $hrefLang if link rel=alternate are found
     * @var $hrefLang array             // hrefLang (also feed with link rel=alternate) ex: ["/en/", "/fr/"]
     * @var $uri_to_bind array          // pages to bind (also feed with robots.txt) regex allow ex: ["/privacy", "/user*"]
     * @var $extensions_allow array     // use for replacement /index.(ext) => / || add file.(ext) to sitemap
     * @var $custom_priority array      // custom sitemap priority
     * @var $engines_to_ping array      // ping new sitemap to search engine (only use when exportDir is the real $domain path)
     */

    $echos = true;

    $domains_to_crawl   = [];
    $exportDir          = __DIR__."/exports";
    $frequency          = "monthly";
    $ssl                = true;
    $startWith          = "/";
    $hrefLang           = [];
    $uri_to_bind        = [];
    $extensions_allow   = [
        "html",
        "php"
    ];
    $custom_priority    = [
        "1.0" => [],
        "0.8" => [],
        "0.5" => [],
        "0.3" => [],
        "0.1" => []
    ];
    $engines_to_ping   = [
        "https://www.google.com/webmasters/sitemaps/ping?sitemap="
    ];



    /***
     * NOTE CLI Config
     * @example php path_to_app/generate (REQUIRED)www.domain.com (OPTIONAL)daily
     * @important configs/domain.com.json file must be set
     */

    if($argc > 1)
    {
        $domain = str_replace("/", "", $argv[1]);
        if(!filter_var("http://".$domain, FILTER_VALIDATE_URL)) die('CLI Invalid $domain');

        $configFile = explode("/", str_replace("www.", "", $domain))[0] ?? null;
        $configFile = __DIR__."/configs/$configFile.json";
        if(!file_exists($configFile)) die("Missing config file $configFile");
        else $config = json_decode(file_get_contents($configFile));

        foreach ($config as $key => $values) $$key = $values;

        $frequency = $argv[2] ?? $frequency;
        $domains_to_crawl = [$domain];
    } elseif(!empty($domains_to_crawl)) {
        foreach ($domains_to_crawl as &$domain) {
            $domain = str_replace("/", "", $domain);
            if(!filter_var("http://".$domain, FILTER_VALIDATE_URL)) die('PHP Invalid $domains_to_crawl');
        }
    } else {
        die('Missing $domains_to_crawl');
    }

    if(stripos($exportDir, __DIR__) !== false)
        $engines_to_ping = [];