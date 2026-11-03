# OpenEscool guide

How every part of OpenEscool works and how to configure it. New here? Start with the [README](../README.md) and the [Hostinger install guide](INSTALL-HOSTINGER.md).

## Who can create communities

Only platform admins can create communities. The first person to sign up becomes an admin; promote others after they register:

```bash
php artisan app:make-admin you@example.com
```

## Configuration

| Feature | `.env` keys | Where to get them |
| --- | --- | --- |
| Email (Brevo) | Set in the app: **Admin settings → Email (Brevo)** | Brevo → SMTP & API → API keys; verify the sender under Senders |
| Google sign-in | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google Cloud Console → Credentials → OAuth client (redirect: `APP_URL/auth/google/callback`) |
| Stripe | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Stripe → Developers → API keys; Webhooks → endpoint signing secret |
| Razorpay (INR, UPI) | `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | Razorpay → Account & Settings → API keys; Webhooks (you choose the secret) |

Leave any of these empty to turn that feature off.

**Email setup:** a platform admin pastes the Brevo API key, sender email and name (and optionally a members list ID) in **Admin settings**. The key is checked with Brevo, stored encrypted with `APP_KEY`, and from then on every email goes through the Brevo API (no SMTP login needed). Use **Send test email** to confirm. Until a key is saved, emails go to the log. `BREVO_API_KEY` / SMTP keys in `.env` still work as a fallback.

On Windows, PHP often has no CA bundle, so HTTPS calls fail with cURL error 60. The app then uses the Windows certificate store automatically; Linux servers are not affected.


## Payments

Owners open **Revenue** in their community to add plans (monthly, yearly or one-time, in USD, EUR, GBP, INR, AUD, CAD, SGD or AED), switch the community to **Paid**, and track MRR and payments. Members already in the community keep access when you switch. Non-members of a paid community only see its About page.

- **Stripe** handles every currency through its hosted Checkout page. Members manage cards and invoices in Stripe's Billing Portal (Settings → Billing → Payment methods). In the Stripe dashboard, turn on the Customer portal (Settings → Billing → Customer portal).
- **Razorpay** handles INR plans when its keys are set, so Indian members can pay with UPI, cards, netbanking or wallets. Without Razorpay keys, INR plans go through Stripe.
- **Access is granted on payment** (from the success redirect and from webhooks, whichever arrives first) and removed when a subscription ends. Owners, admins and moderators never lose access. A failed renewal leaves a 3-day grace period, and an hourly job ends any subscription whose paid period is over, in case a webhook was missed.
- Members get an email receipt for each payment. They can cancel from Settings → Billing and keep access until the end of the paid period.

Webhook setup (use test keys first):

| Gateway | Endpoint | Events |
| --- | --- | --- |
| Stripe | `APP_URL/webhooks/stripe` | `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `invoice.paid`, `customer.subscription.updated`, `customer.subscription.deleted` |
| Razorpay | `APP_URL/webhooks/razorpay` | `subscription.activated`, `subscription.charged`, `subscription.pending`, `subscription.halted`, `subscription.cancelled`, `subscription.completed`, `payment.captured` |

To test webhooks on your own computer, use `stripe listen --forward-to localhost:8000/webhooks/stripe`.


## Emails and digests

- **Digest:** members get a weekly summary by default (Monday 08:00): the most popular new posts in each of their communities and events coming up. They can switch to daily (08:00 every day) or off in Settings → Email. Times use the app timezone (`APP_TIMEZONE`). Nobody gets an empty digest, and a post is never sent twice.
- **Activity emails** (comments, mentions, join requests, event reminders, level-ups) can each be turned off in Settings → Email. In-app notifications always arrive.
- **Email a post to members:** when writing a post, owners and admins can tick **Email to all members** (like Skool). Every active member gets the full post by email; members can opt out under Settings → Email → "Posts that admins email to all members". A community can send one such email every 24 hours.
- Every email has a signed unsubscribe link that works without logging in. Digests also send the `List-Unsubscribe` one-click header that Gmail and Yahoo require from bulk senders.
- **About page:** a gallery of images and YouTube/Vimeo/Loom videos (Settings → About page gallery), star ratings and reviews from current members, each labelled with how long the reviewer has been a member and whether they still pay. Owners can hide abusive reviews; ratings also show on the Discover page.
- Members get a notification when likes push them to a new level, along with any courses it unlocks. Owners can name each level (Settings → Level names); the names show on the leaderboard and on locked courses.

Send a digest by hand with `php artisan app:send-digests weekly`.

## Classroom files

- Lesson **resources** can be links or uploaded files (PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP, images, MP3). Lessons can have an uploaded **video** (MP4 or WebM) instead of a YouTube/Vimeo/Loom link.
- Uploaded files are stored privately (`storage/app/private`) and only served to people who can open the lesson. Removing a file from a lesson, or deleting the lesson, deletes it.

