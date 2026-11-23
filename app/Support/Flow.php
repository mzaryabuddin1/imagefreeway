<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A workflow is a graph: nodes (steps) and connections (node + output port -> node).
 * Everything here is plain data. Nothing stored in a graph is ever executed.
 */
class Flow
{
    /** type => label, icon (lucide), colour, one-line help */
    public const TYPES = [
        'start' => ['Start', 'play', '#16a34a', 'Where a document enters the workflow.'],
        'task' => ['Task', 'user-check', '#2b3990', 'A person decides. Each option leaves by its own connection.'],
        'email' => ['Send email', 'mail', '#0891b2', 'Emails someone automatically, then carries on.'],
        'condition' => ['If / else', 'split', '#d97706', 'Routes by index values, such as Installer Type or Amount.'],
        'set_index' => ['Set index', 'tag', '#7c3aed', 'Fills in or changes index values, then carries on.'],
        'duplicate' => ['Duplicate check', 'copy', '#db2777', 'Looks for another document with the same value.'],
        'signature' => ['Request signature', 'pen-line', '#059669', 'Waits for someone to sign electronically.'],
        'fork' => ['Run in parallel', 'git-fork', '#475569', 'Starts several branches at once.'],
        'goto' => ['Send to workflow', 'forward', '#9333ea', 'Hands the document to another workflow.'],
        'end' => ['Finish', 'circle-stop', '#dc2626', 'Ends the workflow (optionally archives).'],
    ];

    public const OPERATORS = [
        'eq' => 'equals', 'ne' => 'does not equal', 'gt' => 'is greater than', 'gte' => 'is at least',
        'lt' => 'is less than', 'lte' => 'is at most', 'contains' => 'contains', 'empty' => 'is blank', 'filled' => 'is filled',
    ];

    public static function ports(array $node): array
    {
        $c = $node['config'] ?? [];

        return match ($node['type'] ?? '') {
            'task' => $c['options'] ?? ['Done'],
            'signature' => ['Signed', 'Declined'],
            'condition' => array_merge(array_map(fn ($r) => $r['label'], $c['rules'] ?? []), ['Otherwise']),
            'duplicate' => ['Duplicate', 'Not a duplicate'],
            'fork' => array_map(fn ($i) => 'Branch '.($i + 1), range(0, max(1, min(5, (int) ($c['branches'] ?? 2))) - 1)),
            'start', 'email', 'set_index' => ['Next'],
            default => [],
        };
    }

    /** Clean a graph coming from the browser: known types only, bounded sizes, connections that point at real ports. */
    public static function sanitize(array $graph): array
    {
        $nodes = [];
        foreach (array_slice($graph['nodes'] ?? [], 0, 120) as $n) {
            $type = $n['type'] ?? '';
            if (! isset(self::TYPES[$type])) {
                continue;
            }
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($n['id'] ?? '')) ?: 'n'.Str::lower(Str::random(6));
            $nodes[$id] = [
                'id' => $id, 'type' => $type,
                'name' => Str::limit(trim((string) ($n['name'] ?? self::TYPES[$type][0])), 120, '') ?: self::TYPES[$type][0],
                'x' => (int) max(-5000, min(8000, (float) ($n['x'] ?? 0))), 'y' => (int) max(-5000, min(12000, (float) ($n['y'] ?? 0))),
                'config' => self::config($type, (array) ($n['config'] ?? [])),
            ];
        }

        $edges = [];
        foreach (array_slice($graph['edges'] ?? [], 0, 400) as $e) {
            $from = $nodes[$e['from'] ?? ''] ?? null;
            $to = $e['to'] ?? '';
            if ($from && isset($nodes[$to]) && $to !== $from['id'] && in_array($e['port'] ?? '', self::ports($from), true)) {
                $edges[$from['id'].'|'.$e['port']] = ['from' => $from['id'], 'port' => $e['port'], 'to' => $to];   // one connection per port
            }
        }

