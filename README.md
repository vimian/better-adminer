# Better Adminer

Better Adminer is a small Docker setup for people who like Adminer, but are tired of retyping database connection details every time they open it.

It adds one focused improvement: you can save database connections and reopen them later from the Adminer UI. Saved connections are stored on disk and encrypted with a 4-character PIN that you choose when saving.

## What problem this solves

Plain Adminer is great for quick database access, but it is not great when you:

- jump between the same local databases every day
- keep re-entering host, username, password, and database name
- want a lightweight tool instead of a larger database client

This project packages Adminer with a custom plugin that makes repeat access much faster.

## Is this for you?

This is a good fit if you want:

- a simple browser-based database tool
- saved connections for local or development databases
- a small Docker-based setup you can run in minutes

This is probably not for you if you want:

- team-wide secret management
- enterprise access controls
- a full desktop database client with advanced query tooling

## What it includes

- Adminer running in Docker
- a custom "saved connections" plugin
- persistent storage in `data/`
- encrypted saved connection data protected by your PIN

## How it works

1. Start the container.
2. Open Adminer in your browser.
3. Enter your database connection details as usual.
4. Save the connection from the UI.
5. Next time, unlock it with the same 4-character PIN and reconnect quickly.

Saved connections are stored in `data/saved-connections.json`, while the PIN itself is not stored.

## Quick start

Requirements:

- Docker Desktop or Docker Engine with Compose support

Run:

```bash
docker compose up --build
```

Or double-click the launcher for your operating system:

- Windows: `start-adminer.bat`
- macOS: `start-adminer.command`

Then open [http://localhost:8080](http://localhost:8080).

## Important networking note

This setup uses host networking so `localhost` in the Adminer login form points to your machine.

On Windows, Docker Desktop host networking must be enabled first:

`Settings -> Resources -> Network -> Enable host networking`

That behavior is configured in `compose.yaml`.

## Project structure

- `Dockerfile`: builds the custom Adminer image
- `compose.yaml`: runs the container with persistent storage
- `plugins-enabled/saved-connections.php`: adds saved connection support

## Security note

This project is aimed at local and development workflows. Connection details are encrypted before being written to disk, but this is still a convenience tool, not a replacement for a proper secrets platform.
