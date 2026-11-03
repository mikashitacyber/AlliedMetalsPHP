# Allied Metals Customer Service & Order Tracking Portal (PHP + MySQL / XAMPP)

## Roles and how they get in

Customers can request an account through public sign-up. New accounts remain
pending until an Admin reviews and approves them in **Manage Users**; only approved
customers can sign in. Admins no longer create customer accounts or set customer
passwords directly. For non-general service and order requests, assigned Staff can send
a formal PHP quotation with notes and an expiry date. Customers can review it and accept
or decline it from the request page; staff can issue a revised quote if needed.

| Role | Created by | Can do |
|---|---|---|
| **Customer** | Public sign-up, then Admin approval | Submit inquiries/quotation requests with files, **track each order** through its stages, use the support chatbot, and reply to employee-started direct conversations |
| **Staff** | Admin → Manage Staff & HR | See the full queue and **Assigned to Me**, reply, change status, request more info, see and update their assigned tasks, **set own status** (Available / On break / Offline) on their profile, and message customers |
| **HR** | Admin → Manage Staff & HR | Assign tickets to a specific staff member, assign deliveries / installations / other tasks to Staff or Field Technicians, view staff workload, set own status, and message customers |
| **Field Technician** | Admin → Manage Staff, HR & Technicians | **Field Portal**: sees only tasks assigned to them, opens linked customer-order details when provided, updates task progress and work notes, sets own status |
| **Admin** | `setup_first_admin.php` (once) | Reports, manage customers and employee accounts, assign deliveries / installations / other tasks, set own status, and message customers |

## Order tracking stages

`Received → In Review → Awaiting Your Reply → Quoted → In Fabrication → Ready for Delivery → Out for Delivery → Delivered → Closed`

General inquiries use a shorter workflow: `Received → In Review → Awaiting Your Reply → Answered → Closed`.
They do not show order-production or delivery stages, and an answered general inquiry is counted as resolved.
Quotation, equipment, and project requests continue to use the order workflow below.
Answered, Delivered, and Closed inquiries leave the active staff queues and become read-only;
their messages and documents remain available in the Completed Inquiries archive.
Customers see a plain-language explanation of the current stage and dated milestone history.
Milestone dates are recorded when staff update a request; they are not automatic production or
GPS tracking.

Admin and HR assign employee tasks from **Work Assignments**. Tasks can be deliveries,
installations, or other work; they include instructions, a site address, and a due date, and
can optionally link to an active customer order. Tasks can be assigned to any active Staff
member or Field Technician. Employees see their own tasks in **My Work**, update progress
from Assigned to In Progress to Completed, and add work notes. Task assignment changes and
progress updates are recorded in the task history. Linking a task to an order provides
customer/site context; task progress does not automatically change the customer's inquiry
status. This is task assignment, not live GPS. `upgrade_v9.sql` imports existing customer
job assignments into the new employee task list and leaves the original legacy records intact.

## Setup

1. Install XAMPP (or another server with a compatible PHP version, PDO MySQL, and
   MySQL/MariaDB). Start Apache and MySQL, then clone/download this repository into
   `htdocs`. For example, if the folder is
   `C:\xampp\htdocs\AlliedMetalsPHP`, the site URL is
   `http://localhost/AlliedMetalsPHP/`.
2. Copy `config/config.example.php` to `config/config.php`. Set `BASE_URL` to the
   URL path of the folder. Set the database values for your local MySQL server; the
   example defaults are for a standard XAMPP installation. `config/config.php` is
   intentionally excluded from Git because it may contain local credentials.
