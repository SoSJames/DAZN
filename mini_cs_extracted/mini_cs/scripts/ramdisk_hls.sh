sudo sed -i '/home\/mini_cs/d' /etc/fstab
sleep 2
sudo echo $'\ntmpfs /home/mini_cs/hls tmpfs defaults,noatime,nosuid,nodev,noexec,mode=1777,size=90% 0 0' >> /etc/fstab
sudo mount -av