# EasyTalk — Real-time Chat Application (Diploma Thesis)

<table>
<tr><td width="200">
<img src="screenshots/logo.png" alt="EasyTalk Logo" width="200" height="200">
</td><td>

## EasyTalk

#### A web-based chat application with a PHP and MySQL backend, real-time messaging over WebSockets, and a JavaScript and Bootstrap frontend. EasyTalk supports encrypted one-to-one and group chats, file sharing, voice and video recording, photo capture, admin moderation, configurable user settings, and an in-app contact form.

</td></tr>
</table>

## Description

This web application implements a real-time communication platform for asynchronous messaging between users, developed as part of a Diploma Thesis at the **Department of Electrical & Computer Engineering**, University of Western Macedonia, Kozani, Greece.

EasyTalk combines real-time messaging with multimedia support: text, images, audio, video, and document sharing. The architecture is modular. The PHP backend exposes a structured JSON API, a Ratchet WebSocket server handles instant message delivery, and a MySQL database stores persistent data.

On the security side, the platform uses AES-256-GCM message encryption, JWT authentication, CSRF protection, rate limiting on sensitive actions, and a GDPR-compliant privacy policy. The codebase follows defense-in-depth practices and protects against a range of common web vulnerabilities, including SQL injection, cross-site scripting (XSS), cross-site request forgery (CSRF), and broken access control.

## Features

### Messaging

- **Real-time chat**: Powered by WebSockets (Ratchet).
- **Conversations**: Private one-to-one direct messaging and multi-user group chats.
- **Message encryption**: AES-256-GCM applied both in transit and at rest.
- **Read receipts and delivery status**: Tracked per message across all recipients.
- **Message deletion**: Users can delete their own messages.

### File Sharing & Multimedia

- **File uploads**: Images (JPG, PNG, GIF), audio (MP3, OGG), video (MP4, WebM, MOV, AVI), and documents (PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT).
- **Drag-and-drop file picker**: With size validation up to 50 MB.
- **In-chat media preview**: Inline preview for images, audio, and video.
- **MIME type validation**: Server-side type checking before storage.
- **Secure file access**: Downloads pass through an authenticated PHP handler. Direct filesystem access is blocked at the web server layer.

### Communication Features

- **Voice messages**: Recorded in-browser and sent instantly.
- **Video recording**: Short video messages captured directly from the chat.
- **Photo capture**: Take and share photos using the device camera.
- **Email notifications**: Offline users receive email alerts about new direct messages and friend requests, queued in the background and throttled per sender to avoid spam. Each user can choose whether to enable these notifications from their Settings page.

### Group Chats

- **Group creation**: Any user can create a group, set its name and picture, and invite initial members.
- **Role-based permissions**: Each group has admins and members with distinct capabilities.
- **Member management**: Admins can add or remove members from a group.
- **Group customization**: Admins can update the group name and picture.
- **Group deletion**: Admins can delete a group entirely, removing all messages and disbanding the membership.
- **Admin succession**: When an admin leaves a group, they must designate another member to take over.
- **Self-service leave**: Members can leave a group at any time.
- **In-app notifications**: Members receive instant notifications when they are added to, removed from, or when role changes occur in a group.

### Inbox & Notifications

- **Unified messages inbox**: A single page that combines direct conversations and group chats side-by-side, each with unread message counts.
- **Notifications center**: Friend requests (with Accept/Decline) and group activity events (added to a group, removed, role changes, group deletions) collected in one place.

### Account Features

- **Two account types**: Regular users for chat features and platform administrators for moderation tasks.
- **Registration with email verification**: Account activation via email token.
- **Profile management**: Profile picture upload, username change, and password change, all from the user's profile page. Uploaded profile pictures are automatically resized server-side for consistent display.
- **Friend system**: Send, accept, reject, and cancel friend requests.
- **Online presence**: Live online and offline status indicators.
- **User search**: Find users by username, with respect for visibility settings.
- **Account deletion**: Users can close their account from the profile page. Deletion removes any groups they administer and disconnects them from their friends and conversations.
- **Admin panel**: Moderation tools for listing, searching, banning, and unbanning users.
- **In-app contact form**: Users can reach the administrator through a built-in contact page for support and feedback.

### Security & Privacy

