#!/bin/bash
cd "$(dirname $0)"

while [[ true ]];
do
	php grabber_downloader.php
	inotifywait -r -e modify -e create ../tmp/post_queue
done
