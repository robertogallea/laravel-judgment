<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

/** SupportTicket's Question keys, for applications that prefer enums to strings. */
enum TicketQuestion: string
{
    case Language = 'language';
    case Department = 'department';
    case Severity = 'severity';
    case Refund = 'refund';
}
