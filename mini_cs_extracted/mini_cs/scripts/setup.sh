adduser --system --shell /bin/false --group --disabled-login mini_cs
chown -R mini_cs:mini_cs /home/mini_cs
apt-get -y install libxslt1-dev nscd htop libonig-dev libzip-dev software-properties-common aria2
add-apt-repository ppa:xapienz/curl34 -y
apt-get update
apt-get install libcurl4 curl
wget -q -O /tmp/libpng12.deb http://mirrors.kernel.org/ubuntu/pool/main/libp/libpng/libpng12-0_1.2.54-1ubuntu1_amd64.deb
dpkg -i /tmp/libpng12.deb
apt-get install -y
rm /tmp/libpng12.deb
chmod +x /home/mini_cs/bin/ffmpeg
chmod +x /home/mini_cs/bin/mp4decrypt
chmod +x /home/mini_cs/php/bin/php
chmod +x /home/mini_cs/php/sbin/php-fpm
chmod +x /home/mini_cs/nginx/sbin/nginx
ufw allow 18000
ufw allow 18001