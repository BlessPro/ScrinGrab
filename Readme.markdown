# ScrinGrab

Capture, compare, and back up visual snapshots of your WordPress pages — effortlessly.

Author: Bless Doe (aka BlessPro)
Version: 0.1.2
Requires WordPress: 6.0+
Tested up to: 6.7
Requires PHP: 8.1+

---

## Description

ScrinGrab is a lightweight WordPress plugin for taking automated screenshots (visual backups) of selected pages and managing them from a clean dashboard inside WordPress.

Preview, capture, and schedule visual backups of your site’s key pages for multiple device sizes — desktop, tablet, and mobile.

---

## Key Features

- Account-based access (Google OAuth integration coming soon)
- Visual Backup — capture selected pages in your WordPress dashboard
- Multi-device preview — Desktop, Tablet, and Mobile
- Organized dashboard — Backup/Capture and Settings tabs
- Retention & Frequency controls — how many versions to keep and how often to run
- Future-ready storage — Local for now; cloud options on the roadmap
- Modular architecture — easy to extend or contribute

---

## Installation

1. Upload the `scripgrab` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin via Plugins → Installed Plugins.
3. Go to ScrinGrab → Dashboard.
4. Sign in (mock login for now; Google login coming soon).
5. Select your pages, view previews, and start capturing.

---

## FAQ

Q: Does ScrinGrab back up files or the database?
A: No — it captures visual snapshots (screenshots) of your pages.

Q: Will it slow down my site?
A: No. Captures are designed to run in the background.

Q: Is Google sign-in required?
A: Not yet. A mock login is provided so you can explore the interface.

---

## Roadmap

- Overlay login + dashboard tabs
- Real Google OAuth (PHP-based)
- Remote web app sync for multiple sites
- Scheduled visual backups
- Visual diffing & change detection
- Cloud storage integration (Google Drive, Dropbox, S3)

---

## Developer Notes

ScrinGrab follows a modular, class-based structure.