3. **New install:** in phpMyAdmin, create/open the `allied_metals` database and run
   `schema.sql` from the repository. This schema includes all current tables, so do
   not run the upgrade scripts for a fresh database.
   **Existing database from an earlier version:** select `allied_metals`, open its
   **SQL** tab and run any migrations not yet applied, in order: `upgrade_v3.sql`,
   `upgrade_v4.sql` (staff status),
   `upgrade_v5.sql` (Field Technician role + legacy delivery assignments), `upgrade_v6.sql` (document archive),
   `upgrade_v7.sql` (legacy assignment history), `upgrade_v8.sql` (profile photos and bios),
   `upgrade_v9.sql` (employee task assignments), `upgrade_v10.sql` (employee-started customer conversations),
   `upgrade_v11.sql` (direct-message image attachments), `upgrade_v12.sql`
   (customer notifications), `upgrade_v13.sql` (pending customer sign-ups), and
   `upgrade_v14.sql` (formal customer quotations).
   Run each only once. Existing legacy job-order rows are not deleted by `upgrade_v9.sql`.
4. Open `setup_first_admin.php` in the browser and create the first Admin using a
   unique password. After signing in, **delete that file**. The setup page disables
   itself once an Admin exists and never displays the password.
5. From the Admin portal create Staff, HR and Field Technician accounts. Review
   customer sign-up requests in **Manage Users**.

The GitHub repository contains source code, not a hosted/running website or a copy of
the local database and customer uploads. To demo on another computer, import the schema,
create its local config, and use demo accounts/data. The app has no automated test suite;
verify login, request submission, staff status updates, quotes, and file handling on the
target computer before presenting.

New and changed passwords must be at least 8 characters, include at least one
letter and one number, and be no more than 72 bytes. Existing passwords continue
to work at sign-in; this policy is applied when an account is created or its
password is changed.

## Inquiry document archive

Customers can upload documents when opening an inquiry or later from its conversation,
and search their files in My Documents.
The assigned Staff member can add files to the inquiry and download its documents.
HR and Admin can search the full Document Archive; Staff can search documents for
inquiries assigned to them. Customers can download documents on their own inquiries.
Files are limited to five at a time and 10 MB each (PDF, JPG, PNG, DOC, DOCX, XLS,
XLSX, or TXT). Apache blocks direct web access to `uploads/`; downloads are served
through an authenticated endpoint. On existing databases, run `upgrade_v6.sql` once
before using the archive.

Customers can use **Report a Portal Problem** for sign-in, upload, or other website
issues. These reports use the existing inquiry ticket system and are routed to Customer
Service; customers can follow responses in Track My Orders. Equipment, delivery, and
technical-service concerns should continue to use a regular inquiry.

The customer dashboard shortcuts have distinct actions: **Submit an Inquiry** opens a
general inquiry form, **Request a Quotation** preselects Equipment inquiry, and
**Upload Files** opens the customer's request list so uploaded documents can be attached
to the correct inquiry.

## MIS inquiry report

Admin and HR can open **MIS Report** from their portal navigation to review inquiry
counts for a date range, including current open/resolved/unassigned totals and
breakdowns by status, request type, and department. The detailed inquiry list can
also be downloaded as CSV.

Admin and HR can also open **Customer Report** to search customer accounts, review contact
and account information, see each customer's inquiry/order history, and export that
customer's records as CSV. Customer Service department Staff can view the report; other
Staff departments do not have access.

Assigned Staff can prepare a formal quotation for non-general order and service requests.
Each request has one current quote with a PHP amount, optional notes, and a valid-through
date. Sending or revising a quote notifies the customer and moves the request to **Quoted**.
Customers can accept or decline an unexpired quote; their response is recorded in the
request conversation. Apply `upgrade_v14.sql` once to existing databases before using
this feature.

The Admin and HR **Decision Support** dashboard uses transparent rules to highlight
high-priority and 24-hour-old open inquiries, suggest a matching-department staff member
based on open workload (with online presence as a tie-breaker), and flag employees
with multiple active tasks on the same future date. Suggestions do not make assignments.
Because tasks store due dates but not time slots, schedule flags are potential same-day
overlaps rather than confirmed conflicts.

