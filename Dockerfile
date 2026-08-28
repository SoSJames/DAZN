FROM ubuntu:18.04

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /app

# Copy the packaged mini_cs zip into the image
COPY mini_cs.zip /app/mini_cs.zip

# Install runtime and build dependencies referenced in the README
RUN apt-get update
&& apt-get install -y --no-install-recommends software-properties-common ca-certificates wget
&& add-apt-repository universe || true
&& apt-get update
&& apt-get install -y --no-install-recommends
unzip apache2 libapache2-mod-php php7.2 php7.2-mbstring php7.2-xml php7.2-curl php7.2-zip
libxslt1-dev nscd libonig-dev libzip-dev aria2 curl wget ca-certificates procps supervisor
&& rm -rf /var/lib/apt/lists/*

# Unpack the archive to /home (matches README instructions)
RUN if [ -f /app/mini_cs.zip ]; then \
      unzip /app/mini_cs.zip -d /home || true; \
    fi

# Create config directory and persistence.db ahead of time to avoid rm errors
RUN mkdir -p /home/mini_cs/config && touch /home/mini_cs/config/persistence.db || true

# Ensure scripts are executable
RUN if [ -d /home/mini_cs/scripts ]; then \
      chmod -R 755 /home/mini_cs/scripts || true; \
    fi

# Configure Apache to suppress ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable Apache modules needed for PHP
RUN a2enmod rewrite && a2enmod php7.2 2>/dev/null || true

# Copy entrypoint which runs setup/start on container start
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose the web/dashboard port mentioned in README
EXPOSE 18001

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
