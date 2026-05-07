<?php
/* PUBLIC PRIVACY POLICY PAGE

Two render modes:
- Default: full standalone HTML page (for direct visits, bookmarks, JS-disabled clients)
- ?fragment=1: returns ONLY the inner card markup (consumed by the in-app modal) */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/api-response.php';

//Security headers (set before any output and before rate limit check, so even 429 responses carry them)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* Content-Security-Policy header. 

Defense-in-depth for the public Privacy Policy page.
The page loads only same-origin CSS + a favicon and contains no scripts at all, so we lock everything down to 'self'. */
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "img-src 'self' data:; " . //img-src adds `data:` to allow Bootstrap's inline-SVG icons which are encoded as data URIs in the Bootstrap CSS
    "style-src 'self'; " .
    "font-src 'self'; " .
    "script-src 'self'; " .
    "frame-ancestors 'self'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "object-src 'none'" //Explicitly forbids legacy plugin embedding
);

/* Per-IP rate limit: 20 requests/minute per IP.
Defense against opportunistic single-source spam. */
$rateLimitIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit($rateLimitIp, 'privacy_policy_view', 20, 60);

//Set Cache-Control after rate limit so 429 responses are not cached
header('Cache-Control: public, max-age=3600');

$isFragment = isset($_GET['fragment']) && $_GET['fragment'] === '1';
$lastUpdated = '4 May 2026';
$contactEmail = PRIVACY_EMAIL;
$path = $GLOBALS['rootUrl'] . '/static';

if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>EasyTalk &mdash; Privacy Policy</title>
    <link rel="shortcut icon" type="image/png" href="img/logo.png" />

    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/buttons.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/mobile.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/privacy-policy.css" ?>' />
</head>

