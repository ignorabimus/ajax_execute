<?php
/*
 * ajax_execute.php
 */

define("COMMAND", "/path/command");
define("TIMEOUT_USEC", 1000000);
define("LIMIT_ROWS", 1024);
define("LIMIT_COLS", 4096);

$tmpfname = tempnam("/tmp", "TMP");
$handle = fopen($tmpfname, "w");
fwrite($handle, rawurldecode($_POST['script']));
fclose($handle);

$descriptorspec = array(
    1 => array("pipe", "w"),
    2 => array("pipe", "w")
);

$process = proc_open(COMMAND . " " . $tmpfname, $descriptorspec, $pipes);
if (is_resource($process)) {
    $output = '';
    $limit = LIMIT_ROWS;
    $tv_usec = TIMEOUT_USEC;

    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    while (feof($pipes[1]) === false || feof($pipes[2]) === false) {
        $time_start = microtime(true);

        $read = array($pipes[1], $pipes[2]);
        $write = NULL;
        $except = NULL;

        $ret = stream_select($read, $write, $except, 0, $tv_usec);

        if ($ret === false) {
            $output .= "error";
            proc_terminate($process);
            break;
        }

        foreach ($read as $sock) {
            $output .= fgets($sock, LIMIT_COLS);
        }

        $tv_usec -= (integer)((microtime(true) - $time_start) * 1000000);

        if ($ret === 0 || $tv_usec < 0) {
            $output .= "timeout";
            proc_terminate($process);
            break;
        } else if ($limit-- <= 0) {
            $output .= "too long";
            proc_terminate($process);
            break;
        }
    }

    print(json_encode($output));

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

unlink($tmpfname);

/* ajax_execute.php */
