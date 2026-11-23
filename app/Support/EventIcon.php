<?php

namespace App\Support;

/** Icon and colour for a step in a workflow history timeline. */
class EventIcon
{
    /** @return array{0:string,1:string} [lucide icon, tailwind classes] */
    public static function for(object $e): array
    {
        $a = (string) $e->action;

        return match (true) {
            $a === 'started' => ['play', 'bg-blue-100 text-blue-600'],
            $a === 'signature_requested' => ['send', 'bg-blue-100 text-blue-600'],
            $a === 'signed' => ['pen-line', 'bg-emerald-100 text-emerald-600'],
            $a === 'declined' => ['circle-x', 'bg-red-100 text-red-600'],
            $a === 'answered' && preg_match('/reject|decline|cancel|deny|refus/i', (string) $e->outcome) === 1 => ['x', 'bg-red-100 text-red-600'],
            $a === 'answered' => ['check', 'bg-emerald-100 text-emerald-600'],
            in_array($a, ['finished', 'archived'], true) => ['flag', 'bg-slate-800 text-white'],
            str_contains($a, 'email') => ['mail', 'bg-indigo-100 text-indigo-600'],
            str_contains($a, 'escalat') => ['bell', 'bg-amber-100 text-amber-600'],
            $a === 'error' => ['triangle-alert', 'bg-amber-100 text-amber-600'],
            default => ['circle', 'bg-slate-100 text-slate-500'],
        };
    }
}