<body>
    <main class="d-flex vw-100 justify-content-center">
        <div class="container mt-5 pt-5 mb-5">
            <div class="row justify-content-center">
                <div class="col-12 privacy-policy-container">
                    <?php endif; ?>

                    <div class="card p-4 privacy-policy-card">
                        <div class="card-body">
                            <h2 class="text-center">Privacy Policy</h2>
                            <p class="text-center text-muted"><small>Last updated:
                                    <?= htmlspecialchars($lastUpdated) ?></small></p>

                            <p>This Privacy Policy explains how <strong>EasyTalk</strong> ("we", "our", "the service")
                                collects, uses, stores, and protects your personal data when you use our web
                                application. EasyTalk is a real-time communication platform developed as part of a
                                Diploma Thesis at the <strong>University of Western Macedonia (UOWM)</strong>,
                                Department of Electrical &amp; Computer Engineering, in Kozani, Greece.
                            </p>

                            <p>We comply with the EU General Data Protection Regulation (GDPR) and related national
                                legislation. By
                                creating an account or otherwise using the service, you confirm that you have read and
                                understood this
                                policy.</p>

                            <hr>

                            <h5>1. Who we are</h5>
                            <p><strong>Service:</strong> EasyTalk &mdash; Real-Time Chat Web Application<br>
                                <strong>Project context:</strong> Developed as part of a Diploma Thesis<br>
                                <strong>Developed by:</strong> Athanasios Kouskouras<br>
                                <strong>Supervised by:</strong> Dr. Minas Dasygenis<br>
                                <strong>Institution:</strong> Department of Electrical &amp; Computer Engineering,
                                University of Western Macedonia, Kozani, Greece (EU)<br>
                                <strong>Laboratory:</strong> <a href="https://arch.ece.uowm.gr" target="_blank"
                                    rel="noopener">Laboratory of Digital Systems and Computer Architecture</a><br>
                                <?php if (!empty($contactEmail)): ?>
                                <strong>Contact:</strong>
                                <a
                                    href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>
                                or use the <a href="contact_us.php">Contact Us</a> form
                                <?php else: ?>
                                <strong>Contact:</strong> use the <a href="contact_us.php">Contact Us</a> form
                                <?php endif; ?>
                            </p>

                            <hr>

                            <h5>2. Hosting &amp; status of the service</h5>
                            <p>EasyTalk is currently in <strong>development / academic stage</strong> and is not yet
                                operated as a
                                commercial service. The application runs in an academic environment within the European
                                Union (Greece /
                                UOWM). No personal data is transferred outside the European Economic Area, except for
                                emails delivered
                                through Google&rsquo;s SMTP infrastructure (see Section 6).</p>
                            <p>If we move to production hosting in the future, this section will be updated to disclose
                                the hosting
                                provider and any cross-border transfer safeguards (Standard Contractual Clauses or
                                adequacy decisions).
                            </p>

                            <hr>

                            <h5>3. Data we collect</h5>

                            <p><strong>Account data</strong> (provided by you during registration and profile setup):
                            </p>
                            <ul>
                                <li>A <strong>username</strong> you choose, visible to other users</li>
                                <li>Your <strong>email address</strong> (used for login, account verification, and
                                    notifications)</li>
                                <li>A <strong>password</strong> (never stored in plain text, only a securely
                                    hashed version is retained)</li>
                                <li>An optional <strong>profile image</strong></li>
                                <li><strong>Account state</strong> (active, banned, or deleted)</li>
                                <li>Account creation timestamp</li>
                            </ul>

                            <p><strong>User-generated content</strong> (created by you while using the service):</p>
                            <ul>
                                <li>Direct (1-on-1) and group chat messages</li>
                                <li>Voice messages and video messages recorded in the chat (microphone and camera
                                    access requires your explicit consent each time)</li>
                                <li>Photos captured via the in-app camera (camera access requires your explicit consent
                                    each time)</li>
                                <li>File attachments you upload in chats such as images, audio, video, and
                                    documents (subject to size and format limits)</li>
                                <li>Group memberships, group images, group names</li>
                                <li>Friend requests and friendships</li>
                                <li>Contact form submissions: the subject, message, and optionally your nickname and
                                    surname you provide. Your username, email, and an internal account identifier are
                                    attached to each submission to link it to your account</li>
                                <li>Privacy and notification preferences such as account visibility settings and
                                    email notification opt-in</li>
                            </ul>

                            <p><strong>Technical &amp; security data</strong> (collected automatically while using the
                                service):</p>
                            <ul>
                                <li><strong>Strictly necessary cookies</strong> for authentication and session
                                    management (see Section 7)</li>
                                <li>Your <strong>IP address</strong> (used only for abuse-prevention purposes)</li>
                                <li><strong>Real-time presence indicators</strong> (online/offline status and typing
                                    notifications, transmitted only while you are actively connected)</li>
                            </ul>

                            <hr>

                            <h5>4. How we use your data</h5>
                            <ul>
                                <li>To create and authenticate your account</li>
                                <li>To deliver messages, files and notifications between you and other users you
                                    have chosen to communicate with</li>
                                <li>To match friend requests and maintain group memberships</li>
                                <li>To send you transactional emails for account verification, password resets, friend
                                    requests, and notifications about direct messages you received while offline</li>
                                <li>To enforce security, prevent abuse, and protect accounts from unauthorized access
                                </li>
                                <li>To respond to your contact form messages and privacy requests</li>
                            </ul>
                            <p>We <strong>do not</strong> use your data for advertising, profiling, behavioural
                                tracking, or sale to
                                third parties.</p>

                            <hr>

                            <h5>5. Legal basis (GDPR Art. 6)</h5>
                            <ul>
                                <li><strong>Performance of a contract</strong> &mdash; for everything required to
                                    provide the service
                                    you signed up for (account, messaging, friends, groups).</li>
                                <li><strong>Your consent</strong> &mdash; for accessing your microphone or camera (asked
                                    by your
                                    browser each time), and for optional email notifications which you can enable in
                                    Settings.</li>
                                <li><strong>Legitimate interests</strong> &mdash; for security, fraud and abuse
                                    prevention.</li>
                                <li><strong>Legal obligation</strong> &mdash; if we are required to retain or disclose
                                    data by
                                    applicable law.</li>
                            </ul>

                            <hr>

                            <h5>6. Sharing &amp; third parties</h5>
                            <p>We <strong>do not sell, rent, or trade</strong> your personal data. Limited disclosure
                                occurs only in
                                the following cases:</p>
                            <ul>
                                <li><strong>Other users</strong>: information you choose to share with them, such as
                                    your username, profile image, the messages you send them, and your online status.
                                    Members of any group you both belong to can also see your role within that group
                                    (admin or member) and whether your account has been banned.</li>
                                <li><strong>Email delivery</strong>: transactional emails (verification, password reset,
                                    notifications,
                                    contact form) are delivered through a <strong>third-party email delivery
                                        service</strong>
                                    (currently Google&rsquo;s email infrastructure), acting as a sub-processor solely
                                    for that purpose.</li>
                                <li><strong>Administrator</strong>: the developer can access user records (username,
                                    email, profile image, account status, and registration date) for moderation and
                                    abuse prevention.</li>
                                <li><strong>Legal authorities</strong>: if compelled by valid legal process, we may
                                    disclose data to
                                    competent authorities.</li>
                            </ul>
                            <p>Front-end libraries used by the interface are <strong>self-hosted</strong> on our
                                server. The application does <strong>not</strong> use Google Analytics, Facebook Pixel,
                                advertising trackers, or any other third-party tracking technology.</p>

                            <hr>

                            <h5>7. Cookies</h5>
                            <p>EasyTalk uses only <strong>strictly necessary</strong> cookies for authentication and
                                session
                                management. No tracking, analytics, or advertising cookies are set.</p>
                            <ul>
                                <li>An authentication cookie that keeps you logged in during your session</li>
                                <li>An optional persistent login cookie, set only if you choose &ldquo;Keep me logged
                                    in&rdquo; at login</li>
                                <li>A standard browser session cookie</li>
                                <li>A security token used to verify that form submissions come from your active
                                    session</li>
                            </ul>
                            <p>All cookies are configured with industry-standard security attributes appropriate to the
                                deployment
                                (restricted to first-party use, HTTPS-only flag where applicable).</p>

                            <hr>

                            <h5>8. Data retention &amp; deletion</h5>
                            <p>Different categories of data are retained as follows:</p>
                            <ul>
                                <li><strong>Active accounts</strong>: kept for as long as your account exists.</li>
                                <li><strong>Deleted accounts and deleted messages</strong>: marked as deleted
                                    (&ldquo;soft delete&rdquo;)
                                    and become invisible to other users. The underlying records may remain in the
                                    database for technical
                                    reasons (referential integrity, audit). You may request a <strong>full
                                        deletion</strong> via the Contact Us form (see Section 9).</li>
                                <li><strong>Verification and password reset tokens</strong>: short-lived,
                                    automatically invalidated on use or after a brief expiry window.</li>
                                <li><strong>Abuse-prevention records</strong>: retained only as long as needed to
                                    enforce these protections, then purged.</li>
                                <li><strong>Session cookies</strong>: cleared when you log out or your session expires.
                                </li>
                            </ul>

                            <hr>

                            <h5>9. Your rights under GDPR</h5>
                            <p>You have the right to:</p>
                            <ul>
                                <li><strong>Access</strong> &mdash; obtain a copy of the personal data we hold about
                                    you.</li>
                                <li><strong>Rectification</strong> &mdash; correct inaccurate or incomplete data.</li>
                                <li><strong>Erasure</strong> &mdash; request hard deletion of your data.</li>
                                <li><strong>Restriction</strong> &mdash; ask us to limit how we process your data.</li>
                                <li><strong>Portability</strong> &mdash; receive your data in a structured,
                                    machine-readable format.</li>
                                <li><strong>Object</strong> &mdash; object to specific processing activities.</li>
                                <li><strong>Withdraw consent</strong> at any time, where processing is based on consent.
                                </li>
                                <li><strong>Lodge a complaint</strong> with the Greek Data Protection Authority
                                    (<a href="https://www.dpa.gr" target="_blank" rel="noopener">www.dpa.gr</a>) or your
                                    local supervisory
                                    authority.</li>
                            </ul>
                            <p>To exercise any of these rights, please use our <a href="contact_us.php">Contact Us</a>
                                form with the
                                subject line <strong>&ldquo;Privacy Request&rdquo;</strong>. We will respond within 30
                                days.</p>

                            <hr>

                            <h5>10. Security measures</h5>
                            <ul>
                                <li>Passwords are never stored in plain text. They are hashed using industry-standard
                                    algorithms before storage.</li>
                                <li>Sensitive content stored on our servers is protected with strong encryption at rest.
                                </li>
                                <li>Database queries are designed to defend against injection attacks.</li>
                                <li>Forms that change account state include protections against unauthorized
                                    submissions.</li>
                                <li>Cookies are configured with appropriate security attributes.</li>
                                <li>We use additional HTTP response headers to defend against common browser-based
                                    attacks.</li>
                                <li>Sessions automatically expire after a period of inactivity.</li>
                                <li>Abuse-prevention mechanisms are applied across the service.</li>
                            </ul>

                            <hr>

                            <h5>11. Children</h5>
                            <p>EasyTalk is not directed at children under 16. We do not knowingly collect personal data
                                from children
                                under 16. If you believe a child has provided us with personal data, please contact us
                                so we can delete
                                the account.</p>

                            <hr>

                            <h5>12. Changes to this policy</h5>
                            <p>We may update this Privacy Policy from time to time. The &ldquo;Last updated&rdquo; date
                                at the top of this page indicates when the policy was most recently revised. We
                                encourage you to review this policy periodically. Where feasible, we may also notify you
                                through the email address associated with your account.</p>

                            <hr>

                            <h5>13. Contact</h5>
                            <?php if (!empty($contactEmail)): ?>
                            <p>For any privacy-related question or to exercise your rights, please use the
                                <a href="contact_us.php">Contact Us</a> form (preferred) or email
                                <a
                                    href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.
                            </p>
                            <?php else: ?>
                            <p>For any privacy-related question or to exercise your rights, please use the
                                <a href="contact_us.php">Contact Us</a> form.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$isFragment): ?>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
<?php endif; ?>