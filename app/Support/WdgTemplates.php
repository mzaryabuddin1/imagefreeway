<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Department;
use App\Models\DocType;
use App\Models\Form;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\ProjectRight;
use App\Models\User;
use App\Models\Workflow;

/**
 * Installs the five WDG processes from the client's flowcharts (Invoice, Warranty, Collections, Contract and
 * Emailed documents) into a client: one project, its index fields, the people, document types with email
 * addresses, two forms, and the four workflow graphs. Safe to run again: existing items are left alone.
 */
class WdgTemplates
{
    private Client $client;

    /** @var array<string,int> first name => user id */
    private array $u = [];

    /** @var array<string,int> */
    private array $forms = [];

    public const PEOPLE = ['Tony', 'Dana', 'Cindy', 'Mychael', 'Tanya', 'Stephanie', 'Ruth', 'Mike'];

    public static function install(Client $client, string $password): array
    {
        return (new self)->run($client, $password);
    }

    private function run(Client $client, string $password): array
    {
        $this->client = $client;
        $cid = $client->id;

        $dept = Department::withoutGlobalScopes()->firstOrCreate(['client_id' => $cid, 'name' => 'WDG'], ['sort' => 1]);
        $project = Project::withoutGlobalScopes()->firstOrCreate(['client_id' => $cid, 'name' => 'WDG Jobs'], ['department_id' => $dept->id, 'description' => 'Every document for a job, linked by contract number.', 'ocr_enabled' => true, 'sort' => 1]);
        $project->update(['link_field' => 'contract_number']);

        $fields = [
            ['contract_number', 'Contract Number', 'text'], ['customer', 'Customer', 'text'],
            ['installer_type', 'Installer Type', 'picklist', ['Replacement', 'Covering']],
            ['start_date', 'Start Date', 'date'], ['end_date', 'End Date', 'date'],
            ['invoice_number', 'Invoice Number', 'text'], ['amount', 'Amount', 'amount'], ['paid_date', 'Paid Date', 'date'],
            ['hold_required', 'Hold Required', 'yesno'],
            ['flag_collections', 'In Collections Hold', 'yesno'], ['flag_delivery', 'Awaiting Delivery', 'yesno'],
        ];
        foreach ($fields as $i => $f) {
            ProjectField::withoutGlobalScopes()->firstOrCreate(['project_id' => $project->id, 'key' => $f[0]], [
                'client_id' => $cid, 'label' => $f[1], 'type' => $f[2], 'options' => $f[3] ?? null, 'required' => false,
                'searchable' => true, 'show_in_results' => $i < 7, 'read_only' => false, 'sort' => $i + 1,
            ]);
        }

        foreach (self::PEOPLE as $name) {
            $user = User::withoutGlobalScopes()->firstOrCreate(['email' => strtolower($name).'@'.$client->slug.'.test'], [
                'client_id' => $cid, 'name' => $name, 'password' => $password, 'role' => 'user', 'status' => 'active',
            ]);
            $this->u[$name] = $user->id;
            ProjectRight::firstOrCreate(['project_id' => $project->id, 'user_id' => $user->id], ['can_view' => true, 'can_download' => true, 'can_upload' => true, 'can_index' => true, 'can_delete' => false]);
        }

        $this->forms['invoice'] = $this->form('Invoice Sign-off Form', 'Required when an invoice is assigned for verification.', [
            ['Reviewed by', 'text', true], ['Invoice matches the contract', 'checkbox', true], ['Notes', 'textarea', false],
        ]);
        $this->forms['install'] = $this->form('Installation Sign-off Form', 'Customer confirms the work is complete.', [
            ['Customer name', 'text', true], ['Work completed to my satisfaction', 'checkbox', true], ['Comments', 'textarea', false], ['Customer signature', 'signature', true],
        ]);

        // Workflows are created first so that "send to workflow" steps can point at each other.
        $wf = [];
        foreach (['Invoice Workflow', 'Warranty Workflow', 'Collections Workflow', 'Contract Workflow'] as $name) {
            $wf[$name] = Workflow::withoutGlobalScopes()->firstOrCreate(['client_id' => $cid, 'name' => $name], ['project_id' => $project->id, 'active' => true, 'auto_start' => false, 'graph' => null]);
        }
        $built = [];
        foreach ([
            'Invoice Workflow' => fn () => $this->invoice(),
            'Warranty Workflow' => fn () => $this->warranty($wf['Contract Workflow']->id),
            'Collections Workflow' => fn () => $this->collections(),
            'Contract Workflow' => fn () => $this->contract($wf['Collections Workflow']->id),
        ] as $name => $make) {
            if (! empty($wf[$name]->graph['nodes'])) {
                continue;
            }
            $graph = Flow::layout(Flow::sanitize($make()));
            $wf[$name]->update(['graph' => $graph, 'description' => 'Ready-made from the WDG flowchart. Edit it on the canvas.']);
            $built[] = $name;
        }

        $types = [
            ['Invoice_TBD', 'wdg.invoice-tbd', 'Invoice Workflow'], ['Invoice_installer', 'wdg.invoice-installer', null],
            ['Contract', 'wdg.contracts', 'Contract Workflow'], ['Initial QB Receipt', 'wdg.initial-receipt', null], ['Final QBO Receipt', 'wdg.final-receipt', null],
            ['Warranty Pymt QB Receipt', 'wdg.warranty-receipt', null], ['Change QB Receipt', 'wdg.change-receipt', null], ['Permit QB Receipt', 'wdg.permit-receipt', null],
            ['Order Confirmation', 'wdg.order-confirmation', null], ['Measurement Form', 'wdg.measurement', null], ['Sign Off', 'wdg.signoff', null],
            ['Packing Slip', 'wdg.packing-slip', null], ['Installer Paid (TBD)', null, null],
        ];
        foreach ($types as [$name, $alias, $flow]) {
            DocType::withoutGlobalScopes()->firstOrCreate(['client_id' => $cid, 'name' => $name], [
                'email_alias' => $alias, 'project_id' => $project->id, 'index_field' => 'contract_number', 'workflow_id' => $flow ? $wf[$flow]->id : null,
            ]);
        }

        return ['project' => $project->name, 'workflows' => $built, 'people' => self::PEOPLE];
    }

