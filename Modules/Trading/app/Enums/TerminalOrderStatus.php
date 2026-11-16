<?php

namespace Modules\Trading\Enums;

enum TerminalOrderStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Open = 'open';
    case Closed = 'closed';
    case Rejected = 'rejected';
}
