<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;

/** The steps of the flag walkthrough. Built per person, so only pages they can open are listed. */
class Tour
{
    /** @return array<int, array{id:string,title:string,text:string,path:?string,target:?string}> */
    public static function steps(User $u): array
    {
        $p = fn (string $route, array $args = []) => parse_url(route($route, $args), PHP_URL_PATH);
        $s = [];
        $add = function (string $id, string $title, string $text, ?string $path, ?string $target = null) use (&$s) {
            $s[] = compact('id', 'title', 'text', 'path', 'target');
        };

        $add('dashboard', 'Dashboard', 'Your home page: charts of what came in, what is waiting to be indexed and what is stuck in a workflow. Hover over any chart for the exact numbers.', $p('dashboard'));

        if ($u->isSuperAdmin()) {
            $add('clients', 'Clients', 'Every customer company lives here. Open one to set its logo, brand colours, domain, add-on modules and storage. Each client only ever sees its own data.', $p('clients.index'));
            $add('blueprints', 'Blueprints', 'A blueprint is a saved setup (departments, projects, fields, workflows). Pick one when you create a client and the whole setup is built for you.', $p('blueprints'));
            $add('support', 'Support console', 'Health of every client at a glance. Open a client to look around as one of its users ("View as") when you need to help.', $p('support'));
        }

        if ($u->client_id) {
            $add('documents', 'Documents: folders and files', 'Departments are folders; the projects inside them are folders too. Click one to open it, and use the path at the top or the arrow to go back. Files show their type: PDF, Word, Excel, JPG…', $p('documents'));

            $project = $u->projectsWith('can_view')->orderBy('name')->first();
            if ($project) {
                $add('project', 'A project: upload and search', 'Drop files here to upload. Use the filter box to search by index values, and "Index waiting" to fill in the details of new files.', $p('projects.show', [$project]), '[data-dropzone]');
                $doc = Document::where('project_id', $project->id)->where(fn ($q) => $q->where('mime', 'like', 'image/%')->orWhere('mime', 'application/pdf'))->latest()->first();
                if ($doc) {
                    $add('document', 'A document page', 'Left: index values, details, workflow, versions and eSignature. Right: the viewer. Press "Mark up" to add notes, highlights, redaction boxes and "Sign here" boxes.', $p('documents.show', [$doc]), '#mk-open');
                }
            }
            if ($u->isClientAdmin()) {
                $add('structure', 'Departments & Projects', 'Build your folder structure here. Open a project\'s "Fields & access" to define index fields (text, date, amount, calculated…), who can view, upload or index, and naming rules.', $p('structure'));
            }
            $add('workflow', 'Workflow inbox', 'Documents waiting for you to Approve, Reject or sign off. Each decision is recorded with who, when and why.', $p('workflow'));
            $add('reports', 'Reports', 'Pick a report, filter it and export to CSV. Client Admins can also schedule a report by email (daily, weekly or monthly).', $p('reports'));
            if ($u->isClientAdmin()) {
                $add('forms', 'eForms', 'Build online forms with the drag-and-drop builder and attach them to documents or workflows.', $p('forms'));
                $add('doctypes', 'Document types', 'Classify documents (Invoice, Contract…) and tie a type to a project and a workflow so it starts by itself.', $p('doctypes'));
                $add('retention', 'Retention', 'Set how long documents are kept, then review what is due for disposal. A legal hold stops deletion.', $p('retention'));
                $add('quarantine', 'Quarantine', 'Every upload and email attachment is virus-scanned. Anything suspicious waits here for you to release or destroy.', $p('quarantine'));
            }
            $add('recycle', 'Recycle Bin', 'Deleted documents wait here and can be restored.', $p('recycle'));
        }

        if ($u->isAdmin()) {
            $add('users', 'Users', 'Invite people, give each a role, and archive or delete them. Access to projects is set per project.', $p('users.index'));
            if ($u->client_id) {
                $add('branding', 'Branding', 'Logo, brand colours and footer text. Visitors who open your own domain see this brand.', $p('branding.edit'));
            }
            $add('audit', 'Audit Log', 'A record of who did what and when: sign-ins, uploads, changes, signatures.', $p('audit'));
        }

        $add('notifications', 'Notifications', 'Tasks, signatures and alerts for you appear here and in the bell at the top.', $p('notifications'));
        $add('help', 'How to Use', 'The full guide, with a section for every feature. This walkthrough is just the quick path through it.', $p('help'));

        return $s;
    }

