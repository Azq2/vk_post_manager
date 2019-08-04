#!/bin/bash
ps aux | grep Smm/Task/Catificator | awk '{print $2}' | xargs kill -9
ps aux | grep Smm/Task/Downloader | awk '{print $2}' | xargs kill -9

