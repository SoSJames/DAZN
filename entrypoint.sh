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

# Fallback: ensure Apache runs in foreground so container stays alive
echo "Starting Apache in foreground"
exec apache2ctl -D FOREGROUND