- **JWT authentication**: Tokens signed with HS512, with configurable expiry, issuer/audience validation, and HttpOnly cookies.
- **CSRF protection**: Every endpoint that modifies state requires a CSRF token.
- **Rate limiting**: Sliding-window limits stored in the database, applied across API endpoints with stricter caps on sensitive operations such as login, registration, password reset, message sending, and admin actions.
- **Password hashing**: `password_hash()` with PHP's default algorithm (currently bcrypt).
- **Prepared statements**: All database queries use bound parameters.
- **Input sanitization**: Server-side validation and sanitization of every input.
- **XSS protection**: Output escaping via `htmlspecialchars`, a Content Security Policy, and X-Content-Type-Options / X-Frame-Options headers.
- **Defense in depth**: Multiple `.htaccess` layers block direct access to `vendor/`, `includes/`, `tmp/`, `uploads/`, and the internal API handlers. The web root denies sensitive file extensions outright.
- **Session security**: HttpOnly, SameSite=Lax, and conditional Secure cookie flags.
- **Privacy settings**: Users can switch their account to private mode, which removes it from search results. Only people who already know the exact username can find them, which is useful for maintaining a closed circle of contacts.
- **GDPR compliance**: A Privacy Policy page covering data collection, retention, user rights, and contact channels.

## Demo Screenshots

### Authentication & Account Setup

<table>
<tr>
<td align="center" width="50%">
<img src="screenshots/signup.png" alt="Sign Up" width="100%"><br>
<em>Sign Up: Registration with email verification and password confirmation.</em>
</td>
<td align="center" width="50%">
<img src="screenshots/login.png" alt="Login" width="100%"><br>
<em>Login: Authentication with optional &ldquo;Keep me logged in&rdquo;.</em>
</td>
</tr>
</table>

<p align="center">
<img src="screenshots/forgot_password.png" alt="Forgot Password" width="50%"><br>
<em>Forgot Password: Reset flow via emailed token.</em>
</p>

### Home Dashboard & Profile

<p align="center">
<img src="screenshots/home.png" alt="Home" width="100%"><br>
<em>Home: Personalized dashboard with quick navigation.</em>
</p>

<p align="center">
<img src="screenshots/profile.png" alt="Profile" width="100%"><br>
<em>Profile: Customizable user details and profile picture upload.</em>
</p>

### Friends, Search & Notifications

<p align="center">
<img src="screenshots/friends.png" alt="Friends" width="100%"><br>
<em>Friend List: Connections with live presence indicators.</em>
</p>

<p align="center">
<img src="screenshots/search.png" alt="User Search" width="100%"><br>
<em>User Search: Find users with multiple inline actions.</em>
</p>

<p align="center">
<img src="screenshots/notifications.png" alt="Notifications" width="100%"><br>
<em>Notifications: Pending friend requests with Accept/Decline.</em>
</p>

### Messages Overview

<p align="center">
<img src="screenshots/messages.png" alt="Messages" width="100%"><br>
<em>Messages: Unified inbox of unread messages with sender info and previews.</em>
</p>

### One-to-One Chat

<p align="center">
<img src="screenshots/one-to-one_chatbox.png" alt="One-to-One Chat" width="100%"><br>
<em>One-to-One Chat: Real-time direct messaging with multimedia support, read receipts, and message controls.</em>
</p>

### Group Management

<p align="center">
<img src="screenshots/groups.png" alt="Groups" width="100%"><br>
<em>Groups: List of joined group chats.</em>
</p>

<p align="center">
<img src="screenshots/create_group.png" alt="Create Group" width="100%"><br>
<em>Create Group: Set name, image, and initial members.</em>
</p>

<p align="center">
<img src="screenshots/edit_group.png" alt="Edit Group" width="100%"><br>
<em>Edit Group: Admin tools for renaming, image, and member management.</em>
</p>

### Group Chat & Administration

<p align="center">
<img src="screenshots/group_chatbox.png" alt="Group Chat" width="100%"><br>
<em>Group Chat: Multi-user conversation with role-aware controls.</em>
</p>

<p align="center">
<img src="screenshots/admin_leave_group_chat.png" alt="Admin Leave Group" width="100%"><br>
<em>Admin Leave: Guided flow for transferring or dissolving group ownership.</em>
</p>

### Voice Messages

