# DigSignage – Queue Management System Module

> **DigSignage implementation notes**
>
> - Tenancy is **Team** (`team_id`), not a renamed Organization. Spec mentions of organization still mean the current team.
> - Queue Management is gated by the configurable `queue_management` plan entitlement implemented in **Phase 29**.
> - Implement **one phase at a time**. Run quality gates (formatter, static analysis, relevant tests, frontend typecheck/build) after each phase before starting the next.

## Objective

Extend the existing DigSignage digital signage SaaS platform with a complete **Queue Management System (QMS)**.

The Queue Management System must be a standalone module inside DigSignage but deeply integrated with the existing:

- Organizations / Teams
- Locations
- Screens
- Players
- Designer
- Playlists
- Channels
- Scheduling
- Users and permissions
- Notifications
- Real-time communication
- Reporting

The system should allow businesses such as hospitals, clinics, pharmacies, banks, government offices, customer service centers, restaurants, and retail stores to manage customer queues while displaying real-time queue information on existing DigSignage screens.

The architecture must be production-ready, scalable, multi-tenant, secure, and modular.

---

# PHASE 1 – Queue Management Core

Create a new main navigation module:

Queue Management

Submenus:

- Overview
- Live Queue
- Services
- Counters
- Kiosks
- Tickets
- Appointments
- Displays
- Reports
- Settings

Queue Management must respect the existing team/organization tenancy architecture.

Every queue-related database record must belong to the appropriate organization/team.

---

# PHASE 2 – Queue Services

Allow administrators to create queue services.

Example:

Registration
Billing
Pharmacy
Customer Service
Technical Support

Each service should contain:

- Name
- Internal code
- Ticket prefix
- Description
- Location
- Opening hours
- Average service duration
- Maximum queue capacity
- Priority rules
- Assigned counters
- Display color
- Active/inactive status

Example ticket numbering:

Registration: A001
Billing: B001
Pharmacy: P001

Allow ticket numbering to reset:

- Daily
- Weekly
- Monthly
- Never

Prevent duplicate ticket numbers within the configured numbering period.

**Phase 2 implementation (DigSignage)**

- Table: `queue_services` (team-scoped). Unique `(team_id, code)` and `(team_id, ticket_prefix)`.
- Average duration is stored as `average_service_duration_seconds` (UI collects minutes).
- Opening hours JSON: `{ mon: { open, close, closed }, … }` through `sun`.
- `priority_rules` JSON defaults to `[]` (custom priorities are Phase 4). `default_priority` is a simple integer.
- Counters are not modeled. Copy on the services page notes they attach in Phase 5.
- Numbering cursor: `next_sequence`, `last_issued`, `sequence_period`. Tickets are not issued yet.
- Period keys (`App\Support\QueueTicketNumbering::periodKey`): daily `Y-m-d`, weekly ISO `YYYY-Www`, monthly `Y-m`, never `null`. Phase 3 tickets should unique on `(team_id, service_id, number, period)`.
- Preview: `QueueService::nextTicketNumber()` returns prefix + 3-digit padded sequence (e.g. `A001`) without creating a ticket.

---

# PHASE 3 – Ticket Management

Create a centralized ticket engine.

Ticket statuses:

- Waiting
- Called
- Serving
- On Hold
- Transferred
- Completed
- No Show
- Cancelled

Store:

- Ticket number
- Service
- Location
- Customer
- Priority
- Queue position
- Created time
- Called time
- Service start time
- Completion time
- Counter
- Assigned employee
- Waiting duration
- Serving duration
- Source

Ticket sources:

- Kiosk
- Staff
- QR
- Website
- Appointment
- API

Queue position must update automatically.

---

# PHASE 4 – Queue Priority Engine

Support multiple queue strategies.

Default:

FIFO

Additional options:

- Priority queue
- Appointment priority
- VIP
- Emergency
- Senior
- Accessibility priority
- Manual priority

Do NOT hard-code priority categories.

Allow administrators to define custom priority levels.

Example:

Normal = 0
Appointment = 10
Senior = 20
VIP = 30
Emergency = 100

The queue engine should determine the next eligible ticket based on service, counter capabilities, priority, waiting time, and configured queue strategy.

Prevent starvation of normal-priority customers by allowing configurable maximum priority waiting rules.

---

# PHASE 5 – Counter Management

Create counters/service desks.

Example:

Counter 01
Counter 02
Pharmacy Counter 01
Registration Desk A

Fields:

- Name
- Code
- Location
- Supported services
- Assigned staff
- Status

Statuses:

- Open
- Closed
- Busy
- Paused

One counter may support multiple services.

Create a dedicated staff Counter Dashboard.

Example:

