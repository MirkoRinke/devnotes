# 🚀 DevNotes – Full-Stack Development Documentation

**Status:** 🚧 **Active Development (Pre-MVP)** This project is a "Work in Progress" and has not yet reached Minimum Viable Product (MVP) status. I am currently using it to document my transition from a pure frontend developer to the full-stack domain, exploring new technical territories along the way.

![](https://github.com/MirkoRinke/devnotes/blob/main/preview.webp)
<p align="center">
  <small>Visualizing the Community Flow: From the entry point of the community hub to the final technical post view.</small>
</p>

---

### 💡 About this Project & My Learning Journey

After focusing heavily on frontend design, *DevNotes* marks my **first independent full-stack project**. It reflects my growth over the past few months:

* **Entry into System Logic:** Conceptualizing a custom REST API with Laravel and working with relational databases (MySQL).
* **Focus on Security & Structure:** Implementing security mechanisms like Rate-Limiting and API-Key validation to build robust applications.
* **Reusability in Angular:** Deepening modular patterns where components are used flexibly for different tasks through dynamic routing.

### 🛠️ Infrastructure & Transparency

To test the application under realistic conditions, I operate my own **local server environment**:

* **Partial Containerization:** The backend (API) and database are fully **Dockerized**, enabling a modern data management workflow. The frontend currently runs natively (`ng serve`) to maintain high iteration speeds during development.
* **Hardware Basis:** The services run on a dedicated **ThinClient**, functioning as a private development server.
* **Current Progress:** As this project has grown over a longer period, the code documents my personal learning curve. It is intended less as a finished product and more as a transparent look at my **current proficiency in systematic problem-solving**.

🎨 [**Frontend Repository (Angular)**](https://github.com/MirkoRinke/devnotes_frontend)

---

### 📘 Concept & Foundation

* **The Origin (v1.0):** My very first project – a personal knowledge base to understand HTML, CSS, and JavaScript.
* **Evolution (v2.0):** A complete rebuild as a modern full-stack hub to apply learned concepts in a professional structure.
* **Core Utility:** A "Second Brain" and exchange hub for developers.
* **Community & Privacy:** A hybrid model combining public exchange with private notes.
* **Tech Stack:** Angular Frontend, Laravel REST-API, MySQL, and Prism.js for syntax highlighting.

---

### ⚙️ Backend Logic & Security

* **Security Layers:**
    * **API Protection:** X-API-Key validation and Rate Limiting.
    * **Authentication:** Laravel Sanctum (Bearer-Token) coupled with a Device Fingerprinting system.
    * **Compliance:** Automated checks for Privacy Policy consent with integrated token management.
* **Features:**
    * **Interactions:** CRUD for posts, Like/Favorite system, and Follower logic.
    * **Moderation:** Critical term filters and Audit Logs for change tracking.
    * **Special Services:** GuestAccountService (e.g., for test access) and controlled post-read tracking.
* **Dynamic Filtering:** Use of a Custom Trait for complex database queries directly via URL parameters (including Dot-Notation for relations).

---

### 🎨 Frontend & Navigation Concept

* **Angular Services:**
    * **Central API Wrapper:** Unified endpoint handling and automated Auth-Header injection.
    * **SVG & i18n Service:** Centralized icon management and a custom pipe for language switching (DE/EN).
* **Guided Flow:**
    * Strategically leading the user from technology choice to content type and results list.
    * **Hybrid Search:** Intelligent distinction between title search and hashtag search (`#tag`) within a single input field.
    * **Circular Navigation:** "Back-jumps" preserve active filters to support the flow of "Deep Work."

---

### 📘 Detail View & Resource Protection

* **Post Management:**
    * **Versioning:** Access to previous versions of a post via a timestamp dropdown.
    * **Context Menu:** Dynamic options based on user roles (Edit/Delete for owners, Report for guests).
* **Content Presentation:**
    * **Interactive Boxes:** Separate areas for description and code with **Expand/Collapse** and **Copy-to-Clipboard** functionality.
* **Security Gate for Resources:**
    * External resources (images/videos) are only loaded after explicit consent to protect user privacy (**Privacy-by-Design**).
    * **Consent Modal:** Users can unlock content temporarily (1h, 12h, 24h) or permanently via User Settings.

---

### ✍️ Content Management (Editor)

* **In-Place-Editing (WIP):**
    * Editing occurs directly within the final layout (WYSIWYG) for immediate visual feedback.
    * **Smart Code-Block:** Switches to a `textarea` on focus and immediately re-renders syntax highlighting on blur.
* **Technical Synergy:**
    * **Reactive Forms:** The frontend structure mirrors the backend API body to ensure error-free data transfer without complex mapping.
    * **Validation:** Synchronized validation logic between Frontend (Angular) and Backend (Laravel).

---

### 🚀 Structure & Modular Logic

* **Universal Workflow:**
    * Through the consistent use of parameters (`context` and `endPoint`) in the URL, the same component can be used for entirely different sections.
    * **Example:** `posts-list?context=community&endPoint=POSTS` transforms into a personal favorites list or a contribution management view simply by changing parameters.
* **Roadmap (Outlook):**
    * Frontend integration of the existing backend comment section.
    * Expansion of user profiles and social interactions (Activity Feeds).
    * Finalizing media management within the editor.

---
