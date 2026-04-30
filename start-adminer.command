#!/bin/sh

cd "$(dirname "$0")" || exit 1

echo "Starting Better Adminer with Docker Compose..."
echo

if docker compose -f compose.yaml up -d --build; then
  echo
  echo "Better Adminer is running at http://localhost:8080"
  echo
else
  status=$?
  echo
  echo "Docker Compose failed. Make sure Docker Desktop is running and Compose is installed."
  echo
  printf "Press Enter to close this window..."
  read answer
  exit "$status"
fi

printf "Press Enter to close this window..."
read answer
