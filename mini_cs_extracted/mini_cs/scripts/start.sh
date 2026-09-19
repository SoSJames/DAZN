#! /bin/bash
sudo chown -R mini_cs:mini_cs /home/mini_cs
sudo killall -9 -u mini_cs nginx
sudo killall -9 -u mini_cs php-fpm
sudo killall -9 -u mini_cs ffmpeg
sudo killall -9 -u mini_cs php
sleep 3
sudo killall -9 -u mini_cs nginx
sudo killall -9 -u mini_cs php-fpm
sudo killall -9 -u mini_cs ffmpeg
sudo killall -9 -u mini_cs php
sleep 3
sudo rm -rf /home/mini_cs/video/*
sudo rm -rf /home/mini_cs/hls/*
sudo rm -f /home/mini_cs/cache/*.db
sudo rm -f /home/mini_cs/php/daemon.sock
sudo -u mini_cs /home/mini_cs/nginx/sbin/nginx
sudo -u mini_cs start-stop-daemon --start --quiet --pidfile /home/mini_cs/php/daemon.pid --exec /home/mini_cs/php/sbin/php-fpm -- --daemonize --fpm-config /home/mini_cs/php/etc/daemon.conf
sudo -u mini_cs /home/mini_cs/php/bin/php /home/mini_cs/includes/restore.php