Voice messages are recorded directly inside the chat in three steps: open the modal, record, then preview and send. Video recording and photo capture follow the same modal-based flow.

<table>
<tr>
<td align="center" width="50%">
<img src="screenshots/audio_recording_modal.png" alt="Audio Recording Modal" width="100%"><br>
<em>1. Recording modal opens.</em>
</td>
<td align="center" width="50%">
<img src="screenshots/audio_recording_process.png" alt="Audio Recording In Progress" width="100%"><br>
<em>2. Recording in progress with a live timer.</em>
</td>
</tr>
</table>

<p align="center">
<img src="screenshots/audio_recording_completed.png" alt="Audio Recording Completed" width="50%"><br>
<em>3. Recording completed and ready to send.</em>
</p>

### Photo Capture

<p align="center">
<img src="screenshots/photo_capture_modal_no_rights.png" alt="Photo Capture" width="60%"><br>
<em>Photo capture modal: Prompts the browser for camera access before any photo can be taken.</em>
</p>

### Settings

<p align="center">
<img src="screenshots/settings.png" alt="Settings" width="100%"><br>
<em>Settings: Account visibility and email notification preferences.</em>
</p>

### Admin Panel

<p align="center">
<img src="screenshots/user_management.png" alt="User Management" width="100%"><br>
<em>User Management: Admin panel to list, search, ban, and unban users with rate-limited actions.</em>
</p>

### About & Contact

<p align="center">
<img src="screenshots/about.png" alt="About Us" width="100%"><br>
<em>About: Project overview and academic context.</em>
</p>

<p align="center">
<img src="screenshots/contact.png" alt="Contact Us" width="100%"><br>
<em>Contact: Message form for support and feedback.</em>
</p>

---

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+ (or MariaDB 10.5+)
- **Real-time Communication**: Ratchet WebSocket Server
- **Authentication**: Firebase JWT (HS512)
- **Email**: PHPMailer with SMTP, or native `mail()`
- **Configuration**: `vlucas/phpdotenv` for environment-based configuration
- **Frontend**: HTML5, CSS3, JavaScript (vanilla and ES modules), Bootstrap 5
- **Dependency Management**: Composer

## Requirements

- **PHP 8.0+** with extensions: `mysqli`, `gd`, `ctype`, `mbstring`, `openssl`, `curl`, `json`. On XAMPP, you may need to enable some of these in `xampp/php/php.ini` by removing the leading semicolon from the matching `extension=` line and restarting Apache.
- **MySQL 8.0+** or **MariaDB 10.5+** (required for `utf8mb4_0900_ai_ci` collation and the `uuid_to_bin()` / `bin_to_uuid()` functions).
- **Composer** (PHP dependency manager).
- **Web Server** with `mod_rewrite` (Apache, Nginx, or the PHP built-in server for development).

## Installation

