<?php

use App\Models\Client;
use App\Models\DocType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowItem;
use App\Services\WorkflowEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| php artisan demo:fill            adds about 60 realistic documents (images, index values, types, workflows, versions)
| php artisan demo:fill --clear    removes everything demo:fill added
| Only for demos and screenshots; never run on a live client.
*/
Artisan::command('demo:fill {client=demo} {--clear} {--count=60}', function (WorkflowEngine $engine) {
    $client = Client::where('slug', $this->argument('client'))->first();
    if (! $client) {
        return $this->error('No such client.');
    }
    $disk = Storage::disk(config('filesystems.documents_disk'));
    $marker = '/demo-';

    $existing = Document::withoutGlobalScopes()->withTrashed()->where('client_id', $client->id)->where('path', 'like', "%{$marker}%")->get();
    foreach ($existing as $d) {
        DocumentVersion::withoutGlobalScopes()->where('document_id', $d->id)->get()->each(function ($v) use ($disk) {
            $disk->delete($v->path);
            $v->delete();
        });
        WorkflowItem::withoutGlobalScopes()->where('document_id', $d->id)->delete();
        $disk->delete($d->path);
        $d->forceDelete();
    }
    if ($this->option('clear')) {
        return $this->info($existing->count().' demo document(s) removed.');
    }

    $admin = User::withoutGlobalScopes()->where('client_id', $client->id)->where('role', 'client_admin')->first();
    $ap = Project::withoutGlobalScopes()->with('fields')->where('client_id', $client->id)->where('name', 'Accounts Payable')->first();
    $wdg = Project::withoutGlobalScopes()->with('fields')->where('client_id', $client->id)->where('name', 'WDG Jobs')->first();
    $types = DocType::withoutGlobalScopes()->where('client_id', $client->id)->pluck('name')->all();
    $invoiceWf = Workflow::withoutGlobalScopes()->where('client_id', $client->id)->where('name', 'Invoice Workflow')->first();

    $png = function (string $title, string $lines, array $rgb) {
        $im = imagecreatetruecolor(480, 620);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 0, 0, 480, 70, imagecolorallocate($im, ...$rgb));
        imagestring($im, 5, 24, 28, $title, imagecolorallocate($im, 255, 255, 255));
        $y = 110;
        foreach (explode("\n", $lines) as $l) {
            imagestring($im, 4, 24, $y, $l, imagecolorallocate($im, 30, 41, 59));
            $y += 28;
        }
        for ($i = 0; $i < 9; $i++) {
            imagefilledrectangle($im, 24, 330 + $i * 24, 456 - ($i * 37 % 120), 338 + $i * 24, imagecolorallocate($im, 226, 232, 240));
        }
        ob_start();
        imagepng($im);

        return ob_get_clean();
    };

    $vendors = $ap?->fields->firstWhere('key', 'vendor')?->options ?: ['Acme Supplies', 'Globex', 'Initech'];
    $installers = $wdg?->fields->firstWhere('key', 'installer_type')?->options ?: ['Carpet', 'Hardwood'];
    $customers = ['Northwind Homes', 'Blue Ridge Builders', 'Summit Properties', 'Harbor Realty', 'Oakline Developments', 'Peak Interiors'];
    $made = 0;
    $total = (int) $this->option('count');

    $make = function (Project $p, string $name, array $values, string $type, string $status, \Illuminate\Support\Carbon $at, array $rgb, string $lines) use ($client, $admin, $disk, $png, $marker) {
        $path = "clients/{$client->id}/projects/{$p->id}{$marker}".Str::uuid().'.png';
        $bytes = $png($type, $lines, $rgb);
        $disk->put($path, $bytes);
        $d = Document::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'project_id' => $p->id, 'uploaded_by' => $admin?->id, 'original_name' => $name, 'mime' => 'image/png', 'size' => strlen($bytes),
            'path' => $path, 'status' => $status, 'index_values' => $values, 'doc_type' => $type, 'ocr_status' => 'none', 'indexed_at' => $status === 'indexed' ? $at : null,
        ]);
        $d->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return [$d, $bytes];
    };

    for ($i = 0; $i < $total; $i++) {
        // Busier on recent weekdays: pick a day with a bias towards the near past
        $daysAgo = (int) floor(pow(mt_rand() / mt_getrandmax(), 1.6) * 44);
        $at = today()->subDays($daysAgo)->setTime(mt_rand(8, 17), mt_rand(0, 59));
        if ($at->isWeekend() && mt_rand(0, 3)) {
            $at = $at->subDays($at->isSaturday() ? 1 : 2);
        }
        $status = mt_rand(1, 100) <= 78 ? 'indexed' : 'unindexed';

        if ($ap && ($i % 5 < 3 || ! $wdg)) {
            $num = 'INV-'.(2000 + $i);
            $vendor = $vendors[array_rand($vendors)];
            $amount = round(mt_rand(120, 9800) + mt_rand(0, 99) / 100, 2);
            $vals = $status === 'indexed' ? ['invoice_number' => $num, 'vendor' => $vendor, 'amount' => (string) $amount, 'invoice_date' => $at->toDateString()] : [];
            [$d, $bytes] = $make($ap, "Invoice {$num}.png", $vals, 'Invoice', $status, $at, [30, 64, 175], "Invoice: {$num}\nVendor: {$vendor}\nAmount: \${$amount}\nDate: {$at->toDateString()}");
        } elseif ($wdg) {
            $cn = 'CN-'.(5000 + intdiv($i, 3));
            $type = $types ? $types[array_rand($types)] : 'Contract';
            $vals = ['contract_number' => $cn, 'customer' => $customers[array_rand($customers)], 'installer_type' => $installers[array_rand($installers)], 'start_date' => $at->toDateString(), 'amount' => (string) round(mt_rand(900, 24000) + mt_rand(0, 99) / 100, 2)];
            [$d, $bytes] = $make($wdg, Str::slug($type)."-{$cn}.png", $vals, $type, 'indexed', $at, [4, 120, 87], "{$type}\nJob: {$cn}\nCustomer: {$vals['customer']}\nAmount: \${$vals['amount']}");
        } else {
            continue;
        }
        $made++;

        // A few documents have an older version
        if ($i % 7 === 0) {
            $oldPath = "clients/{$client->id}/projects/{$d->project_id}{$marker}".Str::uuid().'.png';
            $disk->put($oldPath, $bytes);
            DocumentVersion::withoutGlobalScopes()->create(['client_id' => $client->id, 'document_id' => $d->id, 'version_no' => 1, 'path' => $oldPath, 'mime' => 'image/png', 'size' => strlen($bytes), 'uploaded_by' => $admin?->id, 'note' => 'First scan', 'replaced_at' => $at->copy()->addHours(3)]);
        }

        // Workflow: invoices flow through the Invoice Workflow; some finish
        if ($invoiceWf && $d->project_id === $wdg?->id && $i % 4 === 0 && $status === 'indexed') {
            $engine->start($d, $invoiceWf, $admin);
            $item = WorkflowItem::withoutGlobalScopes()->where('document_id', $d->id)->latest('id')->first();
            if ($item && $i % 8 === 0) {
                $item->forceFill(['status' => 'complete', 'result' => ['Archive', 'Approved', 'Paid'][$i % 3], 'node_id' => null])->save();
            }
        }
    }
    $this->info("{$made} demo documents added to {$client->name}. Remove them with: php artisan demo:fill --clear");
})->purpose('Fill a client with realistic demo documents so charts and gallery have data');
