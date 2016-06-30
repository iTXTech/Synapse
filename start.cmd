@echo off
TITLE Synapse Proxy Server
cd /d %~dp0

if exist bin\php\php.exe (
	set PHPRC=""
	set PHP_BINARY=bin\php\php.exe
) else (
	set PHP_BINARY=php
)

if exist Synapse*.phar (
	set SYNAPSE_FILE=Synapse*.phar
) else (
	if exist src\synapse\Synapse.php (
	    set SYNAPSE_FILE=src\synapse\Synapse.php
    ) else (
        if exist Synapse.phar (
           set SYNAPSE_FILE=Synapse.phar
        ) else (
		    echo "[ERROR] Couldn't find a valid Synapse installation."
		    pause
		    exit 8
	    )
	)
)

if exist bin\mintty.exe (
	start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o Font="Consolas" -o FontHeight=10 -o CursorType=0 -o CursorBlinks=1 -h error -t "Synapse" -w max %PHP_BINARY% %SYNAPSE_FILE% --enable-ansi %*
) else (
	%PHP_BINARY% -c bin\php %SYNAPSE_FILE% %*
)