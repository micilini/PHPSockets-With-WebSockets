<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Protocol;

enum CloseCode: int
{
    case NORMAL_CLOSURE = 1000;
    case GOING_AWAY = 1001;
    case PROTOCOL_ERROR = 1002;
    case UNSUPPORTED_DATA = 1003;
    case NO_STATUS_RECEIVED = 1005;
    case ABNORMAL_CLOSURE = 1006;
    case INVALID_FRAME_PAYLOAD_DATA = 1007;
    case POLICY_VIOLATION = 1008;
    case MESSAGE_TOO_BIG = 1009;
    case MANDATORY_EXTENSION = 1010;
    case INTERNAL_SERVER_ERROR = 1011;
}
