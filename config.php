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
     * @var $websites_to_crawl array    // websites to exec
     * @var $exportDir string           // where to export sitemap.xml
     * @var $frequancy string           // hourly|daily|weekly|monthly|yearly|never
     * @var $ssl bool                   // true=https:// | false=http://
     * @var $startWith string           // can be reset by $hrefLang
     * @var $hrefLang array             // ex: ["/fr/", "/en/"] (also feed with link rel=alternate)
     * @var $uri_to_bind array          // pages to bind (also feed with robots.txt)
     * @var $extensions_allow array     // use for replacement (/index.(ext) => /) || file.(ext) allow
     * @var $custom_priority array      // custom sitemap priority
     */

    $websites_to_crawl = [];
    $exportDir = __DIR__."/exports";
    $frequency  = "monthly";
    $ssl        = true;
    $startWith  = "/";
    $hrefLang = [];
    $uri_to_bind = [];
    $extensions_allow = [
        "html",
        "php"
    ];
    $custom_priority = [
        "1.0" => [],
        "0.8" => ['article', 'recette', 'blog'],
        "0.5" => ['trouver', 'press'],
        "0.3" => [],
        "0.1" => ['contact']
    ];



    /***
     * NOTE CLI Config
     * @example php generator.php www.domain.com
     * @info configs/domain.com.json file must be set
     */

    if($argc > 1)
    {
        $url = $argv[1];
        if(!filter_var("http://".$url, FILTER_VALIDATE_URL)) die('Invalid URL');

        $configFile = explode("/", str_replace("www.", "", $url))[0] ?? null;
        $configFile = __DIR__."/configs/$configFile.json";
        if(!file_exists($configFile)) die("Missing config file $configFile");
        else $config = json_decode(file_get_contents($configFile));

        foreach ($config as $key => $values) $$key = $values;
        $websites_to_crawl = [$url];
    }