    private function form(string $name, string $desc, array $qs): int
    {
        $fields = array_map(fn ($q) => ['key' => \Illuminate\Support\Str::slug($q[0], '_'), 'label' => $q[0], 'type' => $q[1], 'required' => $q[2], 'options' => []], $qs);

        return Form::withoutGlobalScopes()->firstOrCreate(['client_id' => $this->client->id, 'name' => $name], ['description' => $desc, 'fields' => $fields, 'active' => true])->id;
    }

    /* ---------------- Graph helpers ---------------- */

    private const EMAIL_MAP = "Replacement=replacement-installer@example.com\nCovering=covering-installer@example.com";

    private function start(string $name): array
    {
        return ['id' => 'start', 'type' => 'start', 'name' => $name, 'config' => []];
    }

    /** @param array<int,string> $who first names */
    private function task(string $id, string $name, array $who, array $options, array $c = []): array
    {
        return ['id' => $id, 'type' => 'task', 'name' => $name, 'config' => ['assignees' => array_map(fn ($n) => $this->u[$n], $who), 'options' => $options] + $c];
    }

    private function end(string $id, string $label, bool $archive = true): array
    {
        return ['id' => $id, 'type' => 'end', 'name' => $label, 'config' => ['label' => $label, 'archive' => $archive]];
    }

    private function email(string $id, string $name, string $subject, string $body): array
    {
        return ['id' => $id, 'type' => 'email', 'name' => $name, 'config' => ['to_sender' => true, 'to_field' => 'installer_type', 'to_map' => self::EMAIL_MAP, 'subject' => $subject, 'body' => $body]];
    }

