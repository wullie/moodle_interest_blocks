# Moodle "follow content" blocks

Two blocks that bring new course content to the user, so they don't have to go looking for it.

| Block | Where | What it does |
|---|---|---|
| `block_interestflag` — **Follow course content** | Course pages (incl. single-section pages) | Follow the whole course, or individual sections. |
| `block_interestfeed` — **New in what you follow** | Dashboard | Shows activities/resources added or updated in what the user follows, as tiles (like the Tiles format's activity sub tiles) or a list. |

Requires Moodle 5.0+. `block_interestfeed` depends on `block_interestflag`.

## Install

1. Copy `blocks/interestflag/` → `{moodleroot}/blocks/interestflag/`
2. Copy `blocks/interestfeed/` → `{moodleroot}/blocks/interestfeed/`
3. `php admin/cli/upgrade.php` (or Site administration → Notifications)
4. Configure: Site administration → Plugins → Blocks → New in what you follow
5. Put the blocks where users will see them:
   - **Dashboard:** Site administration → Appearance → Default Dashboard page → add *New in what you follow* → *Reset Dashboard for all users* (or let users add it themselves).
   - **Courses:** add *Follow course content* to each course. For new courses, list `interestflag` in
     `$CFG->defaultblocks_override` in `config.php` so it's added automatically. Existing courses need it
     added once, by hand or with a small CLI script.

The AMD `build/` files are hand-written equivalents of `src/` so the blocks work immediately. If you
change the JS, run `npx grunt amd` from the Moodle root to regenerate them properly.

## How it works

```
 teacher adds/edits a module           student opens Dashboard
          │                                     │
 core\event\course_module_created/updated       │
          ▼                                     ▼
 block_interestfeed_changes ──┐       feed_manager::get_feed()
   (cmid, timeadded,          ├──────►  changes ⨝ flags ⨝ seen
    timechanged, userid)      │         + modinfo visibility checks
 block_interestflag_flags ────┤
   (userid, courseid,         │
    sectionid | 0 = course)   │
 block_interestfeed_seen ─────┘
   (userid, cmid, timeseen)  ◄── core\event\course_module_viewed  /  "Dismiss"
```

An item appears on a user's feed when **all** of these hold:

- It was added (or, if *Include updated content* is on, last updated) within the look-back period.
- The user follows its course, or the section it sits in. Following a section also covers any
  Moodle 5 subsections inside it.
- Someone else made the change. You aren't shown your own edits.
- The change is newer than the last time the user opened or dismissed that module. An item that
  changes again after the user saw it returns, marked **Updated**.
- The user can currently see it on the course page. Enrolment, course/activity visibility, stealth
  and access restrictions are all respected through `can_access_course()` and `cm_info::uservisible`.
- Its type is not in *Ignored activity types* (default: Subsection, Question bank).

**New** vs **Updated**: *New* means the user has never opened it and it was created within the window.
Otherwise it's *Updated*.

### Design notes

- **Change log instead of scanning.** Observers write one row per module, so the Dashboard query is a
  single indexed join. It never walks every module in every course or reads the log store, so it works
  even with logging disabled.
- **Section resolved at display time.** The change log stores only the course. The module's current
  section comes from modinfo, so moving content between sections needs no bookkeeping.
- **Same template, server and client.** Each block renders its Mustache template in PHP. The web services
  (`block_interestflag_set_flag`, `block_interestfeed_dismiss`) return the same context and the JS
  re-renders that template, so the two can't drift apart.
- **Install backfill.** On install, modules created in the last 30 days are seeded into the change log,
  so the feed has something to show straight away.
- **Clean-up.** The scheduled task `block_interestfeed\task\cleanup` (daily, 03:xx) drops rows older than
  `max(30, 2 × look-back)` days. Observers tidy up when courses, sections, modules or users are deleted.
- **Privacy API.** Both blocks implement the Privacy API. Flags are reported in the course context and
  seen state in the module context. Change rows are anonymised rather than deleted, because they drive
  other users' feeds.

## Files

```
blocks/interestflag/
  block_interestflag.php            block class: course-page UI
  classes/flag_manager.php          flag storage + public API used by the feed
  classes/external/set_flag.php     AJAX web service
  classes/observer.php              cleanup on course/section/user deletion
  classes/privacy/provider.php
  templates/controls.mustache
  amd/src/flag.js
  db/{install.xml,access.php,services.php,events.php}

blocks/interestfeed/
  block_interestfeed.php            block class: Dashboard UI
  settings.php                      layout (tiles/list), look-back days, max items, include updated, ignored types
  styles.css                        tile layout
  classes/feed_manager.php          feed query + change/seen recording
  classes/external/dismiss.php      AJAX web service
  classes/observer.php              module created/updated/deleted/viewed, course/user deleted
  classes/task/cleanup.php
  classes/privacy/provider.php
  templates/feed{,_tiles,_list}.mustache
  amd/src/feed.js
  db/{install.xml,install.php,access.php,services.php,events.php,tasks.php}
```

## Test checklist

1. As a student, follow **Week 1** of Course A, and the whole of Course B.
2. As a teacher, add a Page to Week 1 and a File to Week 2 of Course A, and a Quiz anywhere in Course B.
   → Student's Dashboard shows the Page and the Quiz as **New**, but not the Week 2 File.
3. Student opens the Page → it disappears from the feed.
4. Teacher edits the Page → it comes back as **Updated**.
5. Teacher hides the Quiz, or adds an access restriction the student doesn't meet → it disappears.
6. Student clicks ✕ on an item, then **Dismiss all** → feed shows the empty-state message.
7. The teacher doesn't see their own additions on their own Dashboard.
8. Unenrol the student from Course B → its items disappear. The flag is kept and works again if they're re-enrolled.
9. Add a subsection inside Week 1 with an activity in it → it appears for the Week 1 follower.
10. Keyboard only: Tab to each Follow/Dismiss button and press Enter/Space. Focus stays sensible after each action.

## Known limitations

- Content restored from a backup or imported from another course doesn't fire `course_module_created`,
  so it won't appear until it is edited.
- If a module was created hidden and revealed much later, it appears only if the reveal fires
  `course_module_updated` (normal in Moodle 5) and *Include updated content* is on.
- Changes inside a module that don't fire `course_module_updated`, such as a new forum post or a file
  added to a folder via its own page, aren't tracked, by design.
