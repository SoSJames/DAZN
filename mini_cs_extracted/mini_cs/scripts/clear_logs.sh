rm /home/mini_cs/logs/error.log
cd /home/mini_cs/logs/build/ && find . -name "*.log" -print0 | xargs -0 rm
cd /home/mini_cs/logs/ffmpeg/ && find . -name "*.log" -print0 | xargs -0 rm
rm /home/mini_cs/tmp/*.txt
