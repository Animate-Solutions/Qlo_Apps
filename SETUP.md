# QloApps Local Dev Setup

Shared local development environment for our QloApps fork, using Docker so both of us run an identical setup.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- Git installed

## First-time setup

```bash
git clone https://github.com/yourname/QloApps.git
cd QloApps
docker-compose up -d
```

Wait about 30 seconds for MySQL and Apache to fully start inside the container.

Check it's running:

```bash
docker ps
```

You should see a container named `qloapps_site` with status `Up`.

## Accessing the app

- Storefront / installer: http://localhost
- Admin back office: http://localhost/YOUR_ADMIN_FOLDER_NAME/
  (see "Admin folder" note below — this is renamed for security after install)

## If this is a completely fresh database (first person to set up)

Open http://localhost and go through the QloApps web installer. When asked for database details, use:

| Field | Value |
|---|---|
| Database server address | `127.0.0.1` |
| Database name | `qloapps_db` |
| Database login | `root` |
| Database password | `qloapp123` |
| Tables prefix | leave default |

> Note: use `127.0.0.1`, NOT `localhost` — PHP's PDO driver treats `localhost` as a Unix socket connection which does not resolve correctly in this container, and will fail with "Database Server is not found."

After the installer finishes, for security:
1. Delete the install folder:
   ```bash
   docker exec -i qloapps_site rm -rf /home/qloapps/www/QloApps/install
   ```
2. Rename the admin folder to something private:
   ```bash
   docker exec -it qloapps_site bash
   mv /home/qloapps/www/QloApps/admin /home/qloapps/www/QloApps/admin_CHANGE_ME
   exit
   ```
3. Update this file / tell your partner the new admin folder name (don't commit it publicly if the repo is public — consider a shared password manager or private note instead).

## Day-to-day usage

| Goal | Command |
|---|---|
| Start the app | `docker-compose up -d` |
| Stop the app | `docker-compose down` (keeps data) |
| View logs | `docker logs qloapps_site` |
| Shell into container | `docker exec -it qloapps_site bash` |

## Making code changes / adding modules

- The `./QloApps` folder in this repo is **live-mounted** into the container — edit files locally in your editor, refresh the browser, changes appear immediately.
- New modules go in `./QloApps/modules/your-module-name/`.
- After adding a new module, install it via **Admin back office → Modules** (this is a one-time action per local environment, not something Git syncs automatically).
- If changes don't show up, try clearing QloApps' internal cache (delete contents of `./QloApps/var/cache/` if that path exists in this version) and refresh.

## Git workflow

1. Branch off `main` for your work: `git checkout -b feature/your-feature-name`
2. Commit and push your changes
3. Open a Pull Request into `main`
4. After merging, pull the latest: `git pull origin main`
5. Re-check Modules page in admin if new modules were added, and install as needed

## Known gotchas

- If port 80, 3306, or 2222 is already used by something else on your machine, edit the left-hand side of the `ports:` mapping in `docker-compose.yml` (e.g. `"8080:80"`) and access via that port instead.
- `docker-compose up -d` on an existing container reuses it — you don't need to reinstall QloApps every time you start.
