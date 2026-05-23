# System Context: AI-Powered Campus Lost & Found System

## 1. Project Background & System Scope
The project centers on an automated, back-end-driven ecosystem designed to modernize traditional lost and found processes within high-density university environments like the UiTM campus. It replaces manual paper logbooks and passive social media alerts with a robust two-tier architecture hosted on a Virtual Private Server (VPS), where a Laravel-based back-end serves as the central intelligence hub.

### Core Technical Pillars:
- **Multi-API Integration:** Synchronizes a Telegram Bot user interface, OpenCV/Vision AI automated image tag extraction, and Google Maps geospatial mapping.
- **Automated Matching Engine:** Performs a rolling mathematical matching calculation to compare AI-extracted descriptors and physical coordinates, dispatching real-time webhooks only when a confidence score exceeds the 80% threshold.
- **Secure Handover Protocol:** Eliminates fraudulent claims by generating a secure, system-driven One-Time Password (OTP) pushed to the student's verified Telegram account. Campus Security verifies this token before updating the item state to "Claimed" or archiving it as "Returned".

---

## 2. Relational Database Schema (ERD Specification)
The normalized database layer consists of seven tightly linked tables designed to maintain strict referential integrity:

- `users`: id (PK | int), name (string), matric_number (string, unique), telegram_chat_id (string, nullable), role (enum: Admin/Security/User), password (string, authentication hash).
- `categories`: id (PK | int), category_name (string), icon_identifier (string).
- `items`: id (PK | int), user_id (FK -> users.id), category_id (FK -> categories.id), type (enum: Lost/Found), title_description (string), latitude (float), longitude (float), location_name (string), image_path (string, nullable), status (enum: Pending/Matched/Claimed/Returned). *Note: image_path is nullable to store the raw evidence snapshot from a Finder via Telegram ('Found'), or an optional reference placeholder/stock image uploaded by a Loser via the Web Portal ('Lost').*
- `ai_tags`: id (PK | int), item_id (FK -> items.id), tag_name (string), confidence_level (float). *Establishes a one-to-many relationship with items to hold multi-label object attributes.*
- `match_alerts`: id (PK | int), lost_item_id (FK -> items.id), found_item_id (FK -> items.id), match_score (float), is_notified (boolean).
- `claims`: id (PK | int), item_id (FK -> items.id), claimant_user_id (FK -> users.id), security_guard_id (FK -> users.id), otp_code (string), claimed_at (timestamp).
- `api_logs`: id (PK | int), item_id (FK -> items.id), service (string: OpenCV/Maps/Bot), http_status_code (int), payload_response (text), logged_at (timestamp).

---

## 3. Core Backend Functional Workflows

### Asynchronous Multi-API Data Capture & Matching Pipeline
1. **Trigger:** A student interacts with the Telegram bot interface using the `/found` command OR registers a lost item report via the Web submission dashboard.
2. **Payload Collection:** - **Via Telegram (Finder):** Guides the student to take a photo of the item and share immediate GPS location data.
   - **Via Web Portal (Loser):** Collects item details, a geospatial map location pinpoint, and an optional placeholder image upload (e.g., a stock photo matching the model of a lost item).
3. **Parallel Fork Node:** Upon receiving either multi-part payload, the Laravel backend uses an asynchronous fork architecture to process tasks simultaneously, minimizing server latency:
   - **Path A:** Dispatches the image file (raw found evidence or lost reference placeholder) to the OpenCV / Vision API webhooks to generate descriptive metadata arrays (e.g., object type, primary colors) with localized confidence levels.
   - **Path B:** Dispatches raw GPS coordinate strings to the Google Maps Geocoding API for precise spatial logging.
4. **Join & Persist Node:** Once both external APIs return their payloads, the paths merge. The backend commits a new row to the `items` table and populates the child elements inside the `ai_tags` table.
5. **Matching Engine Execution:** The backend runs a rolling mathematical matching calculation checking the newly created record against active corresponding entries.
   - **If Confidence Score > 80%:** A webhook fires back out to the Telegram Bot API (`sendMessage`), pushing an instant match alert notification and image link directly to the loser's account.
   - **If Confidence Score < 80%:** The calculation terminates without notification, keeping the item logged as a `Pending` state in the inventory table for future cross-referencing.

---

## 4. Single Page Application (SPA) Frontend Architectures

### Student Web Portal (End-User View)
- **Authentication & Onboarding Gateway:** Split-screen layout handling session management. The registration module explicitly maps fields to the `USERS` schema, capturing the user's Telegram Handle at onboarding. A reactive Vue.js toggle allows clean transitions between Sign-In and Registration modes without full page reloads.
- **Found Gallery (Discovery Hub):** Desktop-optimized multi-column responsive grid showing found items retrieved from the VPS storage. Features a real-time search field connected to OpenCV tag metadata alongside navigation chips matching the backend `CATEGORIES` logic.
- **Report Lost Item (Submission Portal):** Handles the `Create` operational logic of the `ITEMS` module. Integrates an interactive file drop field for optional reference/stock item images alongside an interactive Google Maps API map component enabling students to drop a pinpoint on their last known location to establish precise coordinates for the matching engine.
- **My Reports & Alerts Dashboard:** Visual personal interface where active user files are tracked. Shows pulsating loading states for entries under active AI scanning. When an active match crosses system thresholds, a custom match card highlights the comparative data alongside a "Generate Claim OTP" trigger.
- **Account Settings Module:** Personal configuration interface interacting with user profiles. The student's Full Name and Profile Photo can be updated, but the Matric Number field is rendered as locked and strictly read-only to maintain database identity protection. Contains a toggle switch allowing users to register or revoke their Telegram subscription webhook dynamically.

### Security & Admin Dashboard (Staff View)
- **Global Inventory Audit Table:** Main operational view for campus security personnel. Displays all verified records sequentially, linking Entry IDs with visual picture cells, absolute geospatial coordinates, and lifecycle state tags. Rows contain an "Audit Details" action to pull up raw database records, including deep OpenCV array details before processing handovers.
- **Match Alerts Decision Support Layout:** Split side-by-side comparison field built to aid security guards. It pairs the student's text profile directly with the system's found record, showing the exact confidence score percentage and location comparison.
- **System Health & API Logs Monitor:** Technical dashboard dedicated strictly to the System Admin role to maintain platform architecture health. Logs real-time transactional webhooks, mapping HTTP response codes (e.g., 200 OK) and transaction speeds in milliseconds to simplify infrastructure troubleshooting and monitor uptime latency.