    /**
     * Task guides: short walk-throughs of one job, step by step, on the real pages.
     *
     * @return array<int, array{id:string,title:string,summary:string,steps:array}>
     */
    public static function guides(User $u): array
    {
        if (! $u->client_id) {
            return [];
        }
        $p = fn (string $route, array $args = []) => parse_url(route($route, $args), PHP_URL_PATH);
        $project = $u->projectsWith('can_view')->orderBy('name')->first();
        $doc = $project ? Document::where('project_id', $project->id)->where(fn ($q) => $q->where('mime', 'like', 'image/%')->orWhere('mime', 'application/pdf'))->latest()->first() : null;
        $docPath = $doc ? $p('documents.show', [$doc]) : $p('documents');
        $S = fn (string $title, string $text, ?string $path, ?string $target = null) => ['id' => md5($title.$path.$target), 'title' => $title, 'text' => $text, 'path' => $path, 'target' => $target];
        $g = [];

        $g[] = ['id' => 'send-sign', 'title' => 'Send a document for signing', 'summary' => 'Draw signature boxes for each person, then send. Works for one signer or many, in order.', 'steps' => [
            $S('Open the document', 'Open a PDF or an image. (Word files cannot be drawn on: save them as PDF first.)', $docPath),
            $S('Press Mark up', 'This opens the page viewer where you can draw on the document.', $docPath, '#mk-open'),
            $S('Draw the signature boxes', 'Choose "Sign here", then drag a box where a signature is needed. You are asked WHO signs there: type a role like Candidate, HR Manager or CEO. Draw one box per signature. Boxes with the same role belong to the same person, and each role gets its own colour. Use the arrows to reach other pages.', $docPath, '[data-tool="sign"]'),
            $S('Add the signers', 'In the eSignature card, add one row per signer. Type the same role you used on the boxes. For people in your team pick them from the list (name and email fill in); for outsiders type name and email.', $docPath, '#signer-rows'),
            $S('Choose the order and send', 'Keep "Sign in this order" ticked so each person is emailed only after the one before has signed (candidate first, then HR, then the CEO). Press Send for signature. Signers need no account.', $docPath, '#esign-card'),
            $S('Follow the progress', 'Below the form you see every signer: when it was sent, when they opened it, reminders, and when they signed. The document file is updated after each signature, so the next person sees the earlier signatures.', $docPath, '#esign-card'),
        ]];

        $g[] = ['id' => 'bulk-sign', 'title' => 'Send one letter to many people', 'summary' => 'For example an offer letter to 10 candidates, each needing their own signature plus HR and CEO.', 'steps' => [
            $S('Prepare the letter once', 'Open the letter. Under Mark up → Sign here draw the boxes for every role: Candidate, HR Manager, CEO.', $docPath, '#mk-open'),
            $S('Choose "A separate copy for many people"', 'In the eSignature card pick this option. Type the candidates\' role (Candidate) and paste one person per line as "Name, email".', $docPath, '#bulk-toggle'),
            $S('Add the fixed signers', 'Below, add the signers who are the same for everyone, for example HR Manager and CEO (pick them from your team). They sign after each candidate.', $docPath, '#signer-rows'),
            $S('Send', 'Every candidate gets their own copy of the letter, named "Letter - Candidate name". Each copy follows the order: candidate, then HR, then CEO. Find the copies in the project folder.', $docPath, '#esign-card'),
        ]];

        $g[] = ['id' => 'track', 'title' => 'See who opened a document and set reminders', 'summary' => 'Know who has looked at it, when, and nudge people who have not signed.', 'steps' => [
            $S('Open the document', 'Open the document you sent for signing.', $docPath),
            $S('The Timeline', 'Every step of the document is listed here, newest first: uploads, new versions, signatures, workflow decisions. Tick "Show every time someone opened it" to also see each visit.', $docPath, '#timeline-card'),
            $S('Opened by', 'This box lists every user who opened the document, how many times, and the last time.', $docPath, '#opened-card'),
            $S('Signer progress and reminders', 'Each signer shows when they opened their link (or "Not opened yet"). Press "Remind now" for an instant reminder. Reminders also go out by themselves: set "Remind every (days)" when sending (default 2, up to 3 reminders). After the third, you get a notification.', $docPath, '#esign-card'),
        ]];

        $g[] = ['id' => 'run-workflow', 'title' => 'Start a workflow and decide', 'summary' => 'Push a document through an approval flow and handle the tasks that come to you.', 'steps' => [
            $S('Open the document', 'Open a document in a project that has a workflow.', $docPath),
            $S('Start the workflow', 'In the Workflow card pick a workflow and press Start. The document moves to the first step, and the people assigned there are notified.', $docPath, '#workflow-card'),
            $S('Your inbox', 'Tasks waiting for you appear here. Open one, read the document and press Approve or Reject (some steps ask for a comment or a form).', $p('workflow')),
        ]];

        if ($u->isClientAdmin()) {
            $first = \App\Models\Workflow::orderBy('name')->first();
            $edit = $first ? $p('workflows.edit', [$first]) : $p('workflows');
            $g[] = ['id' => 'build-workflow', 'title' => 'Build a workflow: employee signs → me → HR manager', 'summary' => 'Design your own flow on the canvas: sign, then approvals by the people you choose.', 'steps' => [
                $S('Open Workflows', 'All your workflows are listed here. Create a new one with a name and the project it belongs to, or open an existing one.', $p('workflows')),
                $S('The canvas', 'Drag steps from the left onto the canvas and connect them by dragging from a step\'s dot. Typical offer-letter flow: Start → Signature (the employee) → Task (you) → Task (HR manager) → End.', $edit, '[data-flow-builder]'),
                $S('The Signature step', 'Fill the signer\'s name and email, or take the email from an index field of the document (for example Candidate Email). "Signs in boxes with role" links it to the boxes you drew with Mark up. Set how many days it is valid and how often to remind. It has two exits: Signed and Declined.', $edit, '[data-flow-builder]'),
                $S('The Task steps', 'A Task is a decision by a person. Choose who gets it (assignees), the buttons (for example Approve / Reject), instructions, and an overdue time with someone to escalate to. Connect each button to what happens next.', $edit, '[data-flow-builder]'),
                $S('Save and test', 'Press Save, then open a document and start the workflow from its Workflow card. Turn on "start automatically" if every new document should enter it.', $edit, '[data-flow-builder]'),
            ]];

            $access = [$S('Add the user', 'Create the person here with a role (Client Admin or User).', $p('users.index'))];
            if ($project) {
                $access[] = $S('Set their access', 'Open a project\'s "Fields & access". For each person tick what they may do: view, upload, index, download, delete. People see only the projects you give them.', $p('projects.settings', [$project]));
            }
            $g[] = ['id' => 'access', 'title' => 'Give people access to documents', 'summary' => 'Add users, then decide what each one can do in each project.', 'steps' => $access];
        }

        return $g;
    }
}
