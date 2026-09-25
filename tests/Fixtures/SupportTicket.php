<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Rating;

final class SupportTicket extends Judgment
{
    public function __construct(public readonly Ticket $ticket) {}

    public function evidence(): array
    {
        return ['ticket' => ['subject' => $this->ticket->subject, 'body' => $this->ticket->body]];
    }

    public function questions(): array
    {
        return [
            'language' => Classification::of('In which language is the ticket written?', labels: ['english', 'italian', 'other']),
            'department' => Classification::of('Which team should handle this ticket?', labels: Department::class),
            'severity' => Rating::of('How badly does the problem affect the customer?', levels: [
                'Cosmetic', 'Annoying', 'Degrading their work', 'Blocking their work',
            ]),
        ];
    }
}
