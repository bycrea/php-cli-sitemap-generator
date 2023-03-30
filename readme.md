## PHP CLI Sitemap Generator (XML)

Use to generate XML Sitemap from online website


### Configuration

Change config.php variables

```
bool    $echos                  // toggle logs
array   $domains_to_crawl       // domains to crawl
string  $exportDir              // where to export sitemap.xml
string  $frequancy              // hourly|daily|weekly|monthly|yearly|never
bool    $ssl                    // enable ssl true=https:// | false=http://
string  $startWith              // can be reset by $hrefLang if link rel=alternate are found
array   $hrefLang               // hrefLang (also feed with link rel=alternate) ex: ["/en/", "/fr/"]
array   $uri_to_bind            // pages to bind (also feed with robots.txt) regex allow ex: ["/privacy", "/user*"]
array   $extensions_allow       // use for replacement /index.(ext) => / || add file.(ext) to sitemap
array   $custom_priority        // custom sitemap priority
array   $engines_to_ping        // ping new sitemap to search engine
```
***NB: only use $engines_to_ping when exportDir is the real $domain path***
<br>

### CLI generate

Use CRON to generate sitemap automatically

```
1 0 * * *   php path_to_app/generate (REQUIRED){domain} (OPTIONAL){frequency}
```

Generate command need's a JSON domain.com config file stored in /configs
Use /configs/domain.com.json as a template

```
cp path_to_app/configs/domain.com.json path_to_app/configs/eample.com.json
```

JSON file

```
{
  "exportDir": "/var/www/example.com",
  "frequency": "daily",
  "ssl": true,
  "startWith": "/",
  "hrefLang": ["/en/"],
  "uri_to_bind": ["/tcu", "/privacy*"],
  "extensions_allow": ["php"],
  "custom_priority": {
    "1.0": [],
    "0.8": ["blog"],
    "0.5": [],
    "0.3": [],
    "0.1": ["contact"]
  },
  "engines_to_pings": [
    "https://www.google.com/webmasters/sitemaps/ping?sitemap="
  ]
}
```

<br>

### Information
Insert your sitemap in *robots.txt*

```
User-agent: *
Allow: /
Disallow: /test
Sitemap: https://example.com/sitemap.xml
```

<br>

### Useful links
https://www.sitemaps.org/
<br>
http://robots-txt.com/sitemaps/
<br>
https://sourceforge.net/projects/simplehtmldom/

<br>

#### More
Feel free to contact me if you have any useful idea to improve this app *bycrea@gmail.com*