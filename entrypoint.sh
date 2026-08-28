#!/usr/bin/env bash
set -e

# Entrypoint for the mini_cs container
# - prepares permissions
# - runs setup/start scripts from /home/mini_cs/scripts if present
# - starts nscd (if installed) and apache2 in the foreground

echo "Container started at $(date)"

echo "Ensuring /home exists and permissions"
mkdir -p /home
chown -R root:root /home || true

# Ensure the mini_cs config directory and persistence DB exist (guard against host mounts)
mkdir -p /home/mini_cs/config
if [ ! -f /home/mini_cs/config/persistence.db ]; then
  echo "Initializing /home/mini_cs/config/persistence.db"
  touch /home/mini_cs/config/persistence.db || true
fi
chown -R root:root /home/mini_cs/config || true
chmod 644 /home/mini_cs/config/persistence.db || true

# If the distro php-fpm is available, create a small wrapper so scripts that call the bundled
# /home/mini_cs/php/sbin/php-fpm will instead invoke the system php-fpm (avoids CURL_OPENSSL_3 ABI mismatch)
if [ -x /usr/sbin/php-fpm7.2 ]; then
  mkdir -p /home/mini_cs/php/sbin
  cat > /home/mini_cs/php/sbin/php-fpm <<'PHPWRAPPER'
#!/bin/sh
exec /usr/sbin/php-fpm7.2 "$@"
PHPWRAPPER
  chmod +x /home/mini_cs/php/sbin/php-fpm || true
fi

# Make all scripts executable if present
if [ -d /home/mini_cs/scripts ]; then
  echo "Making /home/mini_cs/scripts executable"
  chmod -R 755 /home/mini_cs/scripts || true
fi

# Start nscd if installed (some scripts rely on it)
if command -v nscd >/dev/null 2>&1; then
  echo "Starting nscd"
  service nscd start || true
fi

# Create a wrapper for sudo since we're running as root in container
# This allows scripts that call 'sudo' to work without errors
if ! command -v sudo >/dev/null 2>&1; then
  echo "Creating sudo wrapper (container is running as root)"
  mkdir -p /usr/local/bin
  cat > /usr/local/bin/sudo << 'SUDO_WRAPPER'
#!/bin/bash
# In a container running as root, sudo is unnecessary
# Strip sudo flags (-u, -i, etc.) and execute the actual command

# Remove common sudo flags: -u user, -i, -s, etc.
while [[ $# -gt 0 ]]; do
  case "$1" in
    -u)
      shift 2  # Skip -u and the username
      ;;
    -i|-s|--login|--shell)
      shift    # Skip flag only
      ;;
    -*)
      shift    # Skip other flags
      ;;
    *)
      # Found the actual command; execute it with all remaining args
      exec "$@"
      ;;
  esac
done
SUDO_WRAPPER
  chmod +x /usr/local/bin/sudo
fi

# Fix php-fpm pool configs missing user/group to avoid FPM initialization failure
echo "Searching for and fixing php-fpm pool configuration files..."

