<?php

    function dispatchLogs(string $logs): void
    {
        global $echos;
        $echos ? echoLogs($logs) : storeLogs($logs);
    }

    function storeLogs(string $log): void
    {
        $file  = dirname(__DIR__, 1)."/logs.log";
        $logs  = file_get_contents($file);
        $logs .= date('Y/m/d H:i:s') . " - $log";
        file_put_contents($file, $logs);
    }

    function echoLogs(string $log): void
    {
        echo "$log\n";
    }