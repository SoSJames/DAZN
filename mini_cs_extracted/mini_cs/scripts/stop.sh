#! /bin/bash
sudo killall -9 -u mini_cs nginx
sudo killall -9 -u mini_cs php-fpm
sudo killall -9 -u mini_cs ffmpeg
sudo killall -9 -u mini_cs php
sudo rm -rf /home/mini_cs/video/*
sudo rm -rf /home/mini_cs/hls/*
sudo rm -f /home/mini_cs/cache/*.db
sudo rm -f /home/mini_cs/php/daemon.sock