        return ['nodes' => array_values($nodes), 'edges' => array_values($edges)];
    }

    private static function strings(mixed $v, int $max = 100, int $count = 30): array
    {
        return array_values(array_filter(array_map(fn ($s) => Str::limit(trim((string) $s), $max, ''), array_slice((array) $v, 0, $count)), fn ($s) => $s !== ''));
    }

    private static function config(string $type, array $c): array
    {
        $str = fn ($k, $max = 500) => Str::limit(trim((string) ($c[$k] ?? '')), $max, '');
        $conds = fn ($list) => array_values(array_map(fn ($x) => [
            'field' => Str::limit((string) ($x['field'] ?? ''), 80, ''),
            'op' => isset(self::OPERATORS[$x['op'] ?? '']) ? $x['op'] : 'eq',
            'value' => Str::limit((string) ($x['value'] ?? ''), 200, ''),
        ], array_filter(array_slice((array) $list, 0, 10), fn ($x) => ! empty($x['field']))));

        return match ($type) {
            'task' => [
                'assignees' => array_values(array_map('intval', array_slice((array) ($c['assignees'] ?? []), 0, 30))),
                'options' => self::strings($c['options'] ?? ['Done'], 60, 10) ?: ['Done'],
                'instructions' => $str('instructions', 1000),
                'overdue_hours' => isset($c['overdue_hours']) && $c['overdue_hours'] !== '' ? max(1, min(2000, (int) $c['overdue_hours'])) : null,
                'escalate_to' => $str('escalate_to', 12),                  // '', 'previous' or a user id
                'flag_field' => $str('flag_field', 80),                    // set to Yes on entry, cleared on leaving
                'requirements' => array_values(array_map(fn ($r) => [
                    'kind' => in_array($r['kind'] ?? '', ['field', 'doc', 'form'], true) ? $r['kind'] : 'field',
                    'value' => Str::limit((string) ($r['value'] ?? ''), 100, ''),
                    'on' => self::strings($r['on'] ?? [], 60, 10),
                ], array_filter(array_slice((array) ($c['requirements'] ?? []), 0, 12), fn ($r) => ($r['value'] ?? '') !== ''))),
            ],
            'email' => [
                'to' => $str('to', 300), 'to_sender' => (bool) ($c['to_sender'] ?? false),
                'to_field' => $str('to_field', 80), 'to_map' => $str('to_map', 1000),
                'subject' => $str('subject', 200), 'body' => $str('body', 3000), 'secure' => (bool) ($c['secure'] ?? false),
            ],
            'condition' => ['rules' => array_values(array_map(fn ($r) => [
                'label' => Str::limit(trim((string) ($r['label'] ?? '')), 60, '') ?: 'Rule',
                'conds' => $conds($r['conds'] ?? []),
            ], array_slice((array) ($c['rules'] ?? []), 0, 8)))],
            'set_index' => ['sets' => array_values(array_map(fn ($s) => [
                'field' => Str::limit((string) ($s['field'] ?? ''), 80, ''), 'value' => Str::limit((string) ($s['value'] ?? ''), 200, ''),
            ], array_filter(array_slice((array) ($c['sets'] ?? []), 0, 10), fn ($s) => ! empty($s['field']))))],
            'duplicate' => ['field' => $str('field', 80)],
            'signature' => [
                'signer_name' => $str('signer_name', 120), 'signer_email' => $str('signer_email', 190), 'email_field' => $str('email_field', 80),
                'days' => max(1, min(60, (int) ($c['days'] ?? 14))), 'message' => $str('message', 400),
            ],
            'fork' => ['branches' => max(2, min(5, (int) ($c['branches'] ?? 2)))],
            'goto' => ['workflow_id' => (int) ($c['workflow_id'] ?? 0)],
            'end' => ['label' => $str('label', 100), 'archive' => (bool) ($c['archive'] ?? false)],
            default => [],
        };
    }

    /** Left-to-right layered layout from the Start step (used for ready-made workflows; the builder has a Tidy button too). */
    public static function layout(array $graph): array
    {
        $nodes = collect($graph['nodes'])->keyBy('id')->all();
        $start = collect($nodes)->firstWhere('type', 'start')['id'] ?? array_key_first($nodes);
        $depth = [$start => 0];
        $queue = [$start];
        while ($queue) {
            $id = array_shift($queue);
            foreach ($graph['edges'] as $e) {
                if ($e['from'] === $id && ! isset($depth[$e['to']])) {
                    $depth[$e['to']] = $depth[$id] + 1;
                    $queue[] = $e['to'];
                }
            }
        }
        $extra = ($depth ? max($depth) : 0) + 1;
        $cols = [];
        foreach ($nodes as $id => $n) {
            $cols[$depth[$id] ?? $extra][] = $id;
        }
        foreach ($cols as $d => $ids) {
            $y = 40;
            foreach ($ids as $id) {
                $nodes[$id]['x'] = 40 + $d * 330;
                $nodes[$id]['y'] = $y;
                $y += 110 + count(self::ports($nodes[$id])) * 28 + ($nodes[$id]['type'] === 'task' ? 20 : 0);
            }
        }

        return ['nodes' => array_values($nodes), 'edges' => $graph['edges']];
    }

    /** Plain-language problems to show in the builder. */
    public static function issues(array $graph): array
    {
        $out = [];
        $nodes = collect($graph['nodes'] ?? [])->keyBy('id');
        $edges = collect($graph['edges'] ?? []);
        if ($nodes->where('type', 'start')->count() !== 1) {
            $out[] = 'A workflow needs exactly one Start step.';
        }
        foreach ($nodes as $n) {
            foreach (self::ports($n) as $p) {
                if (! $edges->contains(fn ($e) => $e['from'] === $n['id'] && $e['port'] === $p)) {
                    $out[] = "“{$n['name']}”: the “{$p}” exit is not connected, so the workflow ends there.";
                }
            }
            if ($n['type'] === 'task' && empty($n['config']['assignees'])) {
                $out[] = "“{$n['name']}” has nobody assigned. Client Admins will get it.";
            }
        }

        return $out;
    }
}