    private function goto(string $id, string $name, int $workflowId): array
    {
        return ['id' => $id, 'type' => 'goto', 'name' => $name, 'config' => ['workflow_id' => $workflowId]];
    }

    private function cond(string $id, string $name, array $rules): array
    {
        return ['id' => $id, 'type' => 'condition', 'name' => $name, 'config' => ['rules' => array_map(fn ($r) => ['label' => $r[0], 'conds' => [['field' => $r[1], 'op' => 'eq', 'value' => $r[2]]]], $rules)]];
    }

    private const LATE = ['overdue_hours' => 24, 'escalate_to' => 'previous'];   // "~ escalation email to previous person if not completed in 1 work day"

    private function req(string $kind, string $value, array $on = []): array
    {
        return ['kind' => $kind, 'value' => $value, 'on' => $on];
    }

    private function edges(array $rows): array
    {
        return array_map(fn ($r) => ['from' => $r[0], 'port' => $r[1], 'to' => $r[2]], $rows);
    }

    /* ---------------- The workflows ---------------- */

    private function invoice(): array
    {
        $nodes = [
            $this->start('Invoice_TBD emailed in'),
            $this->email('mail1', 'Email WDG invoice to installer', 'Invoice for contract {contract_number}', "Please find the WDG invoice for contract {contract_number}.\nReply with your installer invoice (Invoice_installer) to this address."),
            $this->task('assign', 'Assign for verification', ['Cindy'], ['Tony', 'Dana', 'Cancel', 'Send for Pymt'], [
                'instructions' => 'Wait for the installer invoice (Invoice_installer) to be emailed in. Index the invoice number. Tony and Dana need a sign-off form.',
                'requirements' => [$this->req('field', 'invoice_number', ['Tony', 'Dana', 'Send for Pymt']), $this->req('doc', 'Invoice_installer', ['Tony', 'Dana', 'Send for Pymt']), $this->req('form', (string) $this->forms['invoice'], ['Tony', 'Dana'])],
            ]),
            $this->end('cancel', 'Cancelled folder'),
            ['id' => 'dupT', 'type' => 'duplicate', 'name' => 'Check for duplicate invoice (Tony)', 'config' => ['field' => 'invoice_number']],
            ['id' => 'dupD', 'type' => 'duplicate', 'name' => 'Check for duplicate invoice (Dana)', 'config' => ['field' => 'invoice_number']],
            $this->task('duptask', 'Duplicate invoice', ['Cindy'], ['Duplicate', 'Not a duplicate: Tony', 'Not a duplicate: Dana']),
            $this->email('mail2', 'Email installer: duplicate invoice received', 'Duplicate invoice received: contract {contract_number}', 'We already hold invoice {invoice_number}. This one was not processed.'),
            $this->end('cancel2', 'Cancelled folder (duplicate)'),
            $this->task('tony', 'Assign for verification: Tony', ['Tony'], ['Verified']),
            $this->task('dana', 'Assign for verification: Dana', ['Dana'], ['Verified']),
            $this->task('final', 'Final review', ['Cindy'], ['Send for Payment', 'Return to Tony', 'Return to Dana']),
            $this->task('pay', 'Final payment', ['Mychael'], ['Paid'], ['instructions' => 'Pay the invoice and enter the paid date.', 'requirements' => [$this->req('field', 'paid_date', ['Paid'])]]),
            $this->end('archive', 'Archive'),
        ];

        return ['nodes' => $nodes, 'edges' => $this->edges([
            ['start', 'Next', 'mail1'], ['mail1', 'Next', 'assign'],
            ['assign', 'Tony', 'dupT'], ['assign', 'Dana', 'dupD'], ['assign', 'Cancel', 'cancel'], ['assign', 'Send for Pymt', 'pay'],
            ['dupT', 'Duplicate', 'duptask'], ['dupT', 'Not a duplicate', 'tony'], ['dupD', 'Duplicate', 'duptask'], ['dupD', 'Not a duplicate', 'dana'],
            ['duptask', 'Duplicate', 'mail2'], ['duptask', 'Not a duplicate: Tony', 'tony'], ['duptask', 'Not a duplicate: Dana', 'dana'], ['mail2', 'Next', 'cancel2'],
            ['tony', 'Verified', 'final'], ['dana', 'Verified', 'final'],
            ['final', 'Send for Payment', 'pay'], ['final', 'Return to Tony', 'tony'], ['final', 'Return to Dana', 'dana'],
            ['pay', 'Paid', 'archive'],
        ])];
    }