---------------------------------
COUNTER 04
Customer Service
---------------------------------

Currently Serving

A105

Serving Time
02:34

[ CALL NEXT ]

[ RECALL ]

[ HOLD ]

[ TRANSFER ]

[ COMPLETE ]

[ NO SHOW ]

---------------------------------

Waiting: 14
Average Wait: 8 min
---------------------------------

The counter interface must be optimized for fast operation.

Do not require counter employees to access the entire DigSignage administration interface.

---

# PHASE 6 – Call Next Engine

When staff clicks:

CALL NEXT

The backend must atomically select the next eligible ticket.

Prevent two counters from receiving the same ticket.

Use database transactions and appropriate locking.

Process:

Counter requests next ticket
→ Queue Engine selects eligible ticket
→ Ticket becomes CALLED
→ Counter assigned
→ Queue event created
→ Real-time event broadcast
→ Signage updates
→ Notification sound plays
→ Optional voice announcement

Support:

Recall
Complete
Hold
Resume
Transfer
No Show

---

# PHASE 7 – Ticket Transfer

Allow staff to transfer customers between:

- Services
- Counters
- Departments

Example:

Registration
↓
Doctor Consultation
↓
Billing
↓
Pharmacy

Maintain complete ticket journey/history.

Do not destroy the original ticket history when transferring.

Track:

- Previous service
- New service
- Previous counter
- Transfer reason
- Staff member
- Timestamp

---

# PHASE 8 – Kiosk System

Create a full-screen self-service kiosk interface.

The kiosk should allow customers to select a service.

Example:

WELCOME

Please select a service

[ Registration ]

[ Billing ]

[ Customer Service ]

[ Pharmacy ]

After selection:

Generate ticket.

Example:

YOUR TICKET

A105

People Ahead: 7

Estimated Waiting Time:
12 minutes

Support thermal ticket printers.

Ticket printout should optionally contain:

- Organization logo
- Location
- Ticket number
- Service
- Date/time
- People ahead
- Estimated waiting time
- QR code
- Custom footer

Support kiosk full-screen mode.

Administrators should be able to customize kiosk branding.

---

# PHASE 9 – QR / Virtual Queue

Allow customers to join queues using their phones.

Generate QR codes for:

- Location
- Individual service
- Specific queue

Customer scans QR.

Flow:

QR
→ Mobile queue page
→ Select service
→ Generate virtual ticket

Display:

Ticket A105

Current position:
7

Estimated waiting:
12 minutes

Now serving:
A098

Automatically update using WebSockets.

No page refresh should be required.

Allow customers to leave/cancel their virtual ticket.

---

# PHASE 10 – Digital Signage Integration

Queue Management must integrate directly with the existing DigSignage Designer.

Create a new Designer component category:

QUEUE

Add draggable blocks:

- Now Serving
- Recently Called
- Waiting Tickets
- Queue Position
- Counter Number
- Service Name
- Estimated Waiting Time
- Queue Statistics
- Queue Ticker
- QR Join Queue
- Queue Status
- Custom Queue Board

Each widget must support styling through the existing Designer.

Allow configuration of:

- Font
- Font size
- Background
- Borders
- Alignment
- Animation
- Number of tickets
- Services displayed
- Locations
- Counters
- Sound
- Voice announcements

---

# PHASE 11 – Queue Display Board

Allow screens to display layouts such as:

------------------------------------------------

              NOW SERVING

       TICKET           COUNTER

       A105                04
       A104                02
       B041                06

------------------------------------------------

           Advertisement / Video

------------------------------------------------

A106 → Counter 3
A107 → Counter 5

------------------------------------------------

Queue information should coexist with:

- Videos
- Images
- Advertisements
- News
- Weather
- Clock
- Announcements
- Existing DigSignage content

Do not make queue displays a completely separate signage engine.

Use the existing Designer/rendering system.

---

# PHASE 12 – Real-Time Updates

Use the application's existing real-time infrastructure.

Preferred architecture:

Laravel
→ Queue Service
→ Database
→ Domain Event
→ Laravel Reverb
→ WebSocket
→ Signage Player

When a ticket is called:

CallNextTicket
→ TicketCalled
→ Broadcast
→ Relevant screens receive event
→ Queue widget updates
→ Notification sound
→ Voice announcement

Use Redis where appropriate.

Use Laravel Horizon for background jobs.

REST polling must remain available as a fallback when WebSocket communication is unavailable.

---

# PHASE 13 – Voice Announcements

Support automatic voice calling.

Example:

"Ticket A one zero five, please proceed to Counter Four."

Configuration:

- Enable/disable
- Language
- Voice
- Speed
- Volume
- Repeat count
- Announcement chime

