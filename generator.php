<?php

    require_once "lib/simplehtmldom-1.9.1/simple_html_dom.php";
    require_once "lib/curl.php";
    require_once "config.php";


    foreach ($websites_to_crawl as $website) {
        $error_logs = [];
        $xml = [];

        // NOTE Get starting uri
        $slashes = explode("/", $website);
        $website = array_shift($slashes);
        $startWith = count($slashes) > 0 ? $startWith . implode("/", $slashes) : $startWith;

        $uri_to_crawl = [
            $startWith
        ];

        // NOTE check langHref
        $response = crawlUrl($website.$startWith, $ssl);
        if ($response->error !== false) {
            $error_logs[] = sprintf("%s%s : %s", $website, $website.$startWith, $response->error);
        } else {
            $html = str_get_html($response->content);

            foreach ($html->find('link') as $link) {
                if ($link->rel === 'alternate' && $link->hreflang !== "x-default")
                    $hrefLang[] = "/$link->hreflang/";
            }

            $hrefLang = array_unique($hrefLang);

            // NOTE reset startWith
            if(!empty($hrefLang)) {
                $uri_to_crawl = $hrefLang;
                $startWith = $uri_to_crawl[0];
            }
        }


        // NOTE Get robots.txt
        $response = crawlUrl($website . "/robots.txt", $ssl);
        if ($response->error !== false) {
            $error_logs[] = "robots.txt not found";
        } else {
            preg_match_all("/Disallow:(.+)/", $response->content, $matches, );
            if (isset($matches[1])) foreach ($matches[1] as $match) $uri_to_bind[] = preg_replace(["/\n/", "/\"/", "/\s+/"], "", $match);
        }

        // NOTE start crawling
        $i = 0;
        do {
            $uri = $uri_to_crawl[$i];
            if(in_array($uri, $uri_to_bind) || in_array($uri."*", $uri_to_bind))
                continue;

            $response = crawlUrl($website.$uri, $ssl);
            if ($response->error !== false) {
                $error_logs[] = sprintf("%s%s : %s", $website, $uri, $response->error);
            } else {
                $html = str_get_html($response->content);
                if(is_bool($html)) {
                    echo sprintf("%s%s : %s", $website, $uri, 'simplehtmldom error (check memory_limit)');
                    die;
                }

                $links = [];
                foreach ($html->find('a') as $a) {
                    $link = $a->href;

                    // NOTE bind nofollow
                    if($a->rel && stripos($a->rel, "nofollow") !== false) continue;

                    // NOTE bind anchor
                    if(stripos($link, "#") !== false) $link = explode("#", $link)[0];

                    switch (true) {
                        case stripos($link, "./") === 0:
                            $link = str_replace("./", $startWith, $link);
                            break;
                        case stripos($link, "https://".$website.$uri) === 0:
                            $link = str_replace("https://".$website.$uri, "/", $link);
                            break;
                        case stripos($link, "http://".$website.$uri) === 0:
                            $link = str_replace("http://".$website.$uri, "/", $link);
                            break;
                        case stripos($link, "/") === 0 && (strlen($link) >= strlen($uri) || in_array($link, $hrefLang)):
                            break;
                        default:
                            $link = "";
                            break;
                    }

                    // NOTE Replace index(.ext_allow)
                    if(preg_match("/(?:index\.(".implode("$|", $extensions_allow)."$))/", $link, $matches) == 1)
                        $link = str_replace("index.".$matches[1], "", $link);

                    // NOTE Ignore !(ext_allow)
                    elseif (preg_match("/^.*\.(?!".implode("$|", $extensions_allow)."$)[^.]+$/", $link) == 1)
                        $link = "";

                    // TODO replace !in_array($link, $uri_to_bind) => array_map( 'bindRegex', $uri_to_bind )
                    // NOTE Add to crawl if not bind and unique
                    if($link !== "" && !in_array($link, $uri_to_bind) && !in_array($link, $uri_to_crawl)) {
                        $uri_to_crawl[] = $link;
                    }

                    // NOTE store links for this $uri
                    //  TODO check internal mesh
                    if($link !== "") $links[] = $link;
                }

                // NOTE Add to XML
                $xml[$uri] = [
                    'links' => $links,
                    'loc'   => ($ssl ? "https://" : "http://") . $website . $uri
                ];
            }

            $i++;
        } while (isset($uri_to_crawl[$i]));


        // NOTE Customise priority
        foreach ($xml as $uri => $values)
        {
            $calcPriority = NULL;
            if(in_array($uri, $hrefLang) || $uri === $startWith) {
                $calcPriority = 1;
            } else {
                foreach($custom_priority as $value => $matches) {
                    if(!empty($matches) && preg_match("/(\/".implode("|\/", $matches).")/", $uri) != 0)
                        $calcPriority = (float)$value + 0.1;
                }

                $slashes = explode("/", ltrim($uri, "/"));
                if(isset($slashes[0]) && in_array("/".$slashes[0]."/", $hrefLang))
                    array_shift($slashes);

                $calcPriority = max(($calcPriority ?? 0.9) - (count($slashes) * 0.1),0.1);
            }

            $xml[$uri]['priority'] = number_format($calcPriority, 2, '.', '');
        }

        uasort($xml, function ($a, $b) {
            if($a['priority'] === $b['priority']) return 0;
            return (float)$a['priority'] > (float)$b['priority'] ? -1 : 1;
        });

        // NOTE build XML
        echo "building sitemap ...\n";

        $lastModif = gmdate("Y-m-d\TH:i:s\Z");
        $URLS = "";
        foreach ($xml as $uri => $values) {
            $URLS .= "
            <url>
                <loc>{$values['loc']}</loc>
                <changefreq>{$frequency}</changefreq>
                <lastmod>{$lastModif}</lastmod>
                <priority>{$values['priority']}</priority>
            </url>
            ";
        }

        $sitemapXML = file_get_contents(__DIR__ . '/map.xml');
        $sitemapXML = str_replace("{{website}}", $website, $sitemapXML);
        $sitemapXML = str_replace("{{URLS}}", $URLS, $sitemapXML);

        $website = explode("/", str_replace("www.", "", $website))[0];
        $dir = "$exportDir/$website";
        if(!file_exists($dir) || !is_dir($dir)) mkdir($dir);

        $file = fopen("$dir/sitemap.xml", "w");
        fwrite($file, $sitemapXML);
        fclose($file);
    }

    exit("DONE\n");