## Team

In **Settings → Team**, search a member and click **Make moderator** (or **Make admin**, owner only). Change or remove a role there, or with the role dropdown on the Members page.

## Site settings (Admin settings)

- **First admin:** on a fresh install, the first person to sign up becomes the platform admin. Later sign-ups are normal members (promote more with `php artisan app:make-admin you@example.com`).
- **Site name and logo:** shown in the top bar, sign-in pages, browser tabs and emails.
- **Home page:** open one community (visitors see its About page, members its feed) or show the Discover page, with your own heading, text, button and a choice of communities. `/discover` always lists communities.
- **Search engines (SEO):** one switch for the whole site, then per community (Settings → Search engines) whether the community and its posts may be indexed. Private communities and personal pages are never indexed. `/robots.txt` and `/sitemap.xml` follow these settings.
- **Need help?** links to https://openescool.userjot.com/.

## Updating

Back up your database and files first. Then, in the site folder on your server:

```bash
php artisan app:update
```

It runs `git pull`, `composer install`, `php artisan migrate`, `npm ci && npm run build`, clears caches and restarts the queue, with a short maintenance page meanwhile. Admin settings → Updates shows when a new GitHub release is out, and has a one-click button that runs the same command (it asks you to confirm you have a backup; the command line is the safer way).

Releasing a new version: bump `version` in `config/openescool.php`, push, and publish a GitHub release tagged `v<version>` with notes.

## Search and chat

- **Search:** the search box in a community's top bar finds posts (title and text) and members (name and username).
- **Chat:** chat is only between members and the team. Members can message platform admins and the owner, admins and moderators of their communities; the team can message their members. Members can't message each other. Open a chat from the chat icon in the top bar, or with **Message** on a profile or in the members list. Anyone can block a person from the chat menu. Someone who is away gets one email per unread chat; they can turn it off in Settings → Email.

## Photos and GIFs

Each community owner turns photos, GIFs and comment media on in **Settings → Photos and GIFs**. Everything is off by default.

- **Photos in posts:** JPG, PNG or WebP. Every upload is checked to be a real image.
- **Upload limits (whole platform):** a platform admin sets them in **Admin settings → Upload limits**: photos and GIFs per post (1–4), max photo size (1 MB by default, never more than 1 MB), max lesson resource file and max lesson video (1 MB by default, up to 100 MB each). Any value can be lower, e.g. 0.5. A bigger file gets an error.
- **The team can always add media:** owners, admins and moderators can add photos and GIFs to posts and comments even when the community switches are off. The switches are for members.
- **GIFs** only show up as an option once a GIPHY key is saved.
- **GIFs:** picked from GIPHY with search and trending, like WhatsApp. Results are rated PG-13 or lower, and there is one GIF per post. GIFs load from GIPHY, so they use no storage.
- **Comments:** a separate switch allows one photo or one GIF per comment.
- **GIPHY key:** a platform admin adds it in the app under **Admin settings** (user menu). It is checked with GIPHY, stored encrypted with `APP_KEY`, and never sent to the browser; searches go through the server. Get a free key at [developers.giphy.com](https://developers.giphy.com/dashboard/).
- If your server's PHP `upload_max_filesize` / `post_max_size` is lower than the size you allow, raise it in `php.ini`.

## Spam protection

Anything that looks like spam is hidden straight away. Its author is put **under review**: they can still read, but they can't post, comment or message until a platform admin decides. Admins get a notification, and the shield icon in the top bar shows how many accounts are waiting.

- **Sign-up:** a hidden honeypot field and a signed form token turn away bots that fill the form in under 3 seconds or post without loading it. Sign-ups are also limited to 6 per hour per IP.
- **Content filter** (posts, comments, messages): blocked phrases, links from accounts younger than 72 hours, and more than 5 links in one item. You can add your own phrases with `SPAM_KEYWORDS="phrase one,phrase two"` in `.env`. Tune the other limits in `config/spam.php`.
- **Chat limits:** sending the same message to 3 or more people within 30 minutes counts as a spam blast, and the copies already sent are pulled back too. New accounts can start 5 new chats a day; other members can start 30. Everyone can send up to 15 messages a minute and 300 a day.
- **Reports:** members can report a post, comment or message as spam. Once 3 different people report someone within 7 days, that account goes under review.
- **Admin decisions** live at `/admin/moderation`, or under **Admin actions** on any profile:
  - **Not spam** lifts the review and brings their content back.
  - **Ban** signs them out for good and hides everything they wrote.
  - **Delete account** removes the account and all its content.
  - Admins can also put any account under review by hand.

  Private messages are only ever shown to admins if they were flagged or reported.

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