# Find all pool config files in common locations
POOL_FILES=()
if [ -d /etc/php/7.2/fpm/pool.d ]; then
  for f in /etc/php/7.2/fpm/pool.d/*.conf /etc/php/7.2/fpm/pool.d/*.ini; do
    [ -f "$f" ] && POOL_FILES+=("$f")
  done
fi
if [ -d /home/mini_cs/php/etc/php-fpm.d ]; then
  for f in /home/mini_cs/php/etc/php-fpm.d/*.conf /home/mini_cs/php/etc/php-fpm.d/*.ini; do
    [ -f "$f" ] && POOL_FILES+=("$f")
  done
fi

# Also check for php-fpm.conf which might contain pool definitions
if [ -f /etc/php/7.2/fpm/php-fpm.conf ]; then
  POOL_FILES+=(/etc/php/7.2/fpm/php-fpm.conf)
fi
if [ -f /home/mini_cs/php/etc/php-fpm.conf ]; then
  POOL_FILES+=(/home/mini_cs/php/etc/php-fpm.conf)
fi

# Process all found pool files
for POOL in "${POOL_FILES[@]}"; do
  if [ -f "$POOL" ]; then
    echo "Processing pool config: $POOL"
    
    # Check if this file contains a pool section (look for [poolname])
    if grep -q '^\[' "$POOL" 2>/dev/null; then
      
      # If file has [mini_cs] section, target that specifically
      if grep -q '^\[mini_cs\]' "$POOL" 2>/dev/null; then
        echo "  Found [mini_cs] section in $POOL"
        
        # Check if user is defined within mini_cs section (between [mini_cs] and next [)
        if ! sed -n '/^\[mini_cs\]/,/^\[/p' "$POOL" 2>/dev/null | grep -q '^user\s*=' 2>/dev/null; then
          # Add user = www-data right after [mini_cs]
          sed -i '/^\[mini_cs\]/a user = www-data' "$POOL" || true
          echo "  Added user = www-data"
        else
          # Update existing user line - only within mini_cs section
          sed -i '/^\[mini_cs\]/,/^\[/{/^user\s*=/s/=.*/= www-data/;}' "$POOL" || true
          echo "  Updated user = www-data"
        fi
        
        # Check if group is defined within mini_cs section
        if ! sed -n '/^\[mini_cs\]/,/^\[/p' "$POOL" 2>/dev/null | grep -q '^group\s*=' 2>/dev/null; then
          # Add group = www-data right after [mini_cs]
          sed -i '/^\[mini_cs\]/a group = www-data' "$POOL" || true
          echo "  Added group = www-data"
        else
          # Update existing group line - only within mini_cs section
          sed -i '/^\[mini_cs\]/,/^\[/{/^group\s*=/s/=.*/= www-data/;}' "$POOL" || true
          echo "  Updated group = www-data"
        fi
      else
        # For configs without explicit [mini_cs] section, check/add at top level
        echo "  No [mini_cs] section found, checking global directives"
        
        if ! grep -q '^user\s*=' "$POOL" 2>/dev/null; then
          echo "user = www-data" >> "$POOL" || true
          echo "  Added user = www-data"
        fi
        if ! grep -q '^group\s*=' "$POOL" 2>/dev/null; then
          echo "group = www-data" >> "$POOL" || true
          echo "  Added group = www-data"
        fi
      fi
    fi
  fi
done

echo "PHP-FPM pool configuration update complete"

# Run the included setup script if present
if [ -x /home/mini_cs/scripts/setup.sh ]; then
  echo "Running setup.sh"
  /home/mini_cs/scripts/setup.sh || echo "setup.sh exited with non-zero status"
elif [ -f /home/mini_cs/scripts/setup.sh ]; then
  echo "Making setup.sh executable and running"
  chmod +x /home/mini_cs/scripts/setup.sh
  /home/mini_cs/scripts/setup.sh || echo "setup.sh exited with non-zero status"
fi

# Run the start script (start_clean.sh) if present
if [ -x /home/mini_cs/scripts/start_clean.sh ]; then
  echo "Running start_clean.sh"
  /home/mini_cs/scripts/start_clean.sh || echo "start_clean.sh exited with non-zero status"
elif [ -f /home/mini_cs/scripts/start_clean.sh ]; then
  echo "Making start_clean.sh executable and running"
  chmod +x /home/mini_cs/scripts/start_clean.sh
  /home/mini_cs/scripts/start_clean.sh || echo "start_clean.sh exited with non-zero status"
fi

# Ensure /home permissions are correct for web server
echo "Finalizing permissions for web root"
chmod -R 755 /home || true

# Fallback: ensure Apache runs in foreground so container stays alive
echo "Starting Apache in foreground"
exec apache2ctl -D FOREGROUND
