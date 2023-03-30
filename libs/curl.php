<?php

    function crawlUrl(string $url): stdClass
    {
        global $ssl;
        $fetch = ($ssl ? 'https://' : 'http://') . $url;
        $log = "fetching: $fetch ... ";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $fetch,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER => [
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Cache-Control: no-cache",
                "Connection: keep-alive",
                "User-Agent: Curl (php-sitemap-xml)"
            ]
        ]);

        $content = curl_exec($curl);

        if(curl_errno($curl) === 0) $error = false;
        else $error = curl_error($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($status !== 200 && $status !== 301 && $status !== 302)
            $error = $status;

        curl_close($curl);

        $log .= $error === false ? "Ok." : "KO!";
        dispatchLogs($log);

        return (object) [
            'error'   => $error,
            'status'  => $status,
            'content' => $content
        ];
    }

    function pingUrl(string $ping): bool
    {
        $log = "pinging: $ping ... ";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $ping,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER => [
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Cache-Control: no-cache",
                "Connection: keep-alive",
                "User-Agent: Curl (php-cli-sitemap-generator)"
            ]
        ]);

        curl_exec($curl);
        $response = curl_errno($curl) === 0 && curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200;
        curl_close($curl);

        $log .= $response === true ? "Ok." : "KO!";
        dispatchLogs($log);

        return $response;
    }