Allow multiple languages.

Example:

English
Arabic
Tagalog
Hindi

Design the voice provider behind an interface so different Text-to-Speech providers can be implemented later.

Do not tightly couple queue logic to one TTS provider.

---

# PHASE 14 – Audio Queue

Multiple simultaneous calls must not overlap.

Create a client-side announcement queue.

Example:

TicketCalled A105
TicketCalled A106
TicketCalled B023

Player processes:

A105 announcement
↓
Finish
↓
A106 announcement
↓
Finish
↓
B023 announcement

Allow configurable delay between announcements.

---

# PHASE 15 – Appointments

Allow appointments to integrate with queues.

Appointment fields:

- Customer
- Service
- Date
- Time
- Location
- Reference number
- Status

Customers may check in using:

- Kiosk
- QR code
- Staff
- Appointment reference

After check-in:

Appointment
→ Queue Ticket

Allow administrators to configure appointment priority rules.

---

# PHASE 16 – Customer Notifications

Create notification rules.

Examples:

Send notification when:

- Ticket created
- 5 customers ahead
- 3 customers ahead
- Customer is next
- Ticket called
- Ticket transferred
- Appointment approaching

Support provider architecture for:

- SMS
- WhatsApp
- Email
- Push notifications

Notification processing should use queues.

---

# PHASE 17 – Live Queue Dashboard

Create a real-time operations dashboard.

Display:

Waiting Customers
Currently Serving
Completed Today
No Shows
Average Waiting Time
Average Service Time
Longest Waiting Customer
Open Counters
Closed Counters

Show queues grouped by:

- Location
- Service
- Counter

Allow supervisors to see queue congestion immediately.

---

# PHASE 18 – Queue Analytics

Create reports for:

Ticket Volume

Tickets per:
- Hour
- Day
- Week
- Month

Waiting Time

Show:
- Average
- Median
- Maximum
- Minimum

Service Time

Show:
- Average
- Median
- Maximum

Staff Performance

Show:
- Tickets served
- Average handling time
- No-show count

Counter Performance

Service Performance

Peak Hours

Abandonment Rate

No-Show Rate

SLA Compliance

Allow filters:

- Date range
- Location
- Service
- Counter
- Employee

Allow CSV/Excel/PDF export where supported by the application's existing reporting infrastructure.

---

# PHASE 19 – Queue Alerts

Create automated alerts.

Examples:

Average waiting time > 20 minutes

Waiting customers > 30

Customer waiting > 45 minutes

No counter available

Queue capacity reached

Counter offline

Send alerts through the application's notification infrastructure.

---

# PHASE 20 – Multi-Location Support

One organization may have:

Dubai Branch
Abu Dhabi Branch
Sharjah Branch

Each location should have independent:

- Queues
- Services
- Counters
- Kiosks
- Screens
- Operating hours

Corporate administrators should be able to view combined analytics.

---

# PHASE 21 – Roles and Permissions

Integrate with existing authorization.

Permissions should include:

queue.view
queue.manage
queue.call
queue.transfer
queue.complete
queue.cancel

services.manage
counters.manage
kiosks.manage
appointments.manage

queue_reports.view
queue_settings.manage

Roles could include:

Super Admin
Organization Admin
Branch Manager
Queue Supervisor
Counter Staff
Reporting User

Do not rely only on UI restrictions.

All permissions must be enforced server-side.

---

# PHASE 22 – Audit Trail

Record important actions.

Examples:

Ticket created
Ticket called
Ticket recalled
Ticket transferred
Ticket completed
Ticket cancelled
Ticket marked no-show
Priority changed
Counter opened
Counter closed

Store:

User
Action
Entity
Old value
New value
IP
Timestamp

---

# PHASE 23 – Queue API

Create a versioned API.

Example:

GET /api/v1/queue/services

POST /api/v1/queue/tickets

GET /api/v1/queue/tickets/{id}

POST /api/v1/queue/tickets/{id}/cancel

POST /api/v1/counters/{id}/next

POST /api/v1/tickets/{id}/recall

POST /api/v1/tickets/{id}/complete

POST /api/v1/tickets/{id}/transfer

GET /api/v1/queue/status

Protect APIs using the application's existing authentication mechanism.

Implement:

- Rate limiting
- Validation
- Authorization
- API logging

---

# PHASE 24 – Webhooks

Allow external applications to subscribe to:

ticket.created
ticket.called
ticket.serving
ticket.completed
ticket.transferred
ticket.cancelled
ticket.no_show

Webhook deliveries must:

- Be signed
- Retry failures
- Have delivery logs
- Support secret rotation
- Use queued processing

---

# PHASE 25 – Database Architecture

