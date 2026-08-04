FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /app

# Copy the packaged mini_cs zip into the image
COPY mini_cs.zip /app/mini_cs.zip

# Install runtime and build dependencies referenced in the README
RUN apt-get update && apt-get install -y \
    unzip apache2 libapache2-mod-php php php-mbstring php-xml php-curl php-zip \
    libxslt1-dev nscd libonig-dev libzip-dev aria2 libcurl4-openssl-dev libcurl3 \
    wget curl ca-certificates procps supervisor \
  && rm -rf /var/lib/apt/lists/*

# Unpack the archive to /home (matches README instructions)
RUN if [ -f /app/mini_cs.zip ]; then \
      unzip /app/mini_cs.zip -d /home || true; \
    fi

# Ensure scripts are executable
RUN if [ -d /home/mini_cs/scripts ]; then \
      chmod -R 755 /home/mini_cs/scripts || true; \
    fi

# Copy entrypoint which runs setup/start on container start
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose the web/dashboard port mentioned in README
EXPOSE 18001

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
