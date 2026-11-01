# OpenGuild

An open-source community platform: feed, members, levels and leaderboards, with
courses, events and paid memberships on the way. Built with Laravel 12, Inertia,
React and Tailwind. Email goes through Brevo, and payments will use Stripe and Razorpay.

## Status

| Phase | Scope | Status |
| --- | --- | --- |
| 1. Foundation | Auth (email + Google), communities, roles, feed, comments, likes, points/levels, members, profiles, notifications, Brevo | ✅ Done |
| 2. Classroom, calendar & leaderboards | Courses, modules, lessons, progress, level-locked courses; events with weekly repeats, reminders, add-to-calendar; 7/30-day/all-time leaderboards | ✅ Done |
| 3. Payments | Stripe + Razorpay subscriptions/one-time, paywalls, webhooks | Planned |
| 4. Gamification & digests | Full leaderboards, level unlocks, daily/weekly email digest | Planned |
| 5. Deployment | Hostinger guide, install script, CI deploy | Planned |

## Local setup

Requirements: PHP 8.2+, Composer, Node 20+. Locally the app uses SQLite; in production it uses MySQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # demo data; log in as admin@example.com / password
php artisan storage:link
npm run build
php artisan serve
```

For live-reloading while you develop, run `npm run dev` next to `php artisan serve`.
Emails are written to `storage/logs/laravel.log` while `MAIL_MAILER=log`.

Run the tests with `php artisan test`.

## Who can create communities

Only platform admins can create communities. Promote a user after they register:

```bash
php artisan app:make-admin you@example.com
```

## Configuration

| Feature | `.env` keys | Where to get them |
| --- | --- | --- |
| Email (Brevo SMTP) | `MAIL_MAILER=smtp`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Brevo → SMTP & API → SMTP |
| Brevo contact sync | `BREVO_API_KEY`, `BREVO_MEMBERS_LIST_ID` | Brevo → SMTP & API → API keys; Contacts → Lists |
| Google sign-in | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google Cloud Console → Credentials → OAuth client (redirect: `APP_URL/auth/google/callback`) |

Leave any of these empty to turn that feature off.

## Background jobs

Emails and Brevo sync run on the database queue. Production needs one cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler empties the queue every minute, so you don't need a separate worker process.

## Security notes

- Every action is checked by a policy. Members can only edit their own content; moderators, admins and the owner have more control.
- Posts and comments are rate-limited per user.
- User content is shown as plain text: links and @mentions become clickable, and no HTML is ever rendered.
- Notification links only redirect within the app.
