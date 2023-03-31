<?php

    require_once dirname(__DIR__, 1)."/config.php";
    require_once dirname(__DIR__)."/libs/simplehtmldom-1.9.1/simple_html_dom.php";
    require_once dirname(__DIR__)."/libs/logs.php";
    require_once dirname(__DIR__)."/libs/curl.php";


    foreach ($domains_to_crawl as $domain) {
        $xml  = [];

        // NOTE Get starting uri
        $slashes = explode("/", $domain);
        $domain = array_shift($slashes);
        $startWith = count($slashes) > 0 ? $startWith . implode("/", $slashes) : $startWith;

        $uri_to_crawl = [
            $startWith
        ];


        // NOTE check langHref
        $response = crawlUrl($domain.$startWith);
        if ($response->error !== false) {
            dispatchLogs(sprintf("%s : %s", $domain.$startWith, "Check StartWith OR SSL configuration"));
            die;
        } else {
            $html = str_get_html($response->content);

            foreach ($html->find('link') as $link) {
                if ($link->rel === 'alternate' && $link->hreflang !== "x-default") {
                    $lang = $link->hreflang;

                    if(stripos($lang, "-") === 2) $lang = substr($lang, 0, 2);
                    elseif (stripos($lang, "-") !== false) continue;

                    $hrefLang[] = "/".str_replace("/", "", $lang)."/";
                }
            }

            $hrefLang = array_unique($hrefLang);

            // NOTE reset startWith
            if(!empty($hrefLang)) {
                $uri_to_crawl = $hrefLang;
                $startWith = $uri_to_crawl[0];
            }
        }


        // NOTE Get robots.txt
        $response = crawlUrl($domain . "/robots.txt");
        if ($response->error !== false) {
            dispatchLogs("robots.txt not found");
        } else {
            preg_match_all("/Disallow:(.+)/", $response->content, $matches, );
            if (isset($matches[1])) foreach ($matches[1] as $match) $uri_to_bind[] = preg_replace(["/\n/", "/\"/", "/\s+/"], "", $match);
        }


        // NOTE start crawling
        $i = 0;
        do {
            $uri = $uri_to_crawl[$i];

            $response = crawlUrl($domain.$uri);
            if ($response->error !== false) {
                dispatchLogs(sprintf("%s%s : %s", $domain, $uri, $response->error));
            } else {
                $html = str_get_html($response->content);
                if(is_bool($html)) {
                    dispatchLogs(sprintf("%s%s : %s", $domain, $uri, 'Check memory_limit'));
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
                            if($uri !== $startWith) {
                                $previous = explode("/", $uri); array_pop($previous);
                                $previous = implode("/", $previous) . "/";
                            } else {
                                $previous = $startWith;
                            }
                            $link = str_replace("./", $previous, $link);
                            break;
                        case stripos($link, "https://".$domain.$uri) === 0:
                            $link = str_replace("https://".$domain.$uri, "/", $link);
                            break;
                        case stripos($link, "http://".$domain.$uri) === 0:
                            $link = str_replace("http://".$domain.$uri, "/", $link);
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

                    // NOTE remove bind uri
                    foreach ($uri_to_bind as $bind) {
                        if(stripos($bind, "*") !== false)
                            $regex = "/^".str_replace(["*", "/"], ["", "\/"], $bind).".*/";
                        else
                            $regex = "/^".str_replace(["/"], ["\/"], $bind)."$/";

                        if(preg_match($regex, $link, $matches) != 0)
                            $link = "";
                    }

                    // NOTE Add to crawl if not yet added
                    if($link !== "" && !in_array($link, $uri_to_crawl)) {
                        $uri_to_crawl[] = $link;
                    }
                }

                // NOTE Add to XML
                $xml[$uri] = ['loc' => ($ssl ? "https://" : "http://") . $domain . $uri];
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

        $sitemapXML = file_get_contents(dirname(__DIR__, 1).'/map.xml');
        $sitemapXML = str_replace("{{domain}}", ($ssl ? "https://" : "http://") . $domain, $sitemapXML);
        $sitemapXML = str_replace("{{URLS}}", $URLS, $sitemapXML);

        $dir = ($exportDir !== dirname(__DIR__, 1)."/exports") ? $exportDir : "$exportDir/$domain";
        if(!is_dir($dir)) mkdir($dir);

        dispatchLogs("building: $domain sitemap in $dir");
        $file = fopen("$dir/sitemap.xml", "w");
        fwrite($file, $sitemapXML);
        fclose($file);


        // NOTE ping search engines with new sitemap
        foreach ($engines_to_ping as $url) pingUrl($url . ($ssl ? "https://" : "http://") . $domain . "/sitemap.xml");
    }

    exit("DONE");