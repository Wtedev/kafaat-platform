# Railway staging is paused

Staging is not a deploy target. Develop locally, verify in the browser, and ship through a pull request to `main`. Production deploys from that merge. Admins see the new staff UI in preview mode; other staff do not.

Do not run `railway/deploy-staging.sh`. It exits immediately so it cannot link the CLI to staging or redeploy those services.

The staging database was not deleted. Its volume stays with the Postgres service. Do not remove that volume or the Postgres service until that is explicitly approved.

| Item | Value |
|------|-------|
| Project | `harmonious-comfort` (`728e29dc-fce4-45dd-958b-c8fd64d731b6`) |
| Environment | `staging` (`aab5de07-b736-4ec8-aba0-a2b15dbeb603`) |
| Former URL | `https://kafaat-web-staging-staging.up.railway.app` |
| Production URL | `https://kafaat.org.sa` |

Paused on 2026-10-05. All four containers are exited. Restart policy is `NEVER`, and auto-deploy is off on the three app services, so a push does not wake them. The app start command is `echo staging-paused; exit 0` until staging is explicitly resumed. Resuming means restoring that start command, setting the restart policy back to `ON_FAILURE`, turning auto-deploy on, and deploying again. Do not do that as part of a production change.

Paused services (compute stays off; data stays):

| Service | Role |
|---------|------|
| `kafaat-web-staging` | HTTP |
| `kafaat-worker-staging` | Queue |
| `kafaat-scheduler-staging` | Scheduler |
| `Postgres-bCZA` | Database. Volume `postgres-volume-_Y1m` mounted at `/var/lib/postgresql/data` |

`railway environment staging` links the CLI. Do not run it. Pass `--project` and `--environment` only when inspecting this environment, and do not redeploy it.
