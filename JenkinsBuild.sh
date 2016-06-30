#!/opt/php/bin/php
<?php

$build = trim($_SERVER["argv"][1]);
$project = "Synapse";
$ver = "1.0dev";
$main = "src/synapse/Synapse.php";

$synapse = str_replace('const VERSION = "' . $ver, 'const VERSION = "' . $ver . '-' . $build, file_get_contents($main));
file_put_contents($main, $synapse);
$server = proc_open(PHP_BINARY . " $main --disable-readline", [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"]
], $pipes);
sleep (5);
fwrite($pipes[0], "version\n");
sleep (5);
fwrite($pipes[0], "ms\n");
sleep (5);
fwrite($pipes[0], "stop\n");
while(!feof($pipes[1])){
	echo fgets($pipes[1]);
}
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
rename("/opt/data-2T/jenkins/jobs/$project/workspace/plugins/$project/{$project}_$ver-$build.phar","/opt/data-2T/jenkins/jobs/$project/workspace/artifact/{$project}_$ver-$build.phar");
if(file_exists("/opt/data-2T/jenkins/jobs/Synapse/workspace/artifact/{$project}_$ver-$build.phar")) exit (0);
exit (1);