    private function warranty(int $contractId): array
    {
        $nodes = [
            $this->start('CSR finds the client file and adds it to Warranty'),
            $this->task('review', 'Review issue', ['Tanya'], ['Inspection', 'Repair Sign Off', 'Warranty Pymt']),
            $this->email('mail', 'Email installer: inspection', 'Inspection needed: contract {contract_number}', 'Please inspect the warranty issue on contract {contract_number}.'),
            $this->task('await', 'Await inspection', ['Tanya'], ['Inspected']),
            $this->goto('tocontract', 'To Contract Workflow', $contractId),
            $this->task('wpay', 'Warranty payment', ['Mychael'], ['Complete', 'Research'], ['requirements' => [$this->req('doc', 'Warranty Pymt QB Receipt', ['Complete'])]]),
            $this->task('research', 'Payment research', ['Tanya'], ['Done']),
        ];

        return ['nodes' => $nodes, 'edges' => $this->edges([
            ['start', 'Next', 'review'],
            ['review', 'Inspection', 'mail'], ['mail', 'Next', 'await'], ['await', 'Inspected', 'tocontract'],
            ['review', 'Repair Sign Off', 'tocontract'], ['review', 'Warranty Pymt', 'wpay'],
            ['wpay', 'Complete', 'review'], ['wpay', 'Research', 'research'], ['research', 'Done', 'wpay'],
        ])];
    }

    private function collections(): array
    {
        $hold = fn (string $id, string $name, string $who = 'Tanya') => $this->task($id, $name, [$who], ['Resolved'], ['flag_field' => 'flag_collections']);
        $nodes = [
            $this->start('Send to Collections'),
            $this->task('col', 'Collections', ['Tanya'], ['Prelim Notice', 'Place Lien', 'Await Sales', 'Collection Agency', 'Attorney letter', 'Credit Card Dispute'], ['instructions' => 'Mike searches for the job file for a credit card dispute, makes a note and adds it here.']),
            $this->task('prelim', 'Send prelim notice', ['Dana'], ['Sent']),
            $hold('hprelim', 'Hold for prelim notice resolution'), $hold('hlien', 'Hold for lien resolution'), $hold('hsales', 'Hold for sales resolution'),
            $hold('hagency', 'Hold for collection agency resolution'), $hold('hatty', 'Hold for attorney letter resolution'), $hold('hcard', 'Credit card dispute', 'Cindy'),
            $this->end('archive', 'Archive'),
        ];

        return ['nodes' => $nodes, 'edges' => $this->edges([
            ['start', 'Next', 'col'],
            ['col', 'Prelim Notice', 'prelim'], ['prelim', 'Sent', 'hprelim'], ['hprelim', 'Resolved', 'archive'],
            ['col', 'Place Lien', 'hlien'], ['col', 'Await Sales', 'hsales'], ['col', 'Collection Agency', 'hagency'], ['col', 'Attorney letter', 'hatty'], ['col', 'Credit Card Dispute', 'hcard'],
            ['hlien', 'Resolved', 'archive'], ['hsales', 'Resolved', 'archive'], ['hagency', 'Resolved', 'archive'], ['hatty', 'Resolved', 'archive'], ['hcard', 'Resolved', 'archive'],
        ])];
    }