Create normalized tables similar to:

queue_services
queue_counters
queue_counter_service
queue_tickets
queue_ticket_events
queue_priorities
queue_kiosks
queue_displays
queue_appointments
queue_notification_rules
queue_settings

Reuse existing:

users
teams/organizations
locations
screens

where appropriate.

Do not duplicate existing platform entities.

Add indexes for frequently queried columns such as:

organization_id
location_id
service_id
counter_id
status
priority
created_at

Use foreign keys where appropriate.

Use soft deletes only where business requirements require them.

---

# PHASE 26 – Concurrency and Reliability

Queue calling is concurrency-sensitive.

Use:

- Database transactions
- Row locking where appropriate
- Idempotent commands
- Unique constraints
- Queue job retries
- WebSocket reconnect handling

Calling "Next" simultaneously from multiple counters must NEVER assign the same ticket twice.

Prevent duplicate ticket generation caused by double-clicks or network retries.

---

# PHASE 27 – Offline Resilience

Digital signage displays must continue displaying the most recently known queue state during temporary network interruption.

Show connection state:

ONLINE
RECONNECTING
OFFLINE

When connection returns:

Player
→ Request latest queue snapshot
→ Reconcile state
→ Resume real-time events

Do not replay stale ticket announcements after reconnecting.

---

# PHASE 28 – Emergency Integration

Integrate with DigSignage's existing Emergency feature.

Emergency content must have higher priority than queue content.

Example:

Normal:

Advertisements + Queue

Emergency activated:

FULL SCREEN EMERGENCY MESSAGE

When emergency ends:

Restore previous signage layout
→ Refresh queue state
→ Continue normal operation

---

# PHASE 29 – SaaS Plan Controls

Make Queue Management an optional SaaS module.

Example plans:

Basic
- Digital Signage

Professional
- Digital Signage
- Queue Management

Business
- Digital Signage
- Queue Management
- Advanced Analytics
- API
- Webhooks

Enterprise
- Unlimited/negotiated locations
- Advanced integrations
- SSO
- Custom SLA

Do not hard-code plan names.

Use feature flags/entitlements so plans can be changed later.

---

# PHASE 30 – Testing

Create automated tests covering:

Ticket creation
Ticket numbering
Call next
Concurrent call-next requests
Priority queues
Transfers
No shows
Counter assignment
Permissions
Tenant isolation
Appointments
Queue events
API authentication
Webhooks

Especially test:

Two employees press CALL NEXT simultaneously.

Expected:

Counter 1 → A101
Counter 2 → A102

NEVER:

Counter 1 → A101
Counter 2 → A101

---

# UX REQUIREMENTS

Follow the existing DigSignage UI shown in the current application.

Reuse:

- Existing sidebar
- Cards
- Tables
- Buttons
- Typography
- Status badges
- Forms
- Modals
- Pagination
- Search
- Filters

Do not introduce an unrelated design system.

Queue Management should visually feel like a native DigSignage feature.

Desktop interfaces should be optimized for administrators.

Counter and kiosk interfaces should be simplified and optimized for their specific purpose.

All interfaces must be responsive.

---

# ENGINEERING REQUIREMENTS

Follow the existing application's architecture and coding conventions.

Before implementing:

1. Inspect the existing project structure.
2. Inspect authentication and tenancy.
3. Inspect existing models and relationships.
4. Inspect screen/player architecture.
5. Inspect Reverb/WebSocket implementation.
6. Inspect Horizon/queue configuration.
7. Inspect Designer/block architecture.
8. Inspect roles/permissions.
9. Inspect notification architecture.
10. Inspect API conventions.

REUSE existing functionality instead of creating duplicate systems.

Keep business logic out of controllers.

Use appropriate:

- Services
- Actions
- Events
- Listeners
- Jobs
- Policies
- Form Requests
- Resources
- Enums
- DTOs/value objects where useful

Keep the Queue Management domain modular enough that it can evolve independently from Digital Signage.

Do not break existing signage functionality.

Run existing automated tests after every major implementation phase.

Add tests for all new critical functionality.

Run the project's formatter and static analysis tools before considering each phase complete.

---

# Final Product Vision

DigSignage should evolve from only a digital signage platform into:

DIGITAL SIGNAGE
+
QUEUE MANAGEMENT
+
KIOSKS
+
VIRTUAL QUEUE
+
APPOINTMENTS
+
REAL-TIME CUSTOMER CALLING
+
ANALYTICS

The key advantage is integration.

Businesses should be able to use the same DigSignage screen for:

Advertising
+
Information
+
Queue Calling
+
Announcements
+
Emergency Messages

while managing everything centrally from one SaaS dashboard.