All portal roles can add a profile picture, phone number, and short bio from their
profile page. Profile pictures accept JPG, PNG, or WebP up to 2 MB and are served only
to signed-in portal users. Employees can open **Customer Messages** to find active
customer accounts and start direct conversations. Customers cannot start direct
employee chats, but can reply to a conversation an employee starts and share JPG, PNG,
or WebP images up to 5 MB. Large phone photos are resized in supported browsers before
upload; unsupported formats should be saved or shared as JPG. On mobile, opening a
conversation hides the contact list so the reply composer stays accessible. The floating
support chatbot is hidden on customer message and inquiry conversation pages so it does
not cover replies, and remains available on other pages for general questions. Inquiry
conversations remain available for order-specific discussions. Presence is updated while
a user is active in the portal. Existing
databases must run `upgrade_v10.sql` and `upgrade_v11.sql` to enable direct messages
and image attachments, `upgrade_v12.sql` to enable customer notifications, and
`upgrade_v13.sql` to enable customer sign-up requests, and `upgrade_v14.sql` to enable
formal customer quotations.
Customers receive unread alerts for direct employee messages, staff replies, request
status changes, staff assignment, and documents added to a request.

Signed-in users can switch between light and dark appearance using the compact control
in their profile page header. Light mode remains the default, and the selection is
remembered in the current browser.

`reset_password.php` is a one-time recovery tool if you ever need to reset an account's
password (for example after importing a database from another PC). Delete it after use.

## FAQ assistant (Groq)

The customer portal's FAQ assistant calls Groq from `customer/support_chat_api.php`.
It sends only the customer's question and a fixed list of public company FAQs; it does
not read inquiry or account data. PHP reads the key from the `GROQ_API_KEY` environment
variable. On Windows, add `GROQ_API_KEY` as a system environment variable, then fully
restart Apache from the XAMPP Control Panel. Never put the key in JavaScript or commit it
to the project. The default model is `openai/gpt-oss-20b`; set `GROQ_MODEL` as an
environment variable to use another model enabled for your Groq account. PHP checks both
the process environment and Apache's `$_SERVER` environment values for these settings.
Without a key, the widget reports that the assistant is not configured and provides
contact details.

## Moving to another PC

Export the database from phpMyAdmin (Export tab) and copy the project folder. On the
new PC: create an empty `allied_metals` database, **open it**, use its **Import** tab,
then set `BASE_URL` and `$DB_PORT` in `config/config.php` for that machine. Do not run
`schema.sql` before importing, since the tables would already exist.

## Folder map

```
index.php            landing page + login
customer/            dashboard, new inquiry, track my orders, order detail, profile
staff/               queue.php, assigned.php, profile.php (+ shared _actions / _ticket_detail_panel)
hr/                  dashboard.php (assign), workload.php, profile.php
technician/          assignments.php (legacy redirect to My Work), profile.php
employee_tasks.php   Admin / HR work assignment management
employee_work.php    Staff / Field Technician assigned task portal
admin/               overview.php, users.php, staff.php, profile.php
includes/            header, footer, ticket_card, staff_profile_core (shared profile page)
assets/              style.css, js/, images/ (logo.png + optional landing photos, see below)
prototype/           standalone clickable HTML for slides (not connected to the database)
```

## Known simplifications (state these honestly in your SAD paper)

- Tracking stages are updated manually by staff; there is no GPS or live map.
- No email/SMS notifications. Conversation messages update automatically while the
  inquiry page is open (the page checks every 3 seconds); status and other page data may
  still require a reload.
- Inquiry documents are limited on the server to five supported files per upload, up to 10 MB each.
- A staff member's own status (Available / On break / Offline) always wins. When they
  are Available, "Online now" means active in the last 2 minutes. After 2 minutes
  without activity, customers see them as Offline; activity is tracked on employee
  pages even when a page stays open. This is not a live chat connection.
- The landing page's Capabilities, Support and About links scroll to sections on the
  same page.

## Landing page hero photo (optional)

The landing page works without a photo, using a dark steel look. To add a hero image,
put a JPG named `hero.jpg` in `assets/images/`:

| File | Where it appears | Suggested size |
|---|---|---|
| `hero.jpg` | Behind the main headline | 1920 × 900 |

Use only a photo Allied Metals gave you permission to use.
The **About** section links to the official Allied Metals website for company information.