    private function contract(int $collectionsId): array
    {
        $late = self::LATE;
        $measureMail = fn (string $id) => $this->email($id, 'Email installer: start measurement', 'Measurement needed: contract {contract_number}', 'Please measure for contract {contract_number}.');
        $holds = fn (string $id, string $name, string $who) => $this->task($id, $name, [$who], ['Released'], ['requirements' => [$this->req('doc', 'Invoice_TBD', ['Released'])]]);

        $nodes = [
            $this->start('All documents received via email'),
            $this->task('init', 'Initial verification', ['Stephanie'], ['Complete', 'Missing Info', 'Complete_Needs Permits'], [
                'instructions' => 'Collect the installer type and start date. Set the contract number if it is not set yet.',
                'requirements' => [$this->req('field', 'contract_number', ['Complete', 'Complete_Needs Permits']), $this->req('field', 'installer_type', ['Complete', 'Complete_Needs Permits']), $this->req('field', 'start_date', ['Complete', 'Complete_Needs Permits'])],
            ]),
            $this->task('second', 'Secondary verification', ['Stephanie'], ['Complete', 'Complete_Needs Permits']),
            $this->task('pay1', 'Initial payment', ['Mychael'], ['Complete', 'Research'], ['requirements' => [$this->req('doc', 'Initial QB Receipt', ['Complete'])]]),
            $this->task('res1', 'Payment research', ['Tanya'], ['Done'], $late),
            $measureMail('mail_m1'),
            $this->task('meas', 'Await measurement form', ['Dana'], ['Measurement received'], ['overdue_hours' => 96, 'escalate_to' => (string) $this->u['Cindy'], 'requirements' => [$this->req('doc', 'Initial QB Receipt'), $this->req('doc', 'Measurement Form', ['Measurement received'])]]),
            $this->task('permits', 'Permits', ['Tanya'], ['Complete', 'Needs Payment']),
            $this->task('permitpay', 'Permit payment', ['Mychael'], ['Complete', 'Research'], ['requirements' => [$this->req('doc', 'Permit QB Receipt', ['Complete'])]]),
            $this->task('res_p', 'Payment research', ['Tanya'], ['Done'], $late),
            $measureMail('mail_m2'),
            $this->task('meas_p', 'Await measurement form: permits', ['Tanya'], ['Measurement received']),

            $this->task('order', 'Order product', ['Dana'], ['Ordered', 'Forward for Ordering', 'Change Order'], $late + [
                'instructions' => 'Check the initial payment is in place and the measurement form is on file.',
                'requirements' => [$this->req('doc', 'Initial QB Receipt', ['Ordered', 'Forward for Ordering']), $this->req('doc', 'Measurement Form', ['Ordered', 'Forward for Ordering']), $this->req('doc', 'Invoice_TBD', ['Ordered'])],
            ]),
            $this->task('verify', 'Order verification', ['Tony'], ['Ordered', 'Hold_CIP', 'Hold_Sales Rep', 'Hold_Installer'], $late + ['requirements' => [$this->req('doc', 'Order Confirmation', ['Ordered'])]]),
            $this->task('orderverify', 'Order and verification', ['Tony'], ['Ordered', 'Hold_CIP', 'Hold_Sales Rep', 'Hold_Installer'], $late + ['requirements' => [$this->req('doc', 'Order Confirmation', ['Ordered'])]]),
            $holds('h_cip', 'Hold: CIP', 'Tony'), $holds('h_sales', 'Hold: Sales Rep', 'Tony'), $holds('h_inst', 'Hold: Installer', 'Tony'),
            $this->cond('holdreq', 'Hold required?', [['Hold required', 'hold_required', 'Yes']]),
            $this->task('h_vendor', 'Hold: vendor confirmation', ['Dana'], ['Released'], ['requirements' => [$this->req('doc', 'Invoice_TBD', ['Released'])]]),
            $this->cond('type1', 'Install type', [['Replacement', 'installer_type', 'Replacement'], ['Covering', 'installer_type', 'Covering']]),
            $this->task('del_r', 'Product delivery: replacement', ['Ruth'], ['Delivered'], ['flag_field' => 'flag_delivery', 'requirements' => [$this->req('doc', 'Packing Slip', ['Delivered'])]]),
            $this->task('del_c', 'Product delivery: covering', ['Ruth'], ['Delivered'], ['flag_field' => 'flag_delivery', 'requirements' => [$this->req('doc', 'Packing Slip', ['Delivered'])]]),

            $this->task('change', 'Change order', ['Cindy'], ['Price Change', 'Order'], $late),
            $this->task('paychg', 'Initial payment (change order)', ['Mychael'], ['Complete', 'Research'], ['requirements' => [$this->req('doc', 'Change QB Receipt', ['Complete'])]]),
            $this->task('reschg', 'Payment research', ['Tanya'], ['Done'], $late),

            $this->task('finalpay', 'Final payment', ['Mychael'], ['Complete', 'Research', 'Resolution', 'Collections'], ['requirements' => [$this->req('doc', 'Final QBO Receipt', ['Complete'])]]),
            $this->task('resf', 'Final payment research', ['Tanya'], ['Done'], $late),
            $this->task('custres', 'Customer resolution', ['Mychael'], ['Resolved', 'Send to Collections']),
            $this->goto('tocoll', 'Send to Collections', $collectionsId),
            $this->email('mail_so', 'Email installer: sign-off request', 'Sign-off needed: contract {contract_number}', 'Please obtain the customer sign-off for contract {contract_number}. Use the Installation Sign-off Form.'),
            $this->cond('type2', 'Install type', [['Replacement', 'installer_type', 'Replacement'], ['Covering', 'installer_type', 'Covering']]),
            $this->task('so_r', 'Awaiting sign-off: replacement', ['Cindy'], ['Signed off', 'Uninstall Repair Photo', 'Uninstall Repair Other', 'Installed Repair Photo', 'Installed Repair Other', 'Send to Tanya', 'Send for Pymt'], $late + ['requirements' => [$this->req('doc', 'Sign Off', ['Signed off'])]]),
            $this->task('so_c', 'Awaiting sign-off: covering', ['Cindy'], ['Signed off', 'Uninstall Repair Photo', 'Uninstall Repair Other', 'Installed Repair Photo', 'Installed Repair Other', 'Send to Tanya', 'Send for Pymt'], $late + ['requirements' => [$this->req('doc', 'Sign Off', ['Signed off'])]]),
            $this->task('addpay', 'Additional payment', ['Mychael'], ['Complete'], ['requirements' => [$this->req('doc', 'Change QB Receipt', ['Complete'])]]),
            $this->task('photos', 'Hold for photos', ['Dana'], ['Re-Order']),
            $this->task('other', 'Hold for other', ['Dana'], ['Re-Order']),
            $this->task('repair', 'Await repair', ['Tanya'], ['Settlement', 'Collections', 'Return to Cindy']),
            $this->task('refund', 'Refund / bill back', ['Mychael'], ['Done'], $late + ['requirements' => [$this->req('doc', 'Installer Paid (TBD)', ['Done'])]]),
            $this->task('billback', 'Bill back', ['Cindy'], ['Bill Back', 'No Bill Back']),
            $this->end('archive', 'Archive'),
        ];

        return ['nodes' => $nodes, 'edges' => $this->edges([
            ['start', 'Next', 'init'],
            ['init', 'Complete', 'pay1'], ['init', 'Missing Info', 'second'], ['init', 'Complete_Needs Permits', 'permits'],
            ['second', 'Complete', 'pay1'], ['second', 'Complete_Needs Permits', 'permits'],
            ['pay1', 'Complete', 'mail_m1'], ['pay1', 'Research', 'res1'], ['res1', 'Done', 'pay1'],
            ['mail_m1', 'Next', 'meas'], ['meas', 'Measurement received', 'order'],
            ['permits', 'Complete', 'mail_m2'], ['permits', 'Needs Payment', 'permitpay'],
            ['permitpay', 'Complete', 'mail_m2'], ['permitpay', 'Research', 'res_p'], ['res_p', 'Done', 'permitpay'],
            ['mail_m2', 'Next', 'meas_p'], ['meas_p', 'Measurement received', 'order'],

            ['order', 'Ordered', 'orderverify'], ['order', 'Forward for Ordering', 'verify'], ['order', 'Change Order', 'change'],
            ['verify', 'Ordered', 'holdreq'], ['verify', 'Hold_CIP', 'h_cip'], ['verify', 'Hold_Sales Rep', 'h_sales'], ['verify', 'Hold_Installer', 'h_inst'],
            ['orderverify', 'Ordered', 'holdreq'], ['orderverify', 'Hold_CIP', 'h_cip'], ['orderverify', 'Hold_Sales Rep', 'h_sales'], ['orderverify', 'Hold_Installer', 'h_inst'],
            ['h_cip', 'Released', 'orderverify'], ['h_sales', 'Released', 'orderverify'], ['h_inst', 'Released', 'orderverify'],
            ['holdreq', 'Hold required', 'h_vendor'], ['holdreq', 'Otherwise', 'type1'], ['h_vendor', 'Released', 'type1'],
            ['type1', 'Replacement', 'del_r'], ['type1', 'Covering', 'del_c'], ['type1', 'Otherwise', 'del_r'],
            ['change', 'Price Change', 'paychg'], ['change', 'Order', 'order'],
            ['paychg', 'Complete', 'order'], ['paychg', 'Research', 'reschg'], ['reschg', 'Done', 'paychg'],

            ['del_r', 'Delivered', 'finalpay'], ['del_c', 'Delivered', 'finalpay'],
            ['finalpay', 'Complete', 'mail_so'], ['finalpay', 'Research', 'resf'], ['finalpay', 'Resolution', 'custres'], ['finalpay', 'Collections', 'tocoll'],
            ['resf', 'Done', 'finalpay'], ['custres', 'Resolved', 'finalpay'], ['custres', 'Send to Collections', 'tocoll'],
            ['mail_so', 'Next', 'type2'], ['type2', 'Replacement', 'so_r'], ['type2', 'Covering', 'so_c'], ['type2', 'Otherwise', 'so_r'],
            ['so_r', 'Signed off', 'archive'], ['so_c', 'Signed off', 'archive'],
            ['so_r', 'Uninstall Repair Photo', 'photos'], ['so_c', 'Uninstall Repair Photo', 'photos'], ['so_r', 'Uninstall Repair Other', 'other'], ['so_c', 'Uninstall Repair Other', 'other'],
            ['so_r', 'Installed Repair Photo', 'repair'], ['so_c', 'Installed Repair Photo', 'repair'], ['so_r', 'Installed Repair Other', 'repair'], ['so_c', 'Installed Repair Other', 'repair'],
            ['so_r', 'Send to Tanya', 'repair'], ['so_c', 'Send to Tanya', 'repair'], ['so_r', 'Send for Pymt', 'addpay'], ['so_c', 'Send for Pymt', 'addpay'],
            ['addpay', 'Complete', 'type2'], ['photos', 'Re-Order', 'order'], ['other', 'Re-Order', 'order'],
            ['repair', 'Settlement', 'refund'], ['repair', 'Collections', 'tocoll'], ['repair', 'Return to Cindy', 'type2'],
            ['refund', 'Done', 'billback'], ['billback', 'Bill Back', 'archive'], ['billback', 'No Bill Back', 'archive'],
        ])];
    }
}
