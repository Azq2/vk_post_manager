#!/bin/bash
cd "$(dirname $0)"

while [[ true ]];
do
	php grabber_downloader.php >> grabber_downloader.php.log
	inotifywait -r -e modify -e create ../tmp/post_queue
done
