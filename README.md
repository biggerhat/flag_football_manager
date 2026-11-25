# Flag Football Coach

A web app for flag football coaches: design plays, plan practices, call games from the sideline and keep families in the loop. It works on a phone, installs as an app, and keeps logging calls when the signal drops.

Built with Laravel 13, Inertia 3, Vue 3, Tailwind 4 and SQLite.

## Features

**Plan**

- **Playbooks and plays.** Draw routes on a field, tag and favourite plays, bulk-edit, and see earlier versions of a play with one-click restore.
- **Formations and drills.** A starter library comes with every new team.
- **Practices.** Build a schedule from drills and plays, then run it with a timer on the field.

**Game day**

- **Game plans.** Pick and order plays for an opponent and print a call sheet.
- **Sideline mode.** Large buttons, screen kept awake, one-tap result logging with undo, and a "touches this game" tally so nobody is forgotten.
- **Offline.** Calls made without a connection are saved on the device and sent when the signal returns, without ever being recorded twice.
- **Stats.** Record, most-called plays, results by down, touches per player, and per-game breakdowns, filterable by season.

**Team**

- **Roster and attendance.** Positions, numbers, practice attendance.
- **Opponents.** Head-to-head record and scouting notes per opponent.
- **Seasons.** Date ranges that scope the record, stats and attendance.
- **Calendar.** An in-app calendar, a subscribable calendar feed (ICS) and day-before email reminders.
- **Print and PDF.** Wristbands, call sheets, practice plans and playbook packets.
- **Backup.** Download everything a team holds as JSON and restore it later.

**Sharing**

- **Read-only links.** A shareable playbook (expires after 90 days), a family page and a calendar feed, each behind a secret link that can be regenerated.

**Teams, clubs and roles**

- A person can belong to many teams, with a different role on each. See [Teams, clubs and roles](#teams-clubs-and-roles).

## Quick start

Requirements: PHP 8.3+, Composer, Node 22+. No database server is needed.

```bash
git clone <your-repo-url> flag-football-coach
cd flag-football-coach

composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
php artisan serve
```

Open <http://localhost:8000> and register. **The first account on a fresh install is always allowed**; after that, registration is by invitation (see below).

For development with hot reload, run `composer dev`.

For a private, local-only install you can skip the login screen entirely with `COACH_AUTO_LOGIN=true` in `.env`. It is ignored when `APP_ENV=production`.

## Configuration

Everything is set in `.env`.

| Setting              | Default     | What it does                                                                                                                   |
| -------------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `COACH_REGISTRATION` | `invite`    | `invite`: new accounts need a team invitation sent to their email. `open`: anyone can register.                                |
| `COACH_VERIFY_EMAIL` | `false`     | Require new users to confirm their email address (needs mail configured).                                                      |
| `COACH_AUTO_LOGIN`   | `false`     | Sign everyone in as one local coach. Local use only; ignored in production.                                                    |
| `COACH_CHROME_PATH`  | auto-detect | Chrome or Chromium used to produce PDF downloads.                                                                              |
| `MAIL_*`             | `log`       | Outgoing mail for reminders and verification. With `MAIL_MAILER=log` mail is only written to the log. Any SMTP provider works. |

## Teams, clubs and roles

**Teams.** Registering starts a team of your own and makes you its owner. You can create more teams (Team → Your teams), switch between them from the sidebar, and see all of them side by side under **My teams**. Everything (plays, roster, games, stats, calendar) belongs to the active team.

**Roles** are per team:

| Role   | Can                                                                               |
| ------ | --------------------------------------------------------------------------------- |
| Owner  | Everything, including members, invitations, seasons, backup and deleting the team |
| Coach  | Create and edit plays, practices, game plans, roster and calls                    |
| Viewer | View and print only                                                               |

**Invitations.** Owners invite people by email and share the link. An invitation can cover several of your teams at once, and is accepted in one step. The invited person must sign in or register with that exact address. Because registration is invitation-only by default, an invitation is also how a new person gets an account.

**Copy between teams.** Plays, drills, formations and practices have a "copy to another team" button. A copied practice brings the drills and plays it uses.

**Clubs.** A club groups teams that one organisation runs, and gives people a role across all of them at once:

| Club role     | Role on every club team (including teams added later) |
| ------------- | ----------------------------------------------------- |
| Owner         | Owner (the person who started the club)               |
| Administrator | Owner                                                 |
| Coach         | Coach                                                 |
| Viewer        | Viewer                                                |

The club owner invites people with a link and picks their role; it can be changed or removed on the Club page. Only the owner and administrators can add teams to, or take them out of, the club. Access that comes from the club is marked as such on each team's member list and is changed on the Club page, so taking a team out of a club, or removing someone, revokes exactly what the club gave. A person can belong to one club.

## Running in production

- Set `APP_ENV=production`, `APP_DEBUG=false` and an `https` `APP_URL`. Set `SESSION_SECURE_COOKIE=true`.
- Run `npm run build` on every deploy; the app does not render without a current `public/build`.
- Add this cron entry for scheduled jobs (daily trash purge, hourly email reminders):

    ```
    * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
    ```

- Nothing is queued today (mail is sent directly), so no queue worker is needed.
- PDF downloads need Chrome or Chromium on the server.
- Back up `database/database.sqlite`, or use Team → Backup. Items deleted in the app stay in the Trash for 30 days, then are purged.

## Development

```bash
vendor/bin/pest                       # PHP tests
npm test                              # front-end unit tests
vendor/bin/phpstan analyse            # static analysis
vendor/bin/pint --test                # PHP formatting
npm run check                         # lint + format
npx vue-tsc --noEmit                  # type-check
```

CI (`.github/workflows/ci.yml`) runs all of these plus a build and `composer audit`.

After changing routes, regenerate the typed route helpers:

```bash
php artisan wayfinder:generate --with-form
```

Project layout:

- `app/Http/Controllers` — one controller per area; every team-owned record is checked with `ensureOwned()`
- `app/Models` — team-scoped models use the `BelongsToTeam` trait
- `resources/js/pages` — Inertia pages; `resources/js/components` — shared components (`ui/` is shadcn-vue)
- `resources/js/lib` — small pure helpers with unit tests (offline call queue, sync, touch balance)
- `public/sw.js` — service worker for offline pages

## Security notes

- Public links (playbook share, family page, calendar feed, invitations) are long random tokens, rate limited, and sent with `no-referrer` and `noindex`.
- All data access is scoped to the signed-in user's teams; a test tries every id-based route as an outsider.
- Responses carry `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and `Permissions-Policy` headers.
- Please report security problems privately rather than in a public issue.

## License

[MIT](LICENSE)