> **Development environment**: EasyTalk was developed with XAMPP for PHP and Apache, alongside a standalone MySQL Community Server installation. XAMPP ships with MariaDB, but the application uses MySQL functions like `uuid_to_bin()` / `bin_to_uuid()` that require either MySQL 8.0+ or MariaDB 10.5+, so a newer MySQL was installed separately and connected through phpMyAdmin. The instructions below assume a similar setup, but the application runs on any compatible PHP / MySQL / Apache stack that meets the [Requirements](#requirements).

### 1. Clone the repository

```bash
git clone https://github.com/thanasis-kouskouras/Real-time-Chat-Application.git
cd Real-time-Chat-Application
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Database setup

Create a MySQL database, then import the schema.

First, connect to MySQL:

```bash
mysql -u root -p
```

Inside the MySQL prompt, create the database and exit:

```sql
CREATE DATABASE app_database CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
EXIT;
```

Back in the terminal, import the schema:

```bash
mysql -u root -p app_database < app_database.sql
```

> **Note**: `-u root` connects as the default MySQL user used in local environments such as XAMPP, WAMP, and MAMP. The `-p` flag prompts for a password. XAMPP's `root` user has no password by default, so you can press Enter at the prompt. In production, replace these credentials with your own.

You may use any database name you prefer. Just make sure it matches the `DB_NAME` value you set in your `.env` file in the next step.

### 4. Environment configuration

> ⚠️ **The `.env` file is not committed to git** (it contains secrets and is listed in `.gitignore`). You must create it locally from `.env.example` before the application can run.

Copy the example file and fill in your values:

```bash
cp .env.example .env
```

Open `.env` in a text editor and configure each section. Generate strong random secrets for the following keys:

```bash
# JWT signing secret (96 hex chars)
php -r "echo bin2hex(random_bytes(48));"

# Message encryption key (96 hex chars), used for AES-256-GCM
php -r "echo bin2hex(random_bytes(48));"

# WebSocket server-to-server API key (64 hex chars)
php -r "echo bin2hex(random_bytes(32));"
```

Paste each generated value into `JWT_SECRET`, `ENCRYPTION_KEY`, and `WS_SERVER_API_KEY` respectively.

For email delivery, a Gmail App Password is the recommended option. You can generate one at [myaccount.google.com → Security → App passwords](https://myaccount.google.com/apppasswords).

> **Note on `APP_PATH`**: this must match the URL path under which your project is served. For example, if you place the project under `htdocs/Real-time-Chat-Application/` (XAMPP), set `APP_PATH=/Real-time-Chat-Application`. If the application is served from the web root, leave it empty.

### 5. (Optional) Advanced configuration

A few additional settings live directly in [config.php](config.php) rather than in `.env`:

- **`date_default_timezone_set('Europe/Athens')`**: Change to your local timezone.
- **`MAX_FILE_SIZE`**: Maximum upload size (default 50 MB).
- **`USE_MAIL`**: Email backend, either `"PhpMailer"` (SMTP, the default) or `"Native"` (PHP's `mail()`).
- **Image resize constants** (e.g. `PROFILE_IMAGE_RESIZE_WIDTH`, `CHAT_IMAGE_MAX_WIDTH`): Adjust profile picture and chat image dimensions.

> **Email backend note**: The default `USE_MAIL = "PhpMailer"` setting sends emails directly via SMTP using the `.env` credentials, so no extra setup is needed. If you switch to `USE_MAIL = "Native"` on XAMPP, you also need to configure SMTP credentials in `xampp/sendmail/sendmail.ini`:
>
> ```ini
> auth_username = your-email@gmail.com
> auth_password = your-app-password
> ```

### 6. Start the web server

If you are using XAMPP, place the project under `htdocs/` and start Apache. The application will be reachable at `http://localhost/<your-folder-name>/`.

### 7. Start the WebSocket server

In a separate terminal, run:

```bash
php bin/chat-server.php
```

The server binds to the port defined in `WS_SERVER_PORT` (default `8082`) and listens for connections from origins listed in `WS_ALLOWED_ORIGINS`.

### 8. (Optional) Start the email queue worker

For asynchronous email notifications, run the queue worker in a separate terminal:

```bash
php bin/process-email-queue.php
```

This drains the file-based email queue at `tmp/email_queue.json` and sends queued emails through your configured SMTP provider.

### 9. Access the application

Open your browser and navigate to your configured URL (for example `http://localhost/Real-time-Chat-Application/`). Register a new account, verify your email, and start chatting.

### 10. (Optional) Promote a user to administrator

After registering an account, you can grant it administrator privileges by running the following SQL on your database:

```sql
UPDATE users SET user_role = 'admin' WHERE user_email = 'your-email@example.com';
```

Administrators gain access to the User Management panel for moderating accounts (list, search, ban, unban).

## Academic Context

- **Diploma Thesis**: Creation of a Dynamic Website for Asynchronous Communication between Users
- **Institution**: University of Western Macedonia, Kozani, Greece
- **Department**: Electrical & Computer Engineering
- **Laboratory**: [Laboratory of Digital Systems and Computer Architecture](https://arch.ece.uowm.gr/)
- **Year**: 2026
- **Developed by**: Athanasios Kouskouras
- **Supervised by**: Dr. Minas Dasygenis

## Contributing

This project was developed as a Diploma Thesis and is open source for educational and personal use. Contributions from developers who want to help improve EasyTalk are welcome, whether for bug fixes, new features, documentation, or security enhancements.

All contributors will be acknowledged. Significant contributions will be highlighted in project documentation.

## License

This project is dual-licensed:

- **Open Source**: GNU AGPLv3
- **Commercial License**: Available upon request. Contact: tnskousko@gmail.com

See the [LICENSE](LICENSE) file for details.
