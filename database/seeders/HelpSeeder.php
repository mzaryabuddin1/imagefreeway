<?php

namespace Database\Seeders;

use App\Models\HelpEntry;
use Illuminate\Database\Seeder;

/**
 * The "How to Use" page. Add or edit an entry here whenever a feature is built or
 * changed, then run:  php artisan db:seed --class=HelpSeeder
 * (rows are matched by slug, so re-running only updates what changed).
 */
class HelpSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['getting-started', 'Basics', 'Signing in and finding your way around', 10, <<<'HTML'
<p>Open the site address you were given and sign in with your email and password. After sign-in you always land in your own organisation's branded workspace.</p>
<ul><li>The <strong>menu on the left</strong> shows only what your role may use.</li>
<li>The <strong>bell</strong> at the top shows new notifications. Click one to open the related page.</li>
<li>Pages change <strong>without reloading</strong>. Success and error messages appear as pop-ups.</li>
<li>Saving, archiving and deleting always ask you to confirm first.</li></ul>
HTML],
            ['roles', 'Basics', 'Roles: who can do what', 20, <<<'HTML'
<ul><li><strong>Super Admin</strong> (platform owner): creates and suspends clients, switches modules on or off, sees all clients.</li>
<li><strong>Client Admin</strong>: manages the users and branding of their own client only.</li>
<li><strong>User</strong>: works with documents according to the rights an admin gives them (document features arrive in the next phase).</li></ul>
<p>A user can never see another client's data, whatever address they type.</p>
HTML],
            ['clients', 'Super Admin', 'Create and manage a client', 30, <<<'HTML'
<ol><li>Open <strong>Clients</strong> then <strong>New client</strong>.</li>
<li>Enter the name, a short slug, and optionally the client's own domain (for example <code>docs.clientcompany.com</code>).</li>
<li>Set the brand colours, logo and footer, and tick the add-on modules the client bought.</li>
<li>Fill in the first Client Admin, then press <strong>Create client</strong>.</li></ol>
<p>Use the row buttons to <strong>suspend</strong> (temporary block), <strong>archive</strong> (retire) or <strong>delete</strong> a client. Each asks for confirmation and is written to the audit log.</p>
HTML],
            ['white-label', 'Super Admin', 'White-label branding and client domains', 40, <<<'HTML'
<p>Every client has its own logo, colours, display name and footer. The whole screen re-colours instantly after saving.</p>
<p>To serve a client from its own domain: add the domain on the client form, then point that domain's DNS (a CNAME) at the platform and add the domain to the web server. Anyone who opens that domain sees only that client's login page and brand, and only that client's users can sign in there.</p>
HTML],
            ['users', 'Administration', 'Add, edit, archive and delete users', 50, <<<'HTML'
<ol><li>Open <strong>Users</strong> then <strong>New user</strong>.</li>
<li>Enter name, email, password, role and status.</li>
<li>Press <strong>Create user</strong>. The person gets a welcome notification.</li></ol>
<p><strong>Archive</strong> keeps a person's history but blocks sign-in; you can restore them later. <strong>Delete</strong> removes them from the list. You cannot archive or delete yourself.</p>
HTML],
            ['branding', 'Administration', 'Customise your own branding (Client Admin)', 60, <<<'HTML'
<p>Open <strong>Branding</strong> in the menu. Change the display name, footer text, primary colour (buttons and active menu), secondary colour (sidebar) and logo, then save. Everyone in your organisation sees the change straight away.</p>
HTML],
            ['notifications', 'Administration', 'Notifications', 70, <<<'HTML'
<p>The bell shows how many notifications are unread and refreshes by itself every 30 seconds. Open <strong>Notifications</strong> in the menu for the full list, or use <strong>Mark all read</strong>. Today you are notified when you are welcomed, when a client is created or changes status, and when a user is added to your client.</p>
HTML],
            ['audit-log', 'Administration', 'Audit log', 80, <<<'HTML'
<p>The audit log records sign-ins and every change to clients, users and branding, with who did it, when and from which address. Changes to existing records show a <strong>Before / after</strong> view. Entries cannot be edited. Client Admins see only their own client's entries.</p>
HTML],
            ['storage', 'Technical', 'File storage: local or Amazon S3', 90, <<<'HTML'
<p>Uploaded files (logos now, documents next) are stored on the disk chosen in the <code>.env</code> file:</p>
<ul><li><code>DOCUMENT_STORAGE=local</code> keeps files in <code>storage/app/documents</code> (development).</li>
<li><code>DOCUMENT_STORAGE=s3</code> uses Amazon S3. Also set <code>AWS_ACCESS_KEY_ID</code>, <code>AWS_SECRET_ACCESS_KEY</code>, <code>AWS_DEFAULT_REGION</code> and <code>AWS_BUCKET</code>.</li></ul>
<p>No code change is needed to switch. Run <code>php artisan config:clear</code> after editing <code>.env</code>.</p>
HTML],

            ['structure', 'Documents', 'Set up departments and projects (Client Admin)', 100, <<<'HTML'
<p>Documents live in <strong>projects</strong>, and projects belong to <strong>departments</strong> (for example Finance → Accounts Payable).</p>
<ol><li>Open <strong>Departments &amp; Projects</strong> in the menu.</li>
<li>Type a department name and press <strong>Add department</strong>.</li>
<li>Inside the department, type a project name and press <strong>Add project</strong>. You are taken to the project's settings.</li></ol>
<p>Rename a department by editing its name and pressing the tick. Only empty departments and projects without documents can be deleted.</p>
HTML],
            ['index-fields', 'Documents', 'Index fields: what is captured for each document', 110, <<<'HTML'
<p>Index fields are the values you want to find documents by, such as Invoice Number, Vendor or Amount. They are defined <strong>per project</strong>.</p>
<ol><li>Open the project's <strong>Fields &amp; access</strong> page.</li>
<li>Under <em>Add an index field</em> enter a name and choose a type: Text, Whole number, Amount, Date, Yes / No or Pick-list.</li>
<li>For a pick-list, type the permitted values, one per line. This keeps data accurate.</li>
<li>Tick <strong>Required</strong>, <strong>Searchable</strong>, <strong>Show in results</strong> or <strong>Read-only</strong> as needed, then <strong>Add field</strong>.</li></ol>
<p>Use the up and down arrows to change the order at any time. <strong>Hiding</strong> a field keeps every stored value, and you can restore it from <em>Hidden fields</em>.</p>
HTML],
            ['access', 'Documents', 'Give people access to a project', 120, <<<'HTML'
<p>On the project's <strong>Fields &amp; access</strong> page, tick what each user may do: <strong>View, Download, Upload, Index, Delete</strong>. A user who has no ticks cannot see the project at all. Client Admins always have full access in their own client.</p>
HTML],
            ['upload', 'Documents', 'Upload documents', 130, <<<'HTML'
<ol><li>Open <strong>Documents</strong> and choose a project.</li>
<li>Drag files into the dashed box, or press <strong>choose files</strong>. Many files at once are fine.</li>
<li>Each file shows its own progress bar. If one fails, the others still upload.</li></ol>
<p>Allowed: PDF, Word, Excel, PowerPoint, text, CSV and images, up to 100 MB each. New documents wait in the <strong>unindexed</strong> queue until their index values are entered. Text inside PDFs and images is read automatically so you can search it.</p>
HTML],
            ['indexing', 'Documents', 'Index a document (enter its values)', 140, <<<'HTML'
<ol><li>On the dashboard, under <em>Unindexed documents</em>, click a project, or press <strong>Index … waiting</strong> inside a project.</li>
<li>The document opens beside its index form. Fill in the values; fields marked * are required.</li>
<li>Press <strong>Save &amp; next unindexed</strong> to move straight to the next waiting document.</li></ol>
<p>You can also change index values later from the same screen. Every change is recorded in the audit log with the before and after values.</p>
HTML],
            ['search', 'Documents', 'Search: inside a project and across everything', 150, <<<'HTML'
<ul><li><strong>Inside a project</strong>: open it and fill any of the boxes. Dates and numbers take a From and To. Click a column heading to sort. A search with empty boxes lists every document.</li>
<li><strong>Everywhere</strong>: use the search box at the top. It looks in file names, index values and the words inside documents, but only in projects you may open.</li></ul>
HTML],
            ['viewer', 'Documents', 'View, download and delete a document', 160, <<<'HTML'
<p>Open a document to see it next to its index values. PDFs and images preview in the page; other file types can be downloaded. Use <strong>Previous</strong> and <strong>Next</strong> to move through the project.</p>
<p><strong>Delete</strong> moves a document to the <strong>Recycle Bin</strong>, where it shows who deleted it and when. Press <strong>Restore</strong> to bring it back with its index values. Viewing, downloading, indexing and deleting are all written to the audit log.</p>
HTML],
            ['ocr-setup', 'Technical', 'Text recognition (OCR) setup on the server', 170, <<<'HTML'
<p>Digital PDFs, Word (.docx) and text files are read with no extra software (PDFs need <code>pdftotext</code> from Poppler). Scanned pages and images also need <strong>Tesseract</strong>.</p>
<ul><li>Install Tesseract and Poppler on the server. If they are not on the PATH, set <code>OCR_TESSERACT</code>, <code>OCR_PDFTOTEXT</code> and <code>OCR_PDFTOPPM</code> in <code>.env</code> to their full paths.</li>
<li>Until an engine is installed, a document shows <em>Engine not installed</em> under Text search. Nothing else is affected.</li>
<li>Turn OCR off for a single project on its settings page.</li></ul>
HTML],
            ['workflows', 'Workflow', 'Draw a workflow on the canvas (Client Admin)', 180, <<<'HTML'
<p>Open <strong>Workflow</strong> then <strong>Manage workflows</strong>, name the workflow, pick its project and press <strong>Create</strong>. The builder opens as a canvas, like a flowchart.</p>
<ul><li><strong>Add a step</strong> from the list on the left, or drag from a step's round <em>exit</em> and drop on empty space to add the next step right there.</li>
<li><strong>Connect</strong> by dragging from an exit to another step. Each exit leads to one step. Click a connection and press Delete (or the red ×) to remove it.</li>
<li><strong>Click a step</strong> to edit it in the panel on the right. <strong>Tidy</strong> arranges the layout; the magnifier buttons zoom; Ctrl + scroll also zooms.</li>
<li>The <strong>“to check”</strong> button lists exits that are not connected and tasks with nobody assigned.</li>
<li>Press <strong>Save</strong>. New documents follow the saved version; documents already in progress carry on from where they are.</li></ul>
<h3>The steps</h3>
<ul><li><strong>Task</strong>: a person chooses one of your options (for example Tony, Dana, Cancel). Each option is its own exit. Set who acts, an overdue time with who to tell, a flag, and requirements.</li>
<li><strong>Send email</strong>: to fixed addresses, to the person who emailed the document in, or to an address picked by a field (for example one address per Installer Type). Use {document}, {link} and {any_index_field} in the text. Tick “secure link” to send a link instead of the file.</li>
<li><strong>If / else</strong>: routes by index values. The first rule that is true wins; “Otherwise” catches the rest.</li>
<li><strong>Set index</strong>, <strong>Duplicate check</strong>, <strong>Request signature</strong> (waits for the signer), <strong>Run in parallel</strong>, <strong>Send to workflow</strong> (hand over to another workflow) and <strong>Finish</strong>.</li></ul>
<h3>Requirements on a task</h3>
<p>Block an option until a field is filled in, a document type is on file for the same job (for example “Initial QB Receipt”), or a form is completed. Leave the “for” boxes empty to apply to every option.</p>
<p>The <strong>Flag</strong> sets an index field to Yes while a document is in the task and clears it when it leaves. A weekly management report with aging is emailed to Client Admins every Friday.</p>
HTML],
            ['workflow-inbox', 'Workflow', 'Answer tasks and use the workflow inbox', 190, <<<'HTML'
<p><strong>Workflow</strong> in the menu (the number shows how many are waiting for you) lists documents at a task you are responsible for. Press <strong>Start working</strong> to open them one after another.</p>
<ul><li>Open a document and use the <strong>Workflow</strong> panel: press the option that fits. Tick “then open my next item” to carry straight on.</li>
<li>If an option needs a form, open it, fill it in and submit. If something is missing (a field, a document, a form) a pop-up tells you exactly what.</li>
<li>Late tasks remind you, then the named manager or the previous person, then the Client Admins.</li>
<li>Every step is kept in the document's <em>History</em>. To start a workflow by hand, use the same panel.</li></ul>
HTML],
            ['wdg-workflows', 'Workflow', 'Ready-made WDG workflows', 195, <<<'HTML'
<p>Four processes from the WDG flowcharts are installed as workflows in the <strong>WDG Jobs</strong> project: <strong>Invoice</strong>, <strong>Warranty</strong>, <strong>Collections</strong> and <strong>Contract</strong>. Open any of them in the builder to change names, people, options or connections.</p>
<ul><li>All documents of one job are linked by <strong>Contract Number</strong>; a document shows the others under “Same Contract Number”.</li>
<li>People used in the flowcharts exist as users (Tony, Dana, Cindy, Mychael, Tanya, Stephanie, Ruth, Mike). Replace the assignees as needed.</li>
<li>The installer email addresses in the email steps are placeholders. Replace them with the real ones.</li>
<li>To install them in another client: <code>php artisan wdg:install client-slug</code>.</li></ul>
HTML],
            ['emailed-docs', 'Workflow', 'Documents emailed in (document types)', 196, <<<'HTML'
<p>Open <strong>Document types</strong>. Each type can have its own address, such as <code>wdg.invoice-tbd@your-domain</code>. When a message arrives:</p>
<ol><li>The attachments are filed in the type's project and labelled with the type; the sender is recorded.</li>
<li>The contract number is read from the subject and stored in the index field you choose. If none is found, the sender gets an email saying the document was not filed, and the Client Admins are told.</li>
<li>The document is linked to the others with the same number, and the type's workflow starts (if you chose one).</li></ol>
<p>Technical: your mail provider (Mailgun, SendGrid, Postmark...) must forward each message to <code>POST /inbound/{address}</code> with <code>from</code>, <code>subject</code> and <code>attachments[]</code> and the header <code>X-Inbound-Secret</code>, which must equal <code>INBOUND_EMAIL_SECRET</code> in <code>.env</code>. Set <code>INBOUND_EMAIL_DOMAIN</code> to the domain shown on the page.</p>
HTML],
            ['eforms', 'Documents', 'eForms', 197, <<<'HTML'
<p>Open <strong>eForms</strong>, create a form and add questions: short or long text, number, date, tick box, pick-list or <strong>signature</strong>. Press Save.</p>
<ul><li>Attach a form to a workflow task as a <em>Requirement</em> (“Form completed”): the person fills it in while choosing the option.</li>
<li>Or open any document and use <em>Forms → Fill in</em>. Answers, including drawn signatures, are kept on the document.</li></ul>
HTML],
            ['esign', 'Documents', 'eSignature', 198, <<<'HTML'
<ol><li>Open a document, find <strong>eSignature</strong>, enter the signer's name and email, and press <strong>Request signature</strong>. The signer is emailed a link; you can also copy it.</li>
<li>The signer reads the document, types their name, draws a signature, ticks the consent box and presses Sign. No account is needed. They may also decline.</li>
<li>You are notified. Press <strong>Certificate</strong> for a printable record with the time, address and the document's SHA-256 fingerprint.</li></ol>
<p>A workflow can ask for a signature too: add a <em>Request signature</em> step. It waits, then continues by the <strong>Signed</strong> or <strong>Declined</strong> exit.</p>
HTML],
            ['retention', 'Compliance', 'Retention, legal hold and destruction certificates (Client Admin)', 200, <<<'HTML'
<p>Open <strong>Retention</strong>. For each project choose how many years to keep documents and whether to count from the upload date or from a date field. Leave it empty to keep documents forever.</p>
<ul><li>Documents that reach their disposal date appear in the <strong>Review queue</strong>. Tick them, then <strong>Destroy</strong>, <strong>Defer 90 days</strong> or <strong>Legal hold</strong>.</li>
<li>Destroying removes the file for good and creates a <strong>certificate</strong> you can print or save as PDF.</li>
<li>A document under <strong>legal hold</strong> cannot be deleted or destroyed by anyone until the hold is released. Place or release a hold from the document page or from the Retention page.</li></ul>
HTML],
            ['sharing', 'Documents', 'Share a document with someone outside (secure link)', 210, <<<'HTML'
<ol><li>Open a document and find <strong>Share securely</strong>.</li>
<li>Choose how many days the link works, an optional access code, and whether the recipient may download.</li>
<li>Press <strong>Create link</strong>, then <strong>Copy link</strong> and send it. Give the code separately.</li></ol>
<p>The recipient sees only the file, in your branding. Index values are never shown. The list shows how often a link was opened or downloaded; press the cross to <strong>revoke</strong> it at once. Expired or revoked links show a "no longer available" page.</p>
HTML],
            ['reports', 'Documents', 'Reports and CSV export', 220, <<<'HTML'
<p>Open <strong>Reports</strong>, pick a project, and choose how to group (status, month added, or a pick-list field). Optionally <em>add up</em> an amount or number field and limit by date. Press <strong>Run report</strong> for bars, and <strong>Export CSV</strong> to open it in Excel. The page also shows how many documents are at each workflow stage.</p>
HTML],
            ['versions', 'Documents', 'Document versions', 215, <<<'HTML'
<p>On a document page, the <strong>Versions</strong> card lets you upload a corrected file. The new file becomes the current one and the old one is kept in the list. <strong>Download</strong> opens an old version; <strong>Restore</strong> makes it current again (the file that was current moves into the history, so nothing is lost).</p>
<p>You need upload rights to add or restore a version. A document on legal hold cannot be changed. Index values stay the same across versions; OCR runs again on the new file.</p>
HTML],
            ['calculated-fields', 'Projects', 'Calculated fields and auto-naming', 216, <<<'HTML'
<p><strong>Calculated field:</strong> in project settings, add a field of type <em>Calculated</em> and write a formula using other number or amount fields by their key, for example <code>quantity * unit_price</code>. You can use <code>+ - * /</code>, brackets and numbers. The value is worked out when index values are saved and cannot be typed in. If a value is missing or you divide by zero, the result stays empty.</p>
<p><strong>Auto-naming:</strong> in project settings, set a naming pattern such as <code>{contract_number}-{invoice_number}</code>. After indexing, the document is renamed to match (the file type is kept). You can also use <code>{doc_type}</code>, <code>{today}</code> and <code>{id}</code>. If any part is empty the name is left as it is.</p>
HTML],
            ['walkthrough', 'Getting started', 'The flag walkthrough', 214, <<<'HTML'
<p>The round <strong>flag</strong> at the bottom-right of every page is a guided tour of the whole system. The number on it is how many steps are left.</p>
<p>Click it to open a card that explains the current page. <em>Take me there</em> opens the page, and <em>Next</em> moves to the next page and explains it. The matching menu item (or button, such as <em>Mark up</em>) is outlined in orange so you know where to look. <em>All steps</em> lets you jump to any step.</p>
<p>Inside the flag, <strong>How do I…</strong> guides walk you through one job step by step on the real pages: sending a document for signing, sending one letter to many people, seeing who opened a document and setting reminders, starting a workflow, building a workflow and giving people access.</p>
<p>The tour only lists pages your role can open, and your progress is remembered in your browser. Close the card with × and reopen it any time from the flag.</p>
HTML],
            ['explorer', 'Documents', 'Browsing documents like File Explorer', 216, <<<'HTML'
<p>The <strong>Documents</strong> page shows folders and files as icons, like File Explorer. The first screen shows your <strong>departments</strong> as folders. Click one to see the <strong>project</strong> folders inside it, then click a project to see its files.</p>
<p>Each file has an icon for its type: red <em>PDF</em>, blue <em>DOC</em> (Word), green <em>XLS</em> (Excel), orange <em>PPT</em>, purple <em>JPG/PNG</em> and so on. An amber dot or number means files are waiting to be indexed. Click a file to open it.</p>
<p>Use the path at the top (All folders › Department › Project) or the back arrow to go up. <em>Upload &amp; search</em> opens the full project page (upload, filters, search); <em>Gallery</em> (Client Admins) shows picture cards.</p>
HTML],
            ['sign-boxes', 'Documents', 'Signature boxes and several signers', 217, <<<'HTML'
<p><strong>1. Draw the boxes.</strong> Open a PDF or image, press <em>Mark up</em> and choose <em>Sign here</em>. Drag a box where a signature is needed and type <strong>who signs there</strong> — a role such as <em>Candidate</em>, <em>HR Manager</em> or <em>CEO</em>. Draw as many boxes as you need, on any page. Boxes with the same role belong to the same person, and every role has its own colour.</p>
<p><strong>2. Add the signers.</strong> In the <em>eSignature</em> card add one row per signer: the same role, then either pick a team member (name and email fill in) or type a name and email for an outsider. Tick <em>Sign in this order</em> so each person is emailed only after the one before has signed. Press <em>Send for signature</em>.</p>
<p><strong>3. How people sign.</strong> They open the email link (no account needed), see the pages with their box, and sign by <strong>drawing</strong>, <strong>typing</strong> their name, <strong>uploading</strong> a picture of their signature or taking a <strong>camera</strong> photo. It appears in their box straight away.</p>
<p>After each signature the document file is updated and the earlier file is kept under <em>Versions</em>, so the next signer sees the signatures already there. The certificate and SHA-256 fingerprint are recorded for every signer. If someone declines, the people still waiting are cancelled.</p>
<p>PDF pages need the poppler tools on the server; images work without them. Note: a signed PDF is rebuilt from page pictures, so its text is read again by OCR.</p>
HTML],
            ['bulk-signing', 'Documents', 'Send one letter to many people', 217, <<<'HTML'
<p>For something like an offer letter to 10 candidates: draw the boxes once (for example <em>Candidate</em>, <em>HR Manager</em>, <em>CEO</em>), then in the eSignature card choose <strong>A separate copy for many people</strong>.</p>
<p>Type the candidates' role and paste one person per line as <code>Name, email</code> (up to 50). Under <em>Other signers</em> add the people who are the same for everyone, such as the HR Manager and the CEO.</p>
<p>Every candidate gets <strong>their own copy</strong> of the letter (named after them, in the same project). Each copy runs in order: the candidate signs first, then HR, then the CEO. Open any copy to see its own signing progress and timeline.</p>
HTML],
            ['timeline-reminders', 'Documents', 'Timeline, who opened it and reminders', 217, <<<'HTML'
<p>The <strong>Timeline</strong> card on a document lists everything that happened, newest first: uploads, new versions, mark-up, signatures requested, opened, signed or declined, reminders, and workflow decisions. Tick <em>Show every time someone opened it</em> to see each visit.</p>
<p><strong>Opened by</strong> shows every user who opened the document, how many times and when last. For signers, the <em>Signing progress</em> list shows when each one was emailed, when they first opened the link (or <em>Not opened yet</em>), how many visits, and when they signed.</p>
<p><strong>Reminders.</strong> Set <em>Remind every (days)</em> when you send for signature (default 2). Anyone whose turn it is and who has not signed gets an email automatically, up to 3 times; after the third you get a notification. Press <em>Remind now</em> for an immediate nudge. Automatic reminders need the Laravel scheduler (<code>php artisan schedule:run</code> every minute); you can also run <code>php artisan signatures:remind</code> by hand.</p>
HTML],
            ['workflow-signing', 'Workflows', 'Workflow: employee signs, then me, then HR manager', 217, <<<'HTML'
<p>A workflow can wait for a signature. Use a <strong>Signature</strong> step: give the signer's email (or pick an index field that holds it, such as <em>Candidate Email</em>) and the <em>role</em> whose boxes they sign. It has two exits, <em>Signed</em> and <em>Declined</em>. Then connect <strong>Task</strong> steps for the people who decide next — each with its own assignees, buttons and overdue time.</p>
<p>Example (already created for you as <em>Offer Letter Signing</em> in the Human Resources project): Start → Candidate signs → Me: check the signed letter → HR Manager approval → Offer completed. Declined and Reject lead to their own endings. To use it: fill the document's <em>Candidate Email</em>, draw a <em>Candidate</em> signing box with Mark up, then press <em>Start</em> in the document's Workflow card. The candidate is emailed; when they sign, the task appears in your Workflow inbox; when you approve it goes to the HR manager.</p>
<p>Change the people in each Task step to your real users (the example assigns the tasks to you, because the account has one user).</p>
HTML],
            ['markup', 'Documents', 'Notes, highlights and redaction', 217, <<<'HTML'
<p>Open a PDF or image and press <strong>Mark up</strong>. Pick a tool, then drag a box on the page:</p>
<ul><li><strong>Note</strong> — write a comment. Hover over the box to read it.</li>
<li><strong>Highlight</strong> — colour an area so others notice it.</li>
<li><strong>Redact</strong> — mark anything that must be hidden, such as a bank number. Needs upload rights.</li></ul>
<p>Use the arrows to move between pages. Hover over a mark and press <strong>×</strong> to remove it (you can remove your own marks; admins can remove any).</p>
<p><strong>Redaction is permanent only in the copy.</strong> Press <em>Create redacted copy</em> and a new document is made with the marked areas blacked out. For PDFs the pages are rebuilt as pictures, so the hidden text cannot be copied out. The original stays untouched — restrict access to it if it must not be seen.</p>
<p>PDF pages need the poppler tools on the server (the same ones OCR uses). Images work without them.</p>
HTML],
            ['quarantine', 'Administration', 'Virus scanning and quarantine', 218, <<<'HTML'
<p>Every file that comes in — an upload, a new version, or an email attachment — is scanned for viruses first. A file that fails is <strong>not filed</strong>. It is kept apart in <strong>Quarantine</strong> (sidebar, with a red number when something is waiting) and the client admins are notified. The person who uploaded it sees a message; for email, the sender is told the file was not accepted.</p>
<p>On the Quarantine page an admin can <strong>Release</strong> a file (it is filed in its project anyway, after you confirm) or <strong>Destroy</strong> it (deleted for good). Both are recorded in the audit log.</p>
<p>Scanning uses ClamAV when it is installed on the server (set <code>SCAN_CLAMSCAN</code> in <code>.env</code> to its path). Without it, only the standard test virus (EICAR) is caught. Turn scanning off with <code>SCAN_ENABLED=false</code>.</p>
HTML],
            ['support-console', 'Administration', 'Support console (Super Admin)', 219, <<<'HTML'
<p>Open <strong>Support console</strong> to see every client at a glance: users, documents, storage, last login, last upload and a health badge. Health flags failed OCR, overdue workflow items and files in quarantine. <strong>Export usage CSV</strong> downloads the table.</p>
<p>Click a client to see its numbers, the documents whose OCR failed, and its latest audit entries. This view is read-only.</p>
HTML],
            ['scheduled-reports', 'Documents', 'Scheduled reports by email', 221, <<<'HTML'
<p>On the <strong>Reports</strong> page (Client Admins), scroll to <strong>Scheduled reports</strong>. Choose a name, how often (every day, every Monday, or the 1st of the month), how to group and what to add up, and who receives it. The project is the one shown on the page.</p>
<p>The email uses your branding colours and name and has the report as bars, plus the workflow state, with a CSV attached for Excel. Use <strong>Send now</strong> to try it, <strong>Pause</strong> to stop it for a while, or <strong>Delete</strong>. Reports go out at 07:00 on the server.</p>
<p>The server must run the Laravel scheduler (<code>php artisan schedule:run</code> every minute) and have real mail settings in <code>.env</code>.</p>
HTML],
            ['client-export', 'Administration', 'Export all of a client\'s data (Super Admin)', 222, <<<'HTML'
<p>Open <strong>Support console</strong>, click a client, then <strong>Export all data (zip)</strong>. The zip holds the original files grouped by department and project, <code>index-values.csv</code>, the full <code>audit-trail.csv</code> and <code>structure.json</code> (departments, projects, fields, document types, workflows). Each export is written to that client's audit trail.</p>
<p>Very large clients can take a while; the file downloads when it is ready.</p>
HTML],
            ['support-view', 'Administration', 'See the product as a user (Super Admin)', 223, <<<'HTML'
<p>In <strong>Support console</strong>, open a client and press <strong>View as</strong> next to a user. You then see exactly what that person sees, under a yellow bar. It is <strong>read-only</strong>: any attempt to save or delete is refused.</p>
<p>If you must change something, press <strong>Enable changes (30 min)</strong> in the yellow bar and confirm. Changes made then are marked <em>[Support: your name]</em> in the client's audit trail. The whole view ends after 60 minutes, or when you press <strong>Stop viewing</strong>. Starting, enabling and stopping are always written to the client's audit trail, so clients can see who looked at their data.</p>
HTML],
            ['gallery', 'Documents', 'Gallery view (Client Admin)', 224, <<<'HTML'
<p>On a project's page, press <strong>View as gallery</strong>. Every document appears as a card with a picture of its first page (PDF and images) and below it an icon for the file type (PDF red, Word blue, Excel green, PowerPoint orange, image purple), the name, size, date and status. Click a card to open the document.</p>
<p>When a document has older versions, a small <strong>stack icon with a number</strong> appears on its card. Hover it to see the current file and each older version; click a version to download it. The same search filters as the list apply. Press <strong>View as list</strong> to go back.</p>
HTML],
            ['blueprints', 'Administration', 'Setup blueprints (Super Admin)', 225, <<<'HTML'
<p>A blueprint is a client's setup saved for reuse: departments, projects, index fields (with formulas and naming patterns), workflows, eForms, document types and add-on modules. It never contains people, documents or access rights.</p>
<p><strong>Save one:</strong> Support console → open the client → <em>Save this client's setup as a blueprint</em>. Using a name that already exists saves the next version (v2, v3…); older versions stay available.</p>
<p><strong>Use one:</strong> <em>Blueprints</em> in the sidebar shows what each contains. Press <em>New client from this</em>, or pick a blueprint on the New client form. A preview line lists what will be created.</p>
<p>The client is created <strong>all or nothing</strong>: if anything fails, no client, user or setup is left behind and you get a clear message. Workflow tasks start with nobody assigned (Client Admins receive them until people are assigned), and email-in addresses are left empty because each must be unique.</p>
HTML],
            ['dashboard', 'Basics', 'The dashboard and its charts', 12, <<<'HTML'
<p>The dashboard opens with counts (documents, waiting to be indexed, your workflow inbox, overdue items, storage, users) and charts you can read at a glance. Hover a bar or slice to see its exact number. Charts only count documents in projects you may view.</p>
<ul><li><strong>Documents added, last 30 days</strong> — one bar per day.</li>
<li><strong>Indexing progress</strong> — how many documents have index values.</li>
<li><strong>By project / by type</strong> — where your documents are.</li>
<li><strong>Where work is waiting</strong> — workflow items at each step; <strong>How finished work ended</strong> — the results (for example Paid or Archive).</li>
<li><strong>Amount by month</strong> — the total of the first amount field, if your project has one.</li></ul>
<p>Super Admins see clients by status and documents and users per client instead. Charts use your brand colour.</p>
<p>For demos, <code>php artisan demo:fill</code> adds about 60 sample documents and <code>php artisan demo:fill --clear</code> removes them.</p>
HTML],
            ['search-as-you-type', 'Documents', 'Search as you type', 213, <<<'HTML'
<p>Start typing in the search box at the top (two letters or more). Suggestions appear under it straight away: matching <strong>projects</strong> and <strong>documents</strong>, with your words highlighted. For documents that match inside their text (OCR) or index values, a short snippet shows where.</p>
<p>Use the <strong>↑ ↓</strong> keys to move, <strong>Enter</strong> to open the highlighted one, or click it. Choose <em>See all … results</em> (or press Enter with nothing highlighted) for the full list. <strong>Esc</strong> closes the suggestions. You only ever see documents in projects you may view.</p>
HTML],
            ['whats-new', 'Release notes', 'What is new (22 Nov 2026)', 5, <<<'HTML'
<p><strong>Search as you type:</strong> suggestions appear under the search box while you write.</p>
<p><strong>Dashboard charts:</strong> the dashboard now shows documents added per day, indexing progress, documents by project and type, where workflow work is waiting, how finished work ended, and amounts by month. Super Admins see clients, documents and users per client.</p>
<p><strong>Phase 10 — setup blueprints:</strong> save a client's whole setup as a named, versioned blueprint and create new clients from it in one step, all or nothing.</p>
<p><strong>Phase 9 — support view and gallery:</strong> Super Admins can see the product as a client's user (read-only, with time-limited changes, all recorded in the client's audit trail), and Client Admins can browse a project as a gallery of picture cards with a hover stack of versions.</p>
<p><strong>Phase 8 — scheduled reports and data export:</strong> email any standard report daily, weekly or monthly with your branding and a CSV attached; Super Admins can export all of a client's data as a zip.</p>
<p><strong>Phase 7 — safety and support:</strong> every upload, new version and emailed attachment is virus-scanned; infected files go to Quarantine instead of being filed. Super Admins get a Support console with usage and health for every client.</p>
<p><strong>Phase 6 — mark-up and redaction:</strong> add notes, highlights and redaction boxes on PDF and image pages, and create a redacted copy with the marked areas blacked out for good.</p>
<p><strong>Phase 5 — versions, calculated fields and auto-naming:</strong> upload a new version of a document and keep the old files, restore an earlier one, calculated index fields (for example quantity × unit price), and automatic document names from index values.</p>
<p><strong>Phase 4 — visual workflows, eForms and eSignature:</strong> a drag-and-connect workflow canvas (tasks with their own options, email, if/else, duplicate check, signature, parallel and hand-over steps), requirements on tasks, document types with email-in, document linking by contract number, eForms, eSignature, a weekly flagged-items report, and the four WDG workflows ready-made.</p>
<p><strong>Phase 3 — workflow and compliance:</strong> approval workflows with conditional stages and an inbox, overdue escalation, retention schedules with legal hold and destruction certificates, secure sharing links, and standard reports with CSV export.</p>
<p><strong>Phase 2 — documents:</strong> departments and projects, index fields, access rights, bulk upload, indexing queue, viewer, project and global search, OCR and the recycle bin.</p>
<p><strong>Phase 1 — foundation:</strong></p>
<ul><li>Sign-in, roles (Super Admin, Client Admin, User) and per-client data isolation.</li>
<li>Clients with white-label branding, custom domain and add-on module switches.</li>
<li>User management with archive and delete confirmations.</li>
<li>In-app notifications, audit log and this How to Use guide.</li>
<li>Local or S3 file storage chosen from <code>.env</code>.</li></ul>
<p><strong>Signing, more complete:</strong> several signers with roles and signing order, a separate copy per recipient for many people, sign by drawing, typing, uploading or camera, a document timeline with who opened it, and automatic reminders.</p>
<p>Coming next: barcode splitting, a getting-started checklist and camera capture.</p>
HTML],
        ];

        foreach ($entries as [$slug, $section, $title, $sort, $body]) {
            HelpEntry::updateOrCreate(['slug' => $slug], compact('section', 'title', 'sort', 'body'));
        }